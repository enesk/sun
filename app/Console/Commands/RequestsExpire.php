<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\ProfileRequests\Enums\RequestStatus;
use App\ProfileRequests\Enums\VerificationMethod;
use App\ProfileRequests\Models\ProfileRequest;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Anfragen ohne Fortschritt schliessen (SUN-RQ #6).
 *
 * Zwei Faelle, beide gemessen an `verification_ttl_hours` (72 Stunden):
 *
 *  1. **Magic-Link abgelaufen** — der Vorgang wartet auf eine Bestaetigung,
 *     die Frist ist vorbei. Das ist der Regelfall: in der Stichprobe
 *     (docs/profile-requests.md 2.3) kommt jede fuenfte Anfrage von einer
 *     Adresse, die nie wieder etwas von sich hoeren laesst.
 *  2. **Liegengeblieben** — der Vorgang steht noch auf `new` oder
 *     `classified`, obwohl die Klassifikation (#6) und die Verifikation
 *     direkt nacheinander laufen. Dann hat etwas nicht funktioniert; nach
 *     der Frist gehoert er trotzdem nicht mehr in die offene Inbox.
 *
 * **Nicht** abgelaufen wird eine Anfrage mit Kennzeichen `manual`: dort
 * wartet niemand auf einen Klick, sondern Enes auf eine Entscheidung. Ein
 * Ablauf wuerde ihm die Aufgabe wegnehmen, statt sie zu erledigen.
 *
 * Laeuft stuendlich (routes/console.php). ZENTRALE Tabelle, ein Lauf fuer
 * alle Portale.
 */
class RequestsExpire extends Command
{
    protected $signature = 'requests:expire
        {--dry-run : Nur anzeigen, was ablaufen wuerde}';

    protected $description = 'Setzt Anfragen ohne Fortschritt auf abgelaufen (Magic-Link verfallen)';

    public function handle(): int
    {
        $ttlHours = (int) config('profile-requests.verification_ttl_hours', 72);
        $grenze = now()->subHours($ttlHours);
        $dryRun = (bool) $this->option('dry-run');

        $zaehler = ['link' => 0, 'liegengeblieben' => 0, 'fehler' => 0];

        foreach ($this->dueForExpiry($grenze)->cursor() as $request) {
            /** @var ProfileRequest $request */
            $art = $request->status === RequestStatus::AWAITING_VERIFICATION ? 'link' : 'liegengeblieben';

            if ($dryRun) {
                $this->line(sprintf(
                    'REQ-%d (%s, %s, Portal %s) wuerde ablaufen [%s]',
                    $request->id,
                    $request->type?->value ?? '?',
                    $request->status->value,
                    $request->tenant_id ?? '—',
                    $art,
                ));

                $zaehler[$art]++;

                continue;
            }

            try {
                if ($request->markExpired()) {
                    $zaehler[$art]++;
                }
            } catch (Throwable $e) {
                $zaehler['fehler']++;

                Log::warning('profile-requests: Ablauf nicht moeglich', [
                    'request_id' => $request->id,
                    'status' => $request->status->value,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        $this->info(sprintf(
            '%d abgelaufen (%d Bestätigungslink verfallen, %d liegengeblieben), %d Fehler.',
            $zaehler['link'] + $zaehler['liegengeblieben'],
            $zaehler['link'],
            $zaehler['liegengeblieben'],
            $zaehler['fehler'],
        ));

        return $zaehler['fehler'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return Builder<ProfileRequest>
     */
    private function dueForExpiry(\Illuminate\Support\Carbon $grenze): Builder
    {
        return ProfileRequest::query()
            ->where(function (Builder $query) use ($grenze): void {
                // 1. Bestaetigungslink verfallen.
                $query->where(function (Builder $inner) use ($grenze): void {
                    $inner->where('status', RequestStatus::AWAITING_VERIFICATION->value)
                        ->where('verification_method', '!=', VerificationMethod::MANUAL->value)
                        ->where(function (Builder $frist) use ($grenze): void {
                            $frist->where('verification_expires_at', '<', now())
                                // Ohne Frist zaehlt das Alter des Vorgangs:
                                // eine nie versendete Mail hinterlaesst genau
                                // so einen Datensatz.
                                ->orWhere(function (Builder $ohneFrist) use ($grenze): void {
                                    $ohneFrist->whereNull('verification_expires_at')
                                        ->where('updated_at', '<', $grenze);
                                });
                        });
                });

                // 2. In `new` oder `classified` liegengeblieben.
                $query->orWhere(function (Builder $inner) use ($grenze): void {
                    $inner->whereIn('status', [RequestStatus::NEW->value, RequestStatus::CLASSIFIED->value])
                        ->where('updated_at', '<', $grenze);
                });
            })
            ->orderBy('id');
    }
}
