<?php

declare(strict_types=1);

namespace App\ProfileRequests\Ingestion;

use App\ProfileRequests\Support\InternalRecipients;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Wacht ueber den Poller (SUN-RQ, Ticket #5).
 *
 * Ein einzelner Fehlschlag ist Alltag — ein Postfach ist mal kurz nicht da,
 * eine Verbindung bricht. Erst `failure_threshold` Fehlschlaege in Folge
 * (Standard 3, also eine Viertelstunde ohne Abruf) sind ein Ausfall und
 * loesen eine Mail aus. Danach ist Ruhe, bis ein Lauf wieder durchgeht:
 * eine Alarmmail alle 5 Minuten waere nach einer Stunde nur noch Rauschen.
 *
 * Der Zaehler steht im Cache, Store aus
 * `profile-requests.ingestion.alert.cache_store` — Standard `file`, weil
 * der Standardstore im Tenant-Kontext auf eine Datenbank ohne cache-Tabelle
 * zeigen kann.
 */
class PollerHealth
{
    private const COUNTER_KEY = 'profile-requests:ingestion:failures';

    private const ALERTED_KEY = 'profile-requests:ingestion:alerted';

    /** Zaehler zuruecksetzen; meldet true, wenn er vorher stand. */
    public function recordSuccess(): bool
    {
        $warFehlerhaft = (int) $this->cache()->get(self::COUNTER_KEY, 0) > 0;

        $this->cache()->forget(self::COUNTER_KEY);
        $this->cache()->forget(self::ALERTED_KEY);

        return $warFehlerhaft;
    }

    /**
     * Fehlschlag verbuchen und bei Erreichen der Schwelle alarmieren.
     *
     * @return int Fehlschlaege in Folge
     */
    public function recordFailure(Throwable $fehler): int
    {
        $anzahl = (int) $this->cache()->get(self::COUNTER_KEY, 0) + 1;

        // Eine Woche Haltbarkeit: lange genug, dass der Zaehler ueber eine
        // laengere Stoerung nicht von selbst vergisst.
        $this->cache()->put(self::COUNTER_KEY, $anzahl, now()->addWeek());

        Log::error('profile-requests: Pollerlauf fehlgeschlagen', [
            'versuche_in_folge' => $anzahl,
            'exception' => $fehler->getMessage(),
        ]);

        $schwelle = max(1, (int) config('profile-requests.ingestion.alert.failure_threshold', 3));

        if ($anzahl >= $schwelle && ! $this->cache()->get(self::ALERTED_KEY, false)) {
            $this->alert($anzahl, $fehler);
            $this->cache()->put(self::ALERTED_KEY, true, now()->addWeek());
        }

        return $anzahl;
    }

    private function alert(int $anzahl, Throwable $fehler): void
    {
        $empfaenger = $this->recipients();

        if ($empfaenger === []) {
            Log::warning('profile-requests: Alarm ohne Empfaenger — REQUESTS_ALERT_RECIPIENTS setzen.');

            return;
        }

        $postfach = (string) config('profile-requests.mailbox.address', '');

        $text = implode("\n", [
            'Der Abruf des Anfragen-Postfachs schlaegt fehl.',
            '',
            "Postfach:            {$postfach}",
            'Host:                '.(string) config('profile-requests.mailbox.imap.host'),
            "Fehlschlaege in Folge: {$anzahl}",
            'Letzter Fehler:      '.$fehler->getMessage(),
            '',
            'Solange der Abruf steht, bleiben eingehende Anfragen im Postfach liegen —',
            'verloren geht nichts, der naechste erfolgreiche Lauf holt sie nach.',
            '',
            'Naechster Schritt: Zugangsdaten (REQUESTS_IMAP_*) und Erreichbarkeit des',
            'Postfachs pruefen, danach `php artisan requests:fetch-mail` von Hand starten.',
        ]);

        try {
            Mail::raw($text, function ($mail) use ($empfaenger): void {
                $mail->to($empfaenger)->subject('Anfragen-Postfach: Abruf schlaegt fehl');
            });
        } catch (Throwable $e) {
            Log::error('profile-requests: Alarmmail nicht versendbar', ['exception' => $e->getMessage()]);
        }
    }

    /**
     * Empfaenger: Komma-Liste aus der .env, sonst alle nicht gesperrten
     * Administratoren — so erreicht der Alarm auch ohne gepflegte Liste
     * jemanden.
     *
     * @return list<string>
     */
    private function recipients(): array
    {
        return InternalRecipients::from(config('profile-requests.ingestion.alert.recipients'));
    }

    private function cache(): Repository
    {
        $store = config('profile-requests.ingestion.alert.cache_store');

        return filled($store) ? Cache::store((string) $store) : Cache::store();
    }
}
