<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Filament\Central\Resources\ProfileRequestResource;
use App\Models\Tenant;
use App\ProfileRequests\AutoRules\RuleRunner;
use App\ProfileRequests\Enums\RequestStatus;
use App\ProfileRequests\Mail\DailyDigestMail;
use App\ProfileRequests\Models\ProfileRequest;
use App\ProfileRequests\Support\FieldRenderer;
use App\ProfileRequests\Support\InternalRecipients;
use App\ProfileRequests\Support\RequestLinks;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Tagesdigest der Anfragen-Inbox, 08:00 (SUN-RQ #9).
 *
 * Vier Bloecke:
 *  * **automatisch erledigt** — gestern von einer Auto-Regel uebernommen
 *    oder geloescht (`applied_by IS NULL`), mit Portal, Betrieb und Feldern.
 *  * **wartet auf Freigabe** — bestaetigt, aber keine Regel zustaendig.
 *  * **wartet auf Verifikation** — Bestaetigungslink unterwegs oder
 *    Handpruefung angefordert.
 *  * **abgelaufen** — gestern verfallen oder storniert.
 *
 * Die ersten beiden sind die eigentliche Nachricht; die beiden anderen
 * verhindern, dass ein Vorgang unbemerkt in einem Wartezustand versandet.
 *
 * Ist alles leer, geht **keine** Mail raus — eine taegliche Mail ohne Inhalt
 * liest nach einer Woche niemand mehr, und dann wird auch die mit Inhalt
 * ueberlesen.
 *
 * ZENTRALE Tabelle, ein Lauf fuer alle Portale.
 */
class RequestsDailyDigest extends Command
{
    protected $signature = 'requests:daily-digest
        {--date= : Stichtag (Y-m-d), Standard gestern}
        {--show : Nur anzeigen, keine Mail}';

    protected $description = 'Tagesdigest der Anfragen-Inbox an die Administratoren (#9)';

    public function __construct(private readonly RuleRunner $runner)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $tag = $this->option('date') !== null
            ? Carbon::parse((string) $this->option('date'))->startOfDay()
            : now()->subDay()->startOfDay();

        $gruppen = [
            'erledigt' => $this->autoCompleted($tag),
            'freigabe' => $this->waitingForApproval(),
            'verifikation' => $this->waitingForVerification(),
            'abgelaufen' => $this->endedYesterday($tag),
        ];

        $anzahl = array_sum(array_map('count', $gruppen));

        foreach ($gruppen as $name => $zeilen) {
            $this->line(sprintf('%-14s %d', $name, count($zeilen)));
        }

        if ($anzahl === 0) {
            $this->info('Nichts zu melden — keine Mail.');

            return self::SUCCESS;
        }

        if ((bool) $this->option('show')) {
            foreach ($gruppen as $name => $zeilen) {
                foreach ($zeilen as $zeile) {
                    $this->line(sprintf('[%s] REQ-%d %s — %s', $name, $zeile['id'], $zeile['titel'], $zeile['zeile']));
                }
            }

            return self::SUCCESS;
        }

        $empfaenger = $this->recipients();

        if ($empfaenger === []) {
            $this->warn('Keine Empfänger — Digest nicht versendet.');

            return self::SUCCESS;
        }

        Mail::to($empfaenger)->send(new DailyDigestMail($tag, $gruppen, $this->runner->isDisabled()));

        $this->info(sprintf('Digest mit %d Vorgängen an %s versendet.', $anzahl, implode(', ', $empfaenger)));

        return self::SUCCESS;
    }

    // ------------------------------------------------------------------
    // Die vier Bloecke
    // ------------------------------------------------------------------

    /** @return list<array{id: int, titel: string, zeile: string, url: string}> */
    private function autoCompleted(Carbon $tag): array
    {
        $records = ProfileRequest::query()
            ->where('status', RequestStatus::APPLIED->value)
            ->whereNull('applied_by')
            ->whereBetween('applied_at', [$tag, $tag->copy()->endOfDay()])
            ->with('changes')
            ->orderBy('applied_at')
            ->get();

        return $this->rows($records, function (ProfileRequest $request): string {
            $felder = $request->diffLines()
                ->filter(fn ($change): bool => $change->accepted === true)
                ->map(fn ($change): string => FieldRenderer::label((string) $change->field))
                ->values()
                ->all();

            return $felder === []
                ? sprintf('%s automatisch erledigt', $request->type->label())
                : sprintf('%s automatisch übernommen: %s', $request->type->label(), implode(', ', $felder));
        });
    }

    /** @return list<array{id: int, titel: string, zeile: string, url: string}> */
    private function waitingForApproval(): array
    {
        $records = ProfileRequest::query()
            ->where('status', RequestStatus::VERIFIED->value)
            ->orderBy('verified_at')
            ->get();

        return $this->rows($records, function (ProfileRequest $request): string {
            if ($request->grace_until !== null) {
                return sprintf(
                    '%s — Karenzfrist bis %s',
                    $request->type->label(),
                    $request->grace_until->timezone('Europe/Berlin')->format('d.m.Y H:i'),
                );
            }

            return sprintf(
                '%s — bestätigt am %s, wartet auf Freigabe',
                $request->type->label(),
                $request->verified_at?->timezone('Europe/Berlin')->format('d.m.Y H:i') ?? '—',
            );
        });
    }

    /** @return list<array{id: int, titel: string, zeile: string, url: string}> */
    private function waitingForVerification(): array
    {
        $records = ProfileRequest::query()
            ->awaitingVerification()
            ->orderBy('updated_at')
            ->get();

        return $this->rows($records, fn (ProfileRequest $request): string => sprintf(
            '%s — %s',
            $request->type->label(),
            $request->verification_method?->label() ?? 'Bestätigung offen',
        ));
    }

    /** @return list<array{id: int, titel: string, zeile: string, url: string}> */
    private function endedYesterday(Carbon $tag): array
    {
        $records = ProfileRequest::query()
            ->whereIn('status', [RequestStatus::EXPIRED->value, RequestStatus::CANCELLED->value])
            ->whereBetween('updated_at', [$tag, $tag->copy()->endOfDay()])
            ->orderBy('updated_at')
            ->get();

        return $this->rows($records, fn (ProfileRequest $request): string => sprintf(
            '%s — %s',
            $request->type->label(),
            $request->status->label(),
        ));
    }

    // ------------------------------------------------------------------
    // Kleinkram
    // ------------------------------------------------------------------

    /**
     * Zeilen fuer die Mail. Der Betriebsname liegt in der Tenant-DB; die
     * Resource (#7) holt ihn mit einer Abfrage je Portal statt einer je
     * Vorgang — deshalb der Umweg ueber `primeCompanyNames()`.
     *
     * @param  Collection<int, ProfileRequest>  $records
     * @param  callable(ProfileRequest): string  $zeile
     * @return list<array{id: int, titel: string, zeile: string, url: string}>
     */
    private function rows(Collection $records, callable $zeile): array
    {
        if ($records->isEmpty()) {
            return [];
        }

        $records->load('tenant');
        ProfileRequestResource::primeCompanyNames($records);

        return $records->map(fn (ProfileRequest $request): array => [
            'id' => (int) $request->getKey(),
            'titel' => $this->titel($request),
            'zeile' => $zeile($request),
            'url' => RequestLinks::panel($request),
        ])->values()->all();
    }

    private function titel(ProfileRequest $request): string
    {
        $tenant = $request->tenant;
        $portal = $tenant instanceof Tenant ? (string) $tenant->name : 'ohne Portal';
        $betrieb = ProfileRequestResource::companyName($request) ?? 'ohne Betrieb';

        return sprintf('%s · %s', $portal, Str::limit($betrieb, 60));
    }

    /**
     * Empfaenger: Komma-Liste aus der .env, sonst alle nicht gesperrten
     * Administratoren — wie beim Poller-Alarm (#5).
     *
     * @return list<string>
     */
    private function recipients(): array
    {
        return InternalRecipients::from(config('profile-requests.auto.digest_recipients'));
    }
}
