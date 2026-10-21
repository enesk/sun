<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\ProfileRequests\Enums\RequestEventType;
use App\ProfileRequests\Ingestion\ImapMailFetcher;
use App\ProfileRequests\Ingestion\IngestionFilter;
use App\ProfileRequests\Ingestion\MailHeaders;
use App\ProfileRequests\Ingestion\MailOutcome;
use App\ProfileRequests\Ingestion\MailToRequestMapper;
use App\ProfileRequests\Ingestion\TenantFromRecipient;
use App\ProfileRequests\Ingestion\ThreadMatcher;
use App\ProfileRequests\Jobs\ClassifyRequest;
use App\ProfileRequests\Models\ProfileRequest;
use App\ProfileRequests\Models\RequestEvent;
use App\ProfileRequests\Models\RequestMailLog;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;
use Webklex\PHPIMAP\Message;

/**
 * Einmaliger Import des bestehenden Rueckstands (SUN-RQ #12).
 *
 *   php artisan requests:import-mailbox --since=2026-09-01 --dry-run
 *   php artisan requests:import-mailbox --since=2026-09-01
 *   php artisan requests:import-mailbox --folder=Rueckstand --no-classify
 *
 * Der Poller (`requests:fetch-mail`, #5) liest nur **ungelesene** Mails und
 * sortiert sie weg. Die offenen Anfragen, die heute in den Postfaechern
 * liegen, sind aber laengst geoeffnet — der Poller sieht sie nie. Dieser
 * Befehl holt genau diese Mails nach.
 *
 * Drei Unterschiede zum Poller, alle absichtlich:
 *
 *  1. **Das Postfach bleibt unberuehrt.** Kein Gelesen-Flag, kein
 *     Verschieben. Der Import ist wiederholbar, und das Postfach bleibt die
 *     unabhaengige zweite Spur, solange Enes den Rueckstand noch von Hand
 *     abarbeitet.
 *  2. **Keine Bestaetigungsmail.** Die Klassifikation laeuft (der Vorschlag
 *     ist der halbe Zeitgewinn), die Verifikation nicht: ein Magic-Link zu
 *     einer Mail von vor drei Wochen ist fuer den Betrieb unverstaendlich.
 *     Mit `--verify` laeuft sie trotzdem.
 *  3. **Zeitfenster.** `--since` begrenzt den Import; ohne Angabe wird der
 *     ganze Ordner gelesen, was bei einem gewachsenen Postfach Hunderte
 *     Mails bedeutet.
 *
 * Doppelt angelegt wird nichts: der Unique-Index auf der Message-ID in
 * `request_mail_log` ist dieselbe Sperre wie beim Poller. Ein zweiter Lauf
 * meldet die bereits importierten Mails als „doppelt".
 */
class RequestsImportMailbox extends Command
{
    protected $signature = 'requests:import-mailbox
        {--folder= : Ordner im Sammelpostfach (Standard: profile-requests.mailbox.imap.folder)}
        {--since= : Nur Mails ab diesem Datum, z. B. 2026-09-01}
        {--limit= : Hoechstzahl Mails in diesem Lauf}
        {--verify : Auch den Verifikationsweg anstossen (Standard: nein)}
        {--no-classify : Ohne KI-Vorschlag importieren}
        {--dry-run : Nur zeigen, was importiert wuerde}';

    protected $description = 'Importiert den bestehenden Rueckstand offener Anfrage-Mails aus dem Sammelpostfach in das Panel';

