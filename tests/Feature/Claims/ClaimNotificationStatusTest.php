<?php

declare(strict_types=1);

namespace Tests\Feature\Claims;

use App\Claims\ClaimNotificationStatus;
use App\Models\Portal\City;
use App\Models\Portal\ClaimRequest;
use App\Models\Portal\Company;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ClaimService;
use Illuminate\Support\Str;
use Tests\Feature\FeatureTest;

/**
 * Versandstatus der Claim-Benachrichtigung (Ticket #25).
 *
 * Der Punkt des Tickets: nach der Freigabe stand am Antrag nur „genehmigt".
 * Ob der Antragsteller eine Mail bekam, war nirgends zu sehen — der Versand
 * laeuft ueber die Warteschlange, ein Fehlschlag landete nur im Log.
 *
 * Geprueft wird deshalb beides: der gute Fall (`notified_at` gesetzt, und zwar
 * **durch das Mail-Ereignis**, nicht durch den Aufruf) und die stillen Faelle,
 * in denen nichts rausgeht.
 */
class ClaimNotificationStatusTest extends FeatureTest
{
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    public function test_freigabe_setzt_den_versandstatus(): void
    {
        // createUser() haengt den Nutzer ans Portal — ohne diese Mitgliedschaft
        // bricht die Rollenvergabe in der Freigabe ab (TenantPermissionService
        // liest die Pivot-Zeile), und es gaebe weder Mail noch Status.
        $nutzer = $this->createUser($this->tenant, [], ['email' => 'antragsteller@example.com']);
        $antragId = $this->antragAnlegen($nutzer);

        $this->freigeben($antragId);

        $zustand = $this->zustand($antragId);

        $this->assertSame(ClaimRequest::STATUS_APPROVED, $zustand['status']);
        $this->assertTrue($zustand['notified'], 'notified_at muss nach dem Versand stehen.');
        $this->assertNull($zustand['error']);
        $this->assertSame('versendet', $zustand['zustand']);
    }

    /**
     * Der eigentliche Grund fuer den Umweg ueber `MessageSent`: ohne
     * Warteschlangen-Durchlauf darf nichts auf „versendet" springen. Hier wird
     * die Mail nur eingereiht und nie abgegeben — der Antrag bleibt
     * „ausstehend", und die Freigabe gilt trotzdem.
     */
    public function test_ohne_abgegebene_mail_bleibt_der_status_ausstehend(): void
    {
        $nutzer = $this->createUser($this->tenant, [], ['email' => 'wartet@example.com']);
        $antragId = $this->antragAnlegen($nutzer);

        \Illuminate\Support\Facades\Mail::fake();

        $this->freigeben($antragId);

        $zustand = $this->zustand($antragId);

        $this->assertSame(ClaimRequest::STATUS_APPROVED, $zustand['status'], 'Die Freigabe darf nie an der Mail haengen.');
        $this->assertFalse($zustand['notified']);
        $this->assertSame('ausstehend', $zustand['zustand']);
    }

    public function test_antrag_ohne_brauchbare_adresse_nennt_den_grund(): void
    {
        $nutzer = $this->createUser($this->tenant);
        $antragId = $this->antragAnlegen($nutzer);

        // Adresse nachtraeglich unbrauchbar machen: der Weg, auf dem vorher
        // stillschweigend nichts gesendet wurde.
        User::query()->whereKey($nutzer->getKey())->update(['email' => 'keine-adresse']);

        $this->freigeben($antragId);

        $zustand = $this->zustand($antragId);

        $this->assertSame(ClaimRequest::STATUS_APPROVED, $zustand['status']);
        $this->assertFalse($zustand['notified']);
        $this->assertSame('gescheitert', $zustand['zustand']);
        $this->assertStringContainsString('E-Mail-Adresse', (string) $zustand['error']);
    }

    public function test_fehlschlag_des_jobs_steht_am_antrag(): void
    {
        $nutzer = $this->createUser($this->tenant);
        $antragId = $this->antragAnlegen($nutzer);

        ClaimNotificationStatus::markFailed($this->tenant, $antragId, 'Versand gescheitert: SMTP weg');

        $zustand = $this->zustand($antragId);

        $this->assertSame('gescheitert', $zustand['zustand']);
        $this->assertStringContainsString('SMTP weg', (string) $zustand['error']);
    }

    /**
     * `Tenant::run()` setzt `config('app.url')` auf die Portal-Domain und
     * stellt sie nicht zurueck. Der Schreiber rahmt den Wert ein — sonst bauen
     * Mails im selben Request Links auf eine fremde Domain.
     */
    public function test_app_url_bleibt_nach_dem_schreiben_stehen(): void
    {
        $nutzer = $this->createUser($this->tenant);
        $antragId = $this->antragAnlegen($nutzer);

        $vorher = config('app.url');

        ClaimNotificationStatus::markSent($this->tenant, $antragId);

        $this->assertSame($vorher, config('app.url'));
    }

    private function freigeben(int $antragId): void
    {
        $this->tenant->run(function () use ($antragId): void {
            $antrag = ClaimRequest::query()->findOrFail($antragId);

            // Der Service verlangt eine Pruefer-ID (int); in der Oberflaeche ist
            // das auth()->id(). Hier genuegt eine beliebige.
            app(ClaimService::class)->approveClaimRequest($antrag, 1);
        });
    }

    /**
     * Zustand des Antrags, ausgelesen **im** Portalkontext.
     *
     * Ein ClaimRequest, der aus `Tenant::run()` herausgetragen wird, kann
     * seine eigenen Datumsfelder nicht mehr lesen: der Cast fragt die
     * Verbindung `tenant` nach dem Datumsformat, und die ist nach dem Lauf
     * abgeraeumt ("Database connection [tenant] not configured").
     *
     * @return array{status: string, notified: bool, error: string|null, zustand: string}
     */
    private function zustand(int $antragId): array
    {
        return $this->tenant->run(function () use ($antragId): array {
            $antrag = ClaimRequest::query()->findOrFail($antragId);

            return [
                'status' => (string) $antrag->status,
                'notified' => $antrag->notified_at !== null,
                'error' => $antrag->notification_error,
                'zustand' => $antrag->notificationState()['zustand'],
            ];
        });
    }

    private function antragAnlegen(User $nutzer): int
    {
        return $this->tenant->run(function () use ($nutzer): int {
            $stadt = City::create(['name' => 'Musterstadt', 'slug' => 'musterstadt-'.Str::random(5), 'zipcode' => '90402']);

            $firma = Company::create([
                'name' => 'Musterbetrieb',
                'slug' => 'musterbetrieb-'.Str::random(5),
                'city_id' => $stadt->id,
                'is_active' => true,
            ]);

            return (int) ClaimRequest::create([
                'company_id' => $firma->id,
                'user_id' => $nutzer->getKey(),
                'status' => ClaimRequest::STATUS_PENDING,
            ])->getKey();
        });
    }
}
