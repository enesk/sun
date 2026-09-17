<?php

namespace App\Services\Premium;

use App\Constants\CompanyEventType;
use App\Jobs\Premium\RecordCompanyEvents;
use App\Models\Portal\CompanyEvent;
use Illuminate\Http\Request;

/**
 * Einziger Eingang fuer Statistik-Events (#15).
 *
 * Prueft Bot und Sitzung und reiht die Events als Job ein. Im Web-Request
 * geschieht das erst nach dem Senden der Antwort (terminating), weil die
 * Queue auch auf dem database-Treiber laufen kann; der Request selbst
 * schreibt nichts in die Datenbank. Gespeichert werden weder IP noch
 * User-Agent, sondern nur ein Sitzungs-Hash, der sich taeglich aendert.
 */
class CompanyStatsRecorder
{
    public function profileView(int $companyId, ?int $cityId, Request $request): void
    {
        $this->record(CompanyEventType::PROFILE_VIEW, [$companyId], $request, $cityId, CompanyEvent::SOURCE_PROFILE);
    }

    /**
     * Ein Batch je Seitenaufruf; der Job teilt ihn je Karte auf.
     *
     * @param  iterable<int|string>  $companyIds
     */
    public function listImpressions(iterable $companyIds, Request $request, ?int $cityId, string $source): void
    {
        $this->record(CompanyEventType::LIST_IMPRESSION, $companyIds, $request, $cityId, $source);
    }

    public function click(CompanyEventType $type, int $companyId, Request $request, ?string $source = null): void
    {
        $this->record($type, [$companyId], $request, null, $source);
    }

    /**
     * Fuer das Bewertungs-Widget (#12). Die Widget-Route laeuft ohne Sitzung,
     * dedupliziert wird dort ueber anonymousHash().
     */
    public function widgetView(int $companyId, Request $request): void
    {
        $this->record(CompanyEventType::WIDGET_VIEW, [$companyId], $request, null, CompanyEvent::SOURCE_WIDGET, $this->anonymousHash($request));
    }

    /**
     * Antwort des Betriebs auf eine Bewertung (#12).
     */
    public function reviewReply(int $companyId, Request $request): void
    {
        $this->record(CompanyEventType::REVIEW_REPLY, [$companyId], $request, null, CompanyEvent::SOURCE_PROFILE);
    }

    public function isBot(Request $request): bool
    {
        $userAgent = strtolower((string) $request->userAgent());

        if ($userAgent === '') {
            return true;
        }

        foreach ((array) config('premium.stats.bot_user_agents', []) as $pattern) {
            if (str_contains($userAgent, (string) $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * HMAC aus Session-ID und Tagesschluessel; ohne Sitzung null.
     */
    public function sessionHash(Request $request): ?string
    {
        if (! $request->hasSession()) {
            return null;
        }

        $sessionId = (string) $request->session()->getId();

        if ($sessionId === '') {
            return null;
        }

        return hash_hmac('sha256', $sessionId, $this->dayKey());
    }

    /**
     * Fuer Aufrufe ohne Sitzung: HMAC aus IP und User-Agent mit Tagesschluessel.
     * Gespeichert wird nur der Hash, der sich taeglich aendert.
     */
    public function anonymousHash(Request $request): string
    {
        return hash_hmac('sha256', "{$request->ip()}|{$request->userAgent()}", $this->dayKey());
    }

    private function dayKey(): string
    {
        return hash_hmac('sha256', 'company-stats|'.now()->toDateString(), (string) config('app.key'));
    }

    /**
     * @param  iterable<int|string>  $companyIds
     */
    private function record(CompanyEventType $type, iterable $companyIds, Request $request, ?int $cityId, ?string $source, ?string $fallbackHash = null): void
    {
        $ids = [];
        foreach ($companyIds as $id) {
            if ((int) $id > 0) {
                $ids[] = (int) $id;
            }
        }

        if ($ids === [] || $this->isBot($request)) {
            return;
        }

        $sessionHash = $this->sessionHash($request) ?? $fallbackHash;

        if ($sessionHash === null) {
            return;
        }

        $job = new RecordCompanyEvents(
            array_values(array_unique($ids)),
            $type->value,
            now()->toIso8601String(),
            $sessionHash,
            $cityId,
            $source,
        );
        $job->onQueue((string) config('premium.stats.queue', 'default'));

        if (app()->runningInConsole()) {
            dispatch($job);

            return;
        }

        app()->terminating(fn () => dispatch($job));
    }
}