    public function handle(
        ImapMailFetcher $fetcher,
        IngestionFilter $filter,
        ThreadMatcher $threadMatcher,
        MailToRequestMapper $mapper,
        TenantFromRecipient $tenantFromRecipient,
    ): int {
        $folder = $this->option('folder') ?: null;
        $since = $this->since();

        if ($since === false) {
            return self::FAILURE;
        }

        $limit = $this->option('limit') === null ? null : max(1, (int) $this->option('limit'));

        try {
            $messages = $fetcher->backlog($folder, $since, $limit);
        } catch (Throwable $e) {
            $this->error("Postfach nicht lesbar: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info(sprintf(
            '%d Mails im Ordner %s%s.',
            count($messages),
            $folder ?? (string) config('profile-requests.mailbox.imap.folder', 'INBOX'),
            $since === null ? '' : " ab {$since}",
        ));

        $zaehler = ['neu' => 0, 'antwort' => 0, 'verworfen' => 0, 'doppelt' => 0, 'fehler' => 0];

        foreach ($messages as $message) {
            try {
                $zaehler[$this->import($message, $filter, $threadMatcher, $mapper, $tenantFromRecipient)]++;
            } catch (Throwable $e) {
                $zaehler['fehler']++;

                Log::error('profile-requests: Rueckstandsmail nicht importierbar', [
                    'message_id' => MailHeaders::messageId($message),
                    'exception' => $e->getMessage(),
                ]);

                $this->warn('Fehler bei „'.Str::limit((string) $message->getSubject(), 60).'": '.$e->getMessage());
            }
        }

        $fetcher->disconnect();

        $this->newLine();
        $this->table(
            ['neu', 'Antworten', 'verworfen', 'doppelt', 'Fehler'],
            [[$zaehler['neu'], $zaehler['antwort'], $zaehler['verworfen'], $zaehler['doppelt'], $zaehler['fehler']]],
        );

        if ($this->option('dry-run')) {
            $this->comment('Trockenlauf — es wurde nichts angelegt.');
        }

        return $zaehler['fehler'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Eine Mail einlesen. Der Ablauf ist der des Pollers (#5) ohne den
     * Postfach-Teil; bewusst hier und nicht in RequestsFetchMail
     * mitbenutzt, weil die Entscheidungen auseinandergehen (Dedupe-Sperre
     * nur beim echten Lauf, keine Verifikation, Trockenlauf).
     *
     * @return 'neu'|'antwort'|'verworfen'|'doppelt'
     */
    private function import(
        Message $message,
        IngestionFilter $filter,
        ThreadMatcher $threadMatcher,
        MailToRequestMapper $mapper,
        TenantFromRecipient $tenantFromRecipient,
    ): string {
        $messageId = MailHeaders::messageId($message) ?? $this->syntheticMessageId($message);
        $betreff = Str::limit((string) $message->getSubject(), 70);

        if (RequestMailLog::alreadySeen($messageId)) {
            $this->line("  doppelt   {$betreff}");

            return 'doppelt';
        }

        $grund = $filter->discardReason($message);

        if ($grund !== null) {
            $this->line("  verworfen {$betreff} ({$grund})");

            if (! $this->option('dry-run')) {
                $this->log($message, $messageId, MailOutcome::DISCARDED, ['reason' => $grund]);
            }

            return 'verworfen';
        }

        $vorgang = $threadMatcher->match($message);

        if ($vorgang instanceof ProfileRequest) {
            $this->line("  Antwort   {$betreff} → #{$vorgang->id}");

            if (! $this->option('dry-run')) {
                $mapper->appendReply($vorgang, $message);
                $this->log($message, $messageId, MailOutcome::REPLIED, ['request_id' => $vorgang->id]);
            }

            return 'antwort';
        }

        $portal = $tenantFromRecipient->resolve($message);

        if ($this->option('dry-run')) {
            $this->line(sprintf('  neu       %s (Portal: %s)', $betreff, $portal['tenant_id'] ?? 'unbekannt'));

            return 'neu';
        }

        $log = $this->log($message, $messageId, MailOutcome::PROCESSING);

        if ($log === null) {
            // Zwei Laeufe gleichzeitig — der Index hat entschieden.
            return 'doppelt';
        }

        $request = $mapper->create($message, $portal['tenant_id'], $portal['recipient']);

        $log->update([
            'outcome' => MailOutcome::CREATED,
            'request_id' => $request->id,
            'recipient' => $portal['recipient'],
        ]);

        // Dass dieser Vorgang aus dem Rueckstand stammt, steht an ihm: eine
        // Woche spaeter ist in der Auswertung des Pilots sonst nicht
        // unterscheidbar, was neu eingegangen und was nachgetragen wurde.
        $request->recordEvent(
            RequestEventType::NOTE,
            [
                'note' => 'Aus dem Rueckstand des Sammelpostfachs importiert',
                'import' => 'requests:import-mailbox',
                'mail_datum' => $this->mailDate($message)?->toDateString(),
            ],
            RequestEvent::ACTOR_SYSTEM,
        );

        if (! $this->option('no-classify')) {
            ClassifyRequest::dispatch((int) $request->id, verify: (bool) $this->option('verify'));
        }

        $this->line(sprintf('  neu       %s → #%d (Portal: %s)', $betreff, $request->id, $portal['tenant_id'] ?? 'unbekannt'));

        return 'neu';
    }

    /**
     * Protokollzeile im Eingangsprotokoll. null heisst: der Unique-Index hat
     * die Mail abgewiesen, sie war schon da.
     *
     * @param  array<string, mixed>  $extra
     */
    private function log(Message $message, string $messageId, MailOutcome $outcome, array $extra = []): ?RequestMailLog
    {
        try {
            return RequestMailLog::create($extra + [
                'message_id_hash' => RequestMailLog::hashMessageId($messageId),
                'message_id' => Str::limit($messageId, 500, ''),
                'outcome' => $outcome,
                'from_email' => MailHeaders::firstAddress($message, 'from'),
                'subject' => Str::limit((string) $message->getSubject(), 250),
                'mail_date' => $this->mailDate($message),
            ]);
        } catch (QueryException $e) {
            if (str_contains(strtolower($e->getMessage()), 'duplicate') || (string) ($e->errorInfo[1] ?? '') === '1062') {
                return null;
            }

            throw $e;
        }
    }

    /**
     * `--since` pruefen. false heisst: Eingabe unbrauchbar, der Lauf endet —
     * ein falsch gelesenes Datum importiert sonst stillschweigend das
     * ganze Postfach.
     */
    private function since(): string|null|false
    {
        $roh = $this->option('since');

        if ($roh === null || trim((string) $roh) === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $roh)->toDateString();
        } catch (Throwable) {
            $this->error("--since={$roh} ist kein lesbares Datum (erwartet z. B. 2026-09-01).");

            return false;
        }
    }

    /** Wie beim Poller: Mails ohne Message-ID brauchen einen stabilen Ersatz. */
    private function syntheticMessageId(Message $message): string
    {
        return 'synthetisch-'.hash('sha256', implode('|', [
            MailHeaders::firstAddress($message, 'from') ?? '',
            (string) $message->getSubject(),
            MailHeaders::text($message, 'date'),
            (string) strlen($message->getRawBody()),
        ]));
    }

    private function mailDate(Message $message): ?Carbon
    {
        try {
            $date = $message->getDate()?->first();

            return $date === null ? null : Carbon::parse($date);
        } catch (Throwable) {
            return null;
        }
    }
}
