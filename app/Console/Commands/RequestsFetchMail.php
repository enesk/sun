<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\ProfileRequests\Enums\RequestEventType;
use App\ProfileRequests\Ingestion\ImapMailFetcher;
use App\ProfileRequests\Ingestion\IngestionFilter;
use App\ProfileRequests\Ingestion\MailHeaders;
use App\ProfileRequests\Ingestion\MailOutcome;
use App\ProfileRequests\Ingestion\MailToRequestMapper;
use App\ProfileRequests\Ingestion\PollerHealth;
use App\ProfileRequests\Ingestion\TenantFromRecipient;
use App\ProfileRequests\Ingestion\ThreadMatcher;
use App\ProfileRequests\Jobs\ClassifyRequest;
use App\ProfileRequests\Models\ProfileRequest;
use App\ProfileRequests\Models\RequestEvent;
use App\ProfileRequests\Models\RequestMailLog;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;
use Webklex\PHPIMAP\Message;

/**
 * Abruf des zentralen Sammelpostfachs (SUN-RQ, Ticket #5).
 *
 * Laeuft alle 5 Minuten (routes/console.php, withoutOverlapping). Je
 * ungelesener Mail:
 *
 *  1. Message-ID im Eingangsprotokoll reservieren — der Unique-Index ist
 *     die Dedupe-Sperre. Wer hier nicht durchkommt, war schon da.
 *  2. Auto-Antwort, Newsletter, gesperrter Absender? -> verworfen,
 *     protokolliert, keine Anfrage.
 *  3. Antwort in einem laufenden Vorgang? -> Ereignis an der bestehenden
 *     Anfrage statt einer neuen.
 *  4. Sonst: neue Anfrage (source=email, status=new) mit Rohmail und
 *     Anhaengen; das Portal kommt aus den Empfaengerkopfzeilen. Danach geht
 *     ClassifyRequest (#6) in die Queue: Typ, Betrieb, Diff-Vorschlag und
 *     Verifikation.
 *  5. Mail als gelesen markieren und nach 'Verarbeitet' verschieben.
 *
 * Eine Mail, die beim Verarbeiten einen Fehler wirft, haelt den Lauf nicht
 * auf: sie bleibt im Protokoll als `processing` stehen, der Lauf macht mit
 * der naechsten weiter, und der Fehler steht im Log.
 *
 * Verbindungs- und Anmeldefehler dagegen beenden den Lauf und gehen an
 * {@see PollerHealth} — nach drei in Folge geht eine Mail raus.
 */
class RequestsFetchMail extends Command
{
    protected $signature = 'requests:fetch-mail
        {--limit= : Hoechstzahl Mails in diesem Lauf (Standard: profile-requests.mailbox.poll_batch_size)}';

    protected $description = 'Liest das zentrale Anfragen-Postfach und legt je Mail eine Anfrage an';

