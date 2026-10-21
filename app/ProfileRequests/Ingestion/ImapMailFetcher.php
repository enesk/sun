<?php

declare(strict_types=1);

namespace App\ProfileRequests\Ingestion;

use RuntimeException;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Message;

/**
 * Zugriff auf das zentrale Sammelpostfach (SUN-RQ, Ticket #5).
 *
 * Kapselt alles, was mit IMAP zu tun hat: Verbindung, Auswahl der
 * ungelesenen Mails, Markieren und Verschieben. Der Rest der Ingestion
 * sieht nur noch {@see Message}-Objekte.
 *
 * Kein IDLE, sondern Polling alle 5 Minuten. Bei dem Aufkommen (gut 20
 * Mails am Tag) ist eine stehende Verbindung mehr Betriebsrisiko als
 * Gewinn, und das Abnahmekriterium ist ohnehin "innerhalb von 5 Minuten".
 *
 * Die Mail wird erst als gelesen markiert und dann nach 'Verarbeitet'
 * verschoben, beides nachdem die Verarbeitung durch ist. Geloescht wird im
 * Postfach nie: die Rohmail liegt zwar bei uns, aber das Postfach bleibt
 * die unabhaengige zweite Spur.
 *
 * Die Zugangsdaten stehen ausschliesslich in der .env (REQUESTS_IMAP_*).
 */
class ImapMailFetcher
{
    private ?Client $client = null;

    /**
     * Ungelesene Mails des Eingangsordners, aelteste zuerst.
     *
     * @return list<Message>
     */
    public function unread(?int $limit = null): array
    {
        $limit ??= (int) config('profile-requests.mailbox.poll_batch_size', 50);

        $messages = $this->inbox()
            ->messages()
            ->whereUnseen()
            // Nicht beim Abholen markieren — gelesen ist eine Mail erst,
            // wenn sie auch verarbeitet wurde. Bricht der Lauf vorher ab,
            // holt der naechste sie wieder.
            ->leaveUnread()
            ->setFetchOrderAsc()
            ->limit($limit)
            ->get();

        /** @var list<Message> $liste */
        $liste = array_values($messages->all());

        return $liste;
    }

    /**
     * Rueckstand eines Ordners: **alle** Mails, aelteste zuerst, ohne
     * Ruecksicht auf das Gelesen-Flag (SUN-RQ #12, `requests:import-mailbox`).
     *
     * Der Poller liest nur Ungelesenes — der bestehende Rueckstand der
     * Portal-Postfaecher ist aber laengst geoeffnet und beantwortet worden.
     * Deshalb ein eigener Weg, der nichts markiert und nichts verschiebt:
     * der Import laesst das Postfach genau so zurueck, wie er es gefunden
     * hat. Doppelt eingelesen wird nichts, dafuer sorgt der
     * Message-ID-Index in `request_mail_log`.
     *
     * @param  string|null  $folder  Pfad des Ordners; null = Eingangsordner
     * @param  string|null  $since  Datum im IMAP-Sinn (ab, einschliesslich)
     * @return list<Message>
     */
    public function backlog(?string $folder = null, ?string $since = null, ?int $limit = null): array
    {
        $query = $this->folder($folder ?? (string) config('profile-requests.mailbox.imap.folder', 'INBOX'))
            ->messages()
            ->all()
            ->leaveUnread()
            ->setFetchOrderAsc();

        if ($since !== null) {
            $query->since($since);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        /** @var list<Message> $liste */
        $liste = array_values($query->get()->all());

        return $liste;
    }

    /**
     * Mail abschliessen: als gelesen markieren und wegsortieren.
     *
     * Schlaegt das Verschieben fehl (Ordner nicht anlegbar, Rechte), bleibt
     * die Mail gelesen im Eingang liegen — doppelt verarbeitet wird sie
     * trotzdem nicht, dafuer sorgt der Message-ID-Index.
     */
    public function finish(Message $message): void
    {
        $message->setFlag('Seen');

        $ziel = (string) config('profile-requests.mailbox.imap.processed_folder', 'Verarbeitet');

        if ($ziel === '') {
            return;
        }

        $this->ensureFolder($ziel);

        $message->move($ziel);
    }

    public function disconnect(): void
    {
        $this->client?->disconnect();
        $this->client = null;
    }

    /** Verbindungs- und Anmeldefehler schlagen hier durch — der Poller alarmiert danach. */
    public function client(): Client
    {
        if ($this->client !== null && $this->client->isConnected()) {
            return $this->client;
        }

        $imap = (array) config('profile-requests.mailbox.imap', []);

        foreach (['host', 'username', 'password'] as $pflicht) {
            if (blank($imap[$pflicht] ?? null)) {
                throw new RuntimeException('Sammelpostfach unvollstaendig konfiguriert: REQUESTS_IMAP_'.strtoupper($pflicht).' fehlt.');
            }
        }

        $client = (new ClientManager)->make([
            'host' => $imap['host'],
            'port' => (int) ($imap['port'] ?? 993),
            'protocol' => 'imap',
            'encryption' => $imap['encryption'] ?? 'ssl',
            'validate_cert' => (bool) ($imap['validate_cert'] ?? true),
            'username' => $imap['username'],
            'password' => $imap['password'],
            'authentication' => null,
            'timeout' => 30,
        ]);

        $client->connect();

        return $this->client = $client;
    }

    private function inbox(): Folder
    {
        return $this->folder((string) config('profile-requests.mailbox.imap.folder', 'INBOX'));
    }

    private function folder(string $pfad): Folder
    {
        $folder = $this->client()->getFolderByPath($pfad, soft_fail: true);

        if ($folder === null) {
            throw new RuntimeException("Ordner '{$pfad}' im Sammelpostfach nicht gefunden.");
        }

        return $folder;
    }

    private function ensureFolder(string $pfad): void
    {
        if ($this->client()->getFolderByPath($pfad, soft_fail: true) !== null) {
            return;
        }

        $this->client()->createFolder($pfad);
    }
}
