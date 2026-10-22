<?php

declare(strict_types=1);

namespace App\Claims;

use App\Models\Portal\ClaimRequest;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Schreibt den Versandstatus der Claim-Benachrichtigung an den Antrag
 * (Ticket #25).
 *
 * Warum eine eigene Klasse und kein Modell-Aufruf an der Stelle des Versands:
 *
 * * **Der Antrag liegt in der Portal-Datenbank.** Gesetzt wird der Status aber
 *   aus drei Richtungen — dem Service (Freigabe), dem Mail-Ereignis im
 *   Warteschlangen-Arbeiter und dem Fehlschlag des Jobs. Nur der erste Weg hat
 *   verlaesslich Portalkontext; die anderen zwei muessen ihn selbst herstellen
 *   ({@see Tenant::run()}).
 * * **`Tenant::run()` laesst `config('app.url')` auf der Portal-Domain
 *   stehen** (nachgemessen 16.10.2026). Im Panel liest danach jeder, der
 *   `app.url` nimmt, die fremde Domain — etwa Links in einer Mail, die im
 *   selben Request entsteht. Der Wert wird deshalb hier eingerahmt.
 *
 * Kein Aufruf darf werfen: der Status ist Beiwerk, die Freigabe steht schon.
 * Scheitert das Schreiben, bleibt eine Logzeile — nie eine Ausnahme nach oben.
 */
final class ClaimNotificationStatus
{
    /** Der Mailer hat die Nachricht abgegeben. */
    public static function markSent(Tenant|int|string $tenant, int $claimRequestId): void
    {
        self::schreibe($tenant, $claimRequestId, [
            'notified_at' => now(),
            'notification_error' => null,
        ]);
    }

    /**
     * Es ging nichts raus. Der Grund steht am Antrag, damit im Panel nicht
     * "genehmigt" ohne Hinweis steht.
     */
    public static function markFailed(Tenant|int|string $tenant, int $claimRequestId, string $grund): void
    {
        self::schreibe($tenant, $claimRequestId, [
            'notified_at' => null,
            // Die Spalte ist 255 Zeichen lang; eine Ausnahme-Meldung ist
            // schnell laenger und wuerde den Schreibzugriff sonst abbrechen.
            'notification_error' => mb_substr(trim($grund), 0, 255),
        ]);
    }

    /**
     * @param  array<string, mixed>  $werte
     */
    private static function schreibe(Tenant|int|string $tenant, int $claimRequestId, array $werte): void
    {
        $model = $tenant instanceof Tenant ? $tenant : Tenant::query()->find($tenant);

        if (! $model instanceof Tenant) {
            Log::warning('ClaimNotificationStatus: Portal nicht gefunden', [
                'tenant' => is_object($tenant) ? null : $tenant,
                'claim_request_id' => $claimRequestId,
            ]);

            return;
        }

        $appUrl = config('app.url');

        try {
            $model->run(function () use ($claimRequestId, $werte): void {
                ClaimRequest::query()->whereKey($claimRequestId)->update($werte);
            });
        } catch (Throwable $e) {
            Log::warning('ClaimNotificationStatus: Versandstatus nicht gespeichert', [
                'tenant_id' => $model->getKey(),
                'claim_request_id' => $claimRequestId,
                'error' => $e->getMessage(),
            ]);
        } finally {
            config(['app.url' => $appUrl]);
        }
    }
}