    public function handle(
        ImapMailFetcher $fetcher,
        IngestionFilter $filter,
        ThreadMatcher $threadMatcher,
        MailToRequestMapper $mapper,
        TenantFromRecipient $tenantFromRecipient,
        PollerHealth $health,
    ): int {
        $limit = $this->option('limit') === null ? null : max(1, (int) $this->option('limit'));

        // Solange der Zugang zum Sammelpostfach fehlt, ist nichts abzurufen.
        // Das ist kein Fehlschlag: wuerde es als solcher zaehlen, ginge
        // alle fuenf Minuten ein Alarm an die Administratoren, bis die
        // Zugangsdaten in der .env stehen.
        if (! $fetcher->configured()) {
            $this->line('Sammelpostfach noch nicht eingerichtet (REQUESTS_IMAP_* fehlen) — Lauf uebersprungen.');

            return self::SUCCESS;
        }

        try {
            $messages = $fetcher->unread($limit);
        } catch (Throwable $e) {
            $anzahl = $health->recordFailure($e);
            $this->error("Postfach nicht erreichbar ({$anzahl}. Fehlschlag in Folge): {$e->getMessage()}");

            return self::FAILURE;
        }

        if ($health->recordSuccess()) {
            $this->info('Postfach wieder erreichbar.');
        }

        $zaehler = ['neu' => 0, 'antwort' => 0, 'verworfen' => 0, 'doppelt' => 0, 'fehler' => 0];

        foreach ($messages as $message) {
            try {
                $ergebnis = $this->process($message, $filter, $threadMatcher, $mapper, $tenantFromRecipient);
                $zaehler[$ergebnis]++;

                $fetcher->finish($message);
            } catch (Throwable $e) {
                $zaehler['fehler']++;

                Log::error('profile-requests: Mail nicht verarbeitbar', [
                    'message_id' => MailHeaders::messageId($message),
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        $fetcher->disconnect();

        $this->info(sprintf(
            '%d Mails: %d neu, %d Antworten, %d verworfen, %d doppelt, %d Fehler.',
            count($messages),
            $zaehler['neu'],
            $zaehler['antwort'],
            $zaehler['verworfen'],
            $zaehler['doppelt'],
            $zaehler['fehler'],
        ));

        return $zaehler['fehler'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return 'neu'|'antwort'|'verworfen'|'doppelt'
     */
    private function process(
        Message $message,
        IngestionFilter $filter,
        ThreadMatcher $threadMatcher,
        MailToRequestMapper $mapper,
        TenantFromRecipient $tenantFromRecipient,
    ): string {
        $messageId = MailHeaders::messageId($message) ?? $this->syntheticMessageId($message);

        $log = $this->reserve($message, $messageId);

        if ($log === null) {
            return 'doppelt';
        }

        $grund = $filter->discardReason($message);

        if ($grund !== null) {
            $this->discard($log, $message, $threadMatcher, $grund);

            return 'verworfen';
        }

        $vorgang = $threadMatcher->match($message);

        if ($vorgang instanceof ProfileRequest) {
            $mapper->appendReply($vorgang, $message);

            $log->update([
                'outcome' => MailOutcome::REPLIED,
                'request_id' => $vorgang->id,
            ]);

            return 'antwort';
        }

        $portal = $tenantFromRecipient->resolve($message);

        $request = $mapper->create($message, $portal['tenant_id'], $portal['recipient']);

        $log->update([
            'outcome' => MailOutcome::CREATED,
            'request_id' => $request->id,
            'recipient' => $portal['recipient'],
        ]);

        // Klassifikation und Verifikation laufen in der Queue (#6): der
        // Pollerlauf soll nicht an einem API-Aufruf je Mail haengen, und ein
        // Fehler beim Modell darf die naechste Mail nicht blockieren.
        ClassifyRequest::dispatch((int) $request->id);

        return 'neu';
    }

    /**
     * Message-ID im Protokoll belegen. null heisst: diese Mail wurde schon
     * einmal verarbeitet — der Unique-Index hat sie abgewiesen.
     */
    private function reserve(Message $message, string $messageId): ?RequestMailLog
    {
        if (RequestMailLog::alreadySeen($messageId)) {
            return null;
        }

        try {
            return RequestMailLog::create([
                'message_id_hash' => RequestMailLog::hashMessageId($messageId),
                'message_id' => Str::limit($messageId, 500, ''),
                'outcome' => MailOutcome::PROCESSING,
                'from_email' => MailHeaders::firstAddress($message, 'from'),
                'subject' => Str::limit((string) $message->getSubject(), 250),
                'mail_date' => $this->mailDate($message),
            ]);
        } catch (QueryException $e) {
            // Zwei Laeufe gleichzeitig (oder ein Wiederholungsversuch des
            // Postfachs) — der Index hat entschieden, hier ist Schluss.
            if ($this->isDuplicateKey($e)) {
                return null;
            }

            throw $e;
        }
    }

    private function discard(RequestMailLog $log, Message $message, ThreadMatcher $threadMatcher, string $grund): void
    {
        $log->update([
            'outcome' => MailOutcome::DISCARDED,
            'reason' => $grund,
        ]);

        // Gehoert die verworfene Mail zu einem laufenden Vorgang (etwa die
        // Abwesenheitsnotiz auf unsere Bestaetigungsmail), steht sie auch in
        // dessen Protokoll — sonst fehlt dort die Erklaerung, warum nie eine
        // Antwort kam.
        $vorgang = $threadMatcher->match($message);

        if ($vorgang instanceof ProfileRequest) {
            $log->update(['request_id' => $vorgang->id]);

            $vorgang->recordEvent(
                RequestEventType::DISCARDED,
                [
                    'reason' => $grund,
                    'from' => MailHeaders::firstAddress($message, 'from'),
                    'subject' => Str::limit((string) $message->getSubject(), 250),
                ],
                RequestEvent::ACTOR_SYSTEM,
            );
        }
    }

    /**
     * Mails ohne Message-ID gibt es; ohne Schluessel waere jede von ihnen
     * bei jedem Lauf neu. Der Ersatz aus Absender, Betreff, Datum und
     * Groesse ist stabil genug, um dieselbe Mail wiederzuerkennen.
     */
    private function syntheticMessageId(Message $message): string
    {
        return 'synthetisch-'.hash('sha256', implode('|', [
            MailHeaders::firstAddress($message, 'from') ?? '',
            (string) $message->getSubject(),
            MailHeaders::text($message, 'date'),
            (string) strlen($message->getRawBody()),
        ]));
    }

    private function mailDate(Message $message): ?\Illuminate\Support\Carbon
    {
        try {
            $date = $message->getDate()?->first();

            return $date === null ? null : \Illuminate\Support\Carbon::parse($date);
        } catch (Throwable) {
            return null;
        }
    }

    private function isDuplicateKey(QueryException $e): bool
    {
        return in_array((string) ($e->errorInfo[1] ?? ''), ['1062'], true)
            || str_contains(strtolower($e->getMessage()), 'duplicate');
    }
}
