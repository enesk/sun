<?php

declare(strict_types=1);

namespace App\Guide\Quality;

use App\Guide\Models\Source;
use App\Guide\Support\GuidePageCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Stufe 3 des Qualitaetsgates (#11): Erreichbarkeit der externen Quellen.
 *
 * Geprueft werden alle guide_sources des Themas (sie stehen im Quellenblock
 * der Artikelseite) und jede externe URL im body_html. HEAD mit
 * guide_lint.links.timeout (5 s); Server, die HEAD ablehnen (403/405/501),
 * bekommen einen GET, bevor der Link als kaputt gilt.
 *
 * 4xx/5xx markieren die Quelle als broken (guide_sources.broken_at). Blocking
 * ist das, solange die Quelle einen aktuellen Fakt belegt oder im Text
 * verlinkt ist — liefert die Recherche fuer den Fakt eine Ersatzquelle,
 * haengt kein aktueller Fakt mehr an der kaputten URL und die Sperre
 * entfaellt. Zeitueberschreitungen sagen nichts ueber das Ziel und sind nur
 * eine Warnung. Ein erfolgreicher Check leert broken_at wieder.
 *
 * Ergebnisse je URL liegen guide_lint.links.cache_seconds im Cache, damit
 * dieselbe Behoerdenseite nicht in jedem Lauf des Tages erneut abgefragt wird.
 */
class LinkChecker
{
    public const STATUS_OK = 'ok';

    public const STATUS_BROKEN = 'broken';

    public const STATUS_UNREACHABLE = 'unreachable';

    private const CACHE_PREFIX = 'guide:linkcheck:';

    /**
     * @param  Collection<int, Source>  $sources  alle Quellen des Themas
     * @param  array<int, string>  $bodyUrls  externe Links im body_html
     * @param  array<int, int>  $factSourceIds  Quellen aktueller Fakten
     * @return list<array{url: string, source_id: ?int, status: string, code: ?int, referenced: bool, blocking: bool, message: string}>
     */
    public function check(Collection $sources, array $bodyUrls, array $factSourceIds): array
    {
        if (! (bool) config('guide_lint.links.enabled', true)) {
            return [];
        }

        $factSourceIds = array_flip($factSourceIds);
        $linked = array_flip($bodyUrls);
        $bySource = $sources->keyBy(fn (Source $source): string => (string) $source->url);
        $urls = array_values(array_unique([...$bySource->keys()->all(), ...$bodyUrls]));
        $results = [];

        foreach ($urls as $url) {
            if (preg_match('#^https?://#i', $url) !== 1) {
                continue;
            }

            $check = $this->checkOne($url);

            /** @var Source|null $source */
            $source = $bySource->get($url);
            $referenced = isset($linked[$url]) || ($source !== null && isset($factSourceIds[$source->getKey()]));

            if ($source !== null) {
                $this->record($source, $check);
            }

            $results[] = [
                'url' => $url,
                'source_id' => $source === null ? null : (int) $source->getKey(),
                'status' => $check['status'],
                'code' => $check['code'],
                'referenced' => $referenced,
                'blocking' => $check['status'] === self::STATUS_BROKEN && $referenced,
                'message' => $check['message'],
            ];
        }

        return $results;
    }

    /**
     * Anteil erreichbarer Links, 0 bis 100.
     *
     * @param  list<array{status: string}>  $results
     */
    public function score(array $results): float
    {
        if ($results === []) {
            return 100.0;
        }

        $ok = count(array_filter($results, fn (array $result): bool => $result['status'] === self::STATUS_OK));

        return round($ok / count($results) * 100, 1);
    }

    /**
     * @param  array{status: string, code: ?int, message: string}  $check
     */
    private function record(Source $source, array $check): void
    {
        if ($check['status'] === self::STATUS_UNREACHABLE) {
            $source->forceFill(['link_checked_at' => Carbon::now()])->save();

            return;
        }

        $wasBroken = $source->isBroken();

        $source->forceFill([
            'link_checked_at' => Carbon::now(),
            'link_status_code' => $check['code'],
            'broken_at' => $check['status'] === self::STATUS_BROKEN ? ($source->broken_at ?? Carbon::now()) : null,
        ])->save();

        // Die Artikelseite verlinkt kaputte Quellen nicht (#27): Wechselt der
        // Zustand, muss der Seiten-Cache neu aufgebaut werden.
        if ($wasBroken !== $source->isBroken()) {
            GuidePageCache::flush();
        }
    }

    /**
     * @return array{status: string, code: ?int, message: string}
     */
    private function checkOne(string $url): array
    {
        $seconds = max(60, (int) config('guide_lint.links.cache_seconds', 21600));

        return Cache::remember(self::CACHE_PREFIX.sha1($url), $seconds, fn (): array => $this->request($url));
    }

    /**
     * @return array{status: string, code: ?int, message: string}
     */
    private function request(string $url): array
    {
        $timeout = max(1, (int) config('guide_lint.links.timeout', 5));
        $connect = max(1, (int) config('guide_lint.links.connect_timeout', 3));
        $retryOn = array_map('intval', (array) config('guide_lint.links.retry_with_get_on', [403, 405, 501]));

        try {
            $code = Http::timeout($timeout)->connectTimeout($connect)->withUserAgent($this->userAgent())->head($url)->status();

            if (in_array($code, $retryOn, true)) {
                $code = Http::timeout($timeout)->connectTimeout($connect)->withUserAgent($this->userAgent())->get($url)->status();
            }

            if ($code >= 400) {
                return ['status' => self::STATUS_BROKEN, 'code' => $code, 'message' => "Quelle antwortet mit HTTP {$code}."];
            }

            return ['status' => self::STATUS_OK, 'code' => $code, 'message' => "HTTP {$code}"];
        } catch (Throwable $exception) {
            return [
                'status' => self::STATUS_UNREACHABLE,
                'code' => null,
                'message' => 'Quelle nicht erreichbar ('.mb_substr($exception->getMessage(), 0, 120).').',
            ];
        }
    }

    private function userAgent(): string
    {
        return str_replace(':bot_url', url('/'), (string) config('guide_lint.links.user_agent', 'SUN-RatgeberBot/1.0 (+:bot_url)'));
    }
}
