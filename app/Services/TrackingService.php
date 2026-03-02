<?php

namespace App\Services;

use App\Models\Portal\TrackingEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TrackingService
{
    /**
     * Bekannte Bot-Pattern im User-Agent.
     * Wir wollen keine Crawler/Bots in der Statistik.
     */
    private const BOT_PATTERNS = [
        'bot', 'crawl', 'spider', 'slurp', 'mediapartners',
        'facebookexternalhit', 'linkedinbot', 'twitterbot',
        'whatsapp', 'telegram', 'preview', 'headless',
        'lighthouse', 'pagespeed', 'gtmetrix', 'pingdom',
        'uptimerobot', 'python-requests', 'curl/', 'wget/',
        'go-http-client', 'java/', 'php/', 'ruby/',
    ];

    /**
     * Profilaufruf tracken.
     */
    public function trackPageView(int $companyId, Request $request): void
    {
        if ($this->isBot($request)) {
            return;
        }

        $this->insertEvent($companyId, TrackingEvent::TYPE_PAGE_VIEW, $request);
    }

    /**
     * Kontaktklick tracken (Telefon, E-Mail, Website, Maps).
     */
    public function trackContactClick(int $companyId, string $contactType, Request $request): void
    {
        if ($this->isBot($request)) {
            return;
        }

        $allowedTypes = [
            TrackingEvent::CONTACT_PHONE,
            TrackingEvent::CONTACT_EMAIL,
            TrackingEvent::CONTACT_WEBSITE,
            TrackingEvent::CONTACT_MAP,
        ];

        if (! in_array($contactType, $allowedTypes, true)) {
            return;
        }

        $this->insertEvent($companyId, TrackingEvent::TYPE_CONTACT_CLICK, $request, [
            'contact_type' => $contactType,
        ]);
    }

    /**
     * Suchimpression tracken — eine Firma erschien in den Suchergebnissen.
     */
    public function trackSearchImpressions(array $companyIds, ?string $searchQuery, Request $request): void
    {
        if ($this->isBot($request) || empty($companyIds)) {
            return;
        }

        $now = now();
        $ip = $this->anonymizeIp($request->ip());
        $userAgent = $this->truncate($request->userAgent(), 500);
        $userId = auth()->id();

        $rows = [];
        foreach ($companyIds as $companyId) {
            $rows[] = [
                'company_id' => $companyId,
                'event_type' => TrackingEvent::TYPE_SEARCH_IMPRESSION,
                'search_query' => $this->truncate($searchQuery, 255),
                'user_id' => $userId,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'created_at' => $now,
            ];
        }

        // Bulk-Insert statt N Einzel-Inserts
        DB::connection('tenant')->table('tracking_events')->insert($rows);
    }

    /**
     * Bot-Erkennung per User-Agent.
     */
    private function isBot(Request $request): bool
    {
        $ua = strtolower($request->userAgent() ?? '');

        if (empty($ua)) {
            return true;
        }

        foreach (self::BOT_PATTERNS as $pattern) {
            if (str_contains($ua, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Event-Insert mit IP-Anonymisierung (DSGVO).
     */
    private function insertEvent(int $companyId, string $eventType, Request $request, array $extra = []): void
    {
        TrackingEvent::create(array_merge([
            'company_id' => $companyId,
            'event_type' => $eventType,
            'user_id' => auth()->id(),
            'referrer' => $this->truncate($request->headers->get('referer'), 500),
            'ip_address' => $this->anonymizeIp($request->ip()),
            'user_agent' => $this->truncate($request->userAgent(), 500),
            'created_at' => now(),
        ], $extra));
    }

    /**
     * IP-Adresse anonymisieren: letztes Oktett auf 0 setzen (DSGVO).
     * IPv4: 192.168.1.42 → 192.168.1.0
     * IPv6: letzte 80 Bits nullen.
     */
    private function anonymizeIp(?string $ip): ?string
    {
        if (! $ip) {
            return null;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return preg_replace('/\.\d+$/', '.0', $ip);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = inet_pton($ip);
            // Letzte 10 Bytes (80 Bits) nullen
            for ($i = 6; $i < 16; $i++) {
                $packed[$i] = "\0";
            }
            return inet_ntop($packed);
        }

        return null;
    }

    private function truncate(?string $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_substr($value, 0, $max);
    }
}
