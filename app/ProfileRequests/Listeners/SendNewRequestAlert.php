<?php

declare(strict_types=1);

namespace App\ProfileRequests\Listeners;

use App\Filament\Central\Resources\ProfileRequestResource;
use App\Models\Tenant;
use App\ProfileRequests\Enums\RequestType;
use App\ProfileRequests\Events\ProfileRequestReceived;
use App\ProfileRequests\Mail\NewRequestAlertMail;
use App\ProfileRequests\Support\InternalRecipients;
use App\ProfileRequests\Support\RequestLinks;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sofortmail bei Löschwunsch und Übernahme (SUN-RQ #23).
 *
 * Entscheidung von Enes (22.10.2026): der Tagesdigest bleibt, zusätzlich
 * eine Mail sofort — aber nur für diese zwei Typen. Eine geänderte
 * Telefonnummer kann bis 08:00 warten; ein Löschwunsch läuft auf eine
 * Karenzfrist zu, und eine Übernahme entscheidet, wer den Eintrag führt.
 *
 * In der Queue, nicht im Request: der Betrieb, der gerade sein Formular
 * abgeschickt hat, soll nicht auf unseren SMTP-Server warten.
 *
 * Ein Fehlschlag beim Versand bleibt im Log und reißt den Vorgang nicht mit
 * — die Anfrage ist das Wichtigere, und im Panel steht sie ohnehin.
 */
class SendNewRequestAlert implements ShouldQueue
{
    // Keine eigene Queue: Horizon bedient für SUN `default`,
    // `guide-research`, `guide-write` und `guide-publish` — eine Queue
    // `mail` gibt es nur in den Nachbaranwendungen auf demselben Server.
    // Ein Job mit `$queue = 'mail'` lag hier still und wartete auf einen
    // Worker, den es nicht gibt (gemessen am 22.10.2026, ein Job in
    // `queues:mail`). Alles andere im Modul läuft ebenfalls über `default`.

    public function handle(ProfileRequestReceived $event): void
    {
        $request = $event->request;

        if (! in_array($request->type, [RequestType::DELETE, RequestType::CLAIM], true)) {
            return;
        }

        $empfaenger = InternalRecipients::from(config('profile-requests.auto.digest_recipients'));

        if ($empfaenger === []) {
            Log::warning('profile-requests: Sofortmeldung ohne Empfänger', [
                'request_id' => $request->getKey(),
                'type' => $request->type->value,
            ]);

            return;
        }

        $tenant = $request->tenant;
        $portal = $tenant instanceof Tenant ? (string) $tenant->name : 'ohne Portal';
        $betrieb = ProfileRequestResource::companyName($request) ?? 'ohne Betrieb';

        try {
            Mail::to($empfaenger)->send(new NewRequestAlertMail(
                $request,
                $portal,
                Str::limit($betrieb, 80),
                RequestLinks::panel($request),
            ));
        } catch (Throwable $e) {
            Log::warning('profile-requests: Sofortmeldung nicht versendet', [
                'request_id' => $request->getKey(),
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
