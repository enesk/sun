<?php

declare(strict_types=1);

namespace App\ProfileRequests\Actions;

use App\Models\User;
use App\ProfileRequests\Enums\RequestEventType;
use App\ProfileRequests\Mail\RequestReplyMail;
use App\ProfileRequests\Models\ProfileRequest;
use App\ProfileRequests\Models\RequestEvent;
use App\ProfileRequests\Support\TenantCompanyResolver;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Antwort an den Anfragenden verschicken (SUN-RQ #7).
 *
 * Der Entwurf steht in `profile_requests.ai_reply_draft` (#6); verschickt
 * wird ausschliesslich der Text, den Enes im Panel bestaetigt hat. Der
 * Betreff bekommt die Vorgangsmarke `[REQ-{id}]`, `Reply-To` zeigt auf das
 * Sammelpostfach — die Antwort des Betriebs kommt damit ueber den Poller
 * (#5) als Ereignis an denselben Vorgang zurueck.
 *
 * Der Versand aendert den Zustand nicht: eine Rueckfrage ist keine
 * Entscheidung. Protokolliert wird sie als `mail_sent` mit Empfaenger und
 * Betreff — der Text selbst steht in der Mail, nicht im Protokoll.
 */
class SendReply
{
    /** Kuerzung des Betreffs im Protokoll. */
    private const LOG_SUBJECT_LIMIT = 120;

    public function __construct(
        private readonly TenantCompanyResolver $resolver,
    ) {}

    /**
     * @return array{ok: bool, message: string}
     */
    public function __invoke(ProfileRequest $request, string $betreff, string $nachricht, ?User $actor = null): array
    {
        $empfaenger = trim((string) $request->requester_email);
        $nachricht = trim($nachricht);

        if (filter_var($empfaenger, FILTER_VALIDATE_EMAIL) === false) {
            return ['ok' => false, 'message' => 'Diese Anfrage hat keine brauchbare Absenderadresse.'];
        }

        if ($nachricht === '') {
            return ['ok' => false, 'message' => 'Eine leere Antwort wird nicht verschickt.'];
        }

        $betreff = RequestReplyMail::betreffMitMarke($request, $betreff);

        // Ohne Sammelpostfach gibt es kein Reply-To, und eine Antwort des
        // Betriebs kaeme nie beim Poller an. Der Versand laeuft trotzdem —
        // aber es steht im Log, warum der Faden danach abreisst.
        if ((string) config('profile-requests.mailbox.address') === '') {
            Log::warning('profile-requests: Antwort ohne Reply-To versendet — REQUESTS_MAILBOX_ADDRESS fehlt', [
                'request_id' => $request->getKey(),
            ]);
        }

        $tenant = $request->tenant_id === null
            ? null
            : $this->resolver->tenant((int) $request->tenant_id);

        try {
            Mail::to($empfaenger)->send(new RequestReplyMail($request, $tenant, $betreff, $nachricht));
        } catch (Throwable $exception) {
            Log::warning('profile-requests: Antwort aus dem Panel nicht versendet', [
                'request_id' => $request->getKey(),
                'exception' => $exception->getMessage(),
            ]);

            return ['ok' => false, 'message' => 'Der Versand ist fehlgeschlagen — siehe Log.'];
        }

        $request->recordEvent(
            RequestEventType::MAIL_SENT,
            [
                'mail' => 'reply',
                'to' => $empfaenger,
                'subject' => Str::limit($betreff, self::LOG_SUBJECT_LIMIT),
            ],
            $actor === null ? RequestEvent::ACTOR_SYSTEM : RequestEvent::ACTOR_USER,
            $actor?->getKey() === null ? null : (int) $actor->getKey(),
        );

        return ['ok' => true, 'message' => "Antwort an {$empfaenger} verschickt."];
    }

    /**
     * Vorbelegung des Formulars: Betreff mit Marke, Text aus dem
     * KI-Entwurf. Fehlt der Entwurf, bleibt das Feld leer — ein
     * selbstgebauter Platzhaltertext waere nur im Weg.
     *
     * @return array{betreff: string, nachricht: string}
     */
    public function draft(ProfileRequest $request): array
    {
        return [
            'betreff' => RequestReplyMail::betreffMitMarke($request, 'Ihre Anfrage zu Ihrem Eintrag'),
            'nachricht' => (string) ($request->ai_reply_draft ?? ''),
        ];
    }
}
