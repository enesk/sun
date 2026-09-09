<?php

declare(strict_types=1);

namespace App\Content\Sources\Connectors;

use App\Content\Enums\SourceFrequency;
use App\Content\Models\SeasonalTopic;
use App\Content\Sources\AbstractHttpConnector;
use App\Content\Sources\SourceItemDto;
use App\Content\Sources\Support\StateCatalog;
use App\Content\Sources\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Saisonkalender und Schulferien als Themenanlass (#11), taeglich.
 *
 * Zwei Eingaben, ein Ergebnis:
 *
 *   seasonal_topics  Der vom Texter (#13) gepflegte Kalender: welches Thema
 *                    in welchen Monaten laeuft und wie viel Vorlauf es
 *                    braucht.
 *   ferien-api.de    Schulferien und Feiertage je Bundesland. Ferien
 *                    verschieben Nachfrage (Umzug, Renovierung, Werkstatt)
 *                    und sind der einzige verlaessliche regionale Termin.
 *
 * Erzeugt werden Rohsignale fuer die naechsten 14 Tage. Der seasonal_score
 * steht in payload_json und spiegelt sich in signal_strength: 1.0, solange
 * das Fenster laeuft, 0.5 in den 14 Tagen davor. source_items hat keine
 * eigene Spalte dafuer — das Scoring (#12) uebernimmt den Wert von hier nach
 * topic_candidates.seasonal_score.
 */
class SeasonalCalendarConnector extends AbstractHttpConnector
{
    /** Vorlauffenster in Tagen, gemaess Abnahmekriterium. */
    private const LOOKAHEAD_DAYS = 14;

    public function key(): string
    {
        return 'seasonal_calendar';
    }

    public function schedule(): string
    {
        return SourceFrequency::DAILY->value;
    }

    public function fetch(TenantContext $context): Collection
    {
        $today = CarbonImmutable::today();
        $until = $today->addDays(self::LOOKAHEAD_DAYS);

        return $this->topicItems($context, $today, $until)
            ->merge($this->holidayItems($context, $today, $until));
    }

    /*
    |--------------------------------------------------------------------------
    | Saisonkalender
    |--------------------------------------------------------------------------
    */

    /**
     * @return Collection<int, SourceItemDto>
     */
    private function topicItems(TenantContext $context, CarbonImmutable $today, CarbonImmutable $until): Collection
    {
        $items = collect();

        foreach (SeasonalTopic::query()->active()->get() as $topic) {
            $score = $this->seasonalScore($topic, $today, $until);

            if ($score === null) {
                continue;
            }

            if (! $context->allowsRegionScope((string) $topic->region_scope)) {
                continue;
            }

            $window = $this->windowLabel($topic);

            $items->push(new SourceItemDto(
                type: 'seasonal',
                title: (string) $topic->title,
                url: null,
                snippet: "Saisonthema {$window}: {$topic->primary_keyword}. Vorlauf {$topic->lead_time_days} Tage.",
                regionScope: (string) $topic->region_scope,
                regionCode: $topic->region_code,
                keywords: array_merge([(string) $topic->primary_keyword], (array) ($topic->keywords_json ?? [])),
                signalStrength: $score,
                publishedAt: $today,
                raw: [
                    'seasonal_topic_id' => (int) $topic->getKey(),
                    'seasonal_score' => $score,
                    'window' => $window,
                    'lead_time_days' => (int) $topic->lead_time_days,
                    'weight' => (float) $topic->weight,
                ],
                externalId: 'seasonal:'.$topic->getKey().':'.$today->format('Y-m'),
                // Ein Saisonthema soll je Monat hoechstens einmal auftauchen,
                // nicht an jedem der 14 Tage neu.
                fingerprintSeed: 'seasonal|'.$topic->getKey().'|'.$today->format('Y-m'),
            ));
        }

        return $items;
    }

    /**
     * 1.0 innerhalb des Saisonfensters, 0.5 in den 14 Tagen davor, sonst null
     * (das Thema ist heute kein Anlass).
     */
    private function seasonalScore(SeasonalTopic $topic, CarbonImmutable $today, CarbonImmutable $until): ?float
    {
        if ($this->inWindow($topic, (int) $today->format('n'))) {
            return 1.0;
        }

        // Steht der Fensterbeginn innerhalb der naechsten 14 Tage bevor?
        for ($day = 1; $day <= self::LOOKAHEAD_DAYS; $day++) {
            $date = $today->addDays($day);

            if ($date->isAfter($until)) {
                break;
            }

            if ($date->day === 1 && $this->inWindow($topic, (int) $date->format('n'))) {
                return 0.5;
            }
        }

        return null;
    }

    /**
     * Fenster ueber den Jahreswechsel (11 bis 2) zaehlen mit.
     */
    private function inWindow(SeasonalTopic $topic, int $month): bool
    {
        $start = (int) $topic->start_month;
        $end = (int) $topic->end_month;

        return $start <= $end
            ? $month >= $start && $month <= $end
            : $month >= $start || $month <= $end;
    }

    private function windowLabel(SeasonalTopic $topic): string
    {
        $months = [1 => 'Januar', 'Februar', 'Maerz', 'April', 'Mai', 'Juni',
            'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];

        return ($months[(int) $topic->start_month] ?? '?').' bis '.($months[(int) $topic->end_month] ?? '?');
    }

    /*
    |--------------------------------------------------------------------------
    | Schulferien
    |--------------------------------------------------------------------------
    */

    /**
     * @return Collection<int, SourceItemDto>
     */
    private function holidayItems(TenantContext $context, CarbonImmutable $today, CarbonImmutable $until): Collection
    {
        if (! (bool) $this->option('holidays_enabled', true) || ! $context->allowsRegionScope('state')) {
            return collect();
        }

        $items = collect();

        foreach ($context->preferredStates() as $iso) {
            if (! StateCatalog::exists((string) $iso)) {
                continue;
            }

            foreach ($this->holidays((string) $iso, $today, $context) as $holiday) {
                $item = $this->toHolidayItem($holiday, (string) $iso, $today, $until);

                if ($item !== null) {
                    $items->push($item);
                }
            }
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $holiday
     */
    private function toHolidayItem(array $holiday, string $iso, CarbonImmutable $today, CarbonImmutable $until): ?SourceItemDto
    {
        $start = $this->parse($holiday['start'] ?? null);
        $end = $this->parse($holiday['end'] ?? null) ?? $start;
        $name = trim((string) ($holiday['name'] ?? ''));

        if ($start === null || $name === '') {
            return null;
        }

        // Alles, was komplett hinter dem Vorlauffenster oder schon vorbei ist.
        if ($start->isAfter($until) || ($end !== null && $end->isBefore($today))) {
            return null;
        }

        $score = $start->lessThanOrEqualTo($today) ? 1.0 : 0.5;
        $stateName = StateCatalog::name($iso) ?? $iso;
        $title = ucfirst($name)." in {$stateName}";

        return new SourceItemDto(
            type: 'seasonal',
            title: $title,
            url: null,
            snippet: "{$title}: {$start->format('d.m.Y')} bis ".($end?->format('d.m.Y') ?? $start->format('d.m.Y')).'.',
            regionScope: 'state',
            regionCode: $iso,
            keywords: [mb_strtolower($name), mb_strtolower($stateName)],
            signalStrength: $score,
            publishedAt: $today,
            raw: [
                'seasonal_score' => $score,
                'holiday_start' => $start->toDateString(),
                'holiday_end' => $end?->toDateString(),
                'source_name' => 'ferien-api.de',
            ],
            externalId: 'ferien:'.$iso.':'.$start->toDateString(),
            fingerprintSeed: 'ferien|'.$iso.'|'.mb_strtolower($name).'|'.$start->toDateString(),
        );
    }

    /**
     * Ferien eines Landes fuer das laufende und das naechste Jahr.
     *
     * Die API ist ein Freiwilligenprojekt und faellt gelegentlich aus. Das
     * darf den Connector nicht scheitern lassen: der Saisonkalender aus der
     * Datenbank ist der wichtigere Teil, die Ferien sind Zugabe.
     *
     * @return array<int, array<string, mixed>>
     */
    private function holidays(string $iso, CarbonImmutable $today, TenantContext $context): array
    {
        $base = rtrim((string) $this->option('holidays_url', 'https://ferien-api.de/api/v1/holidays'), '/');
        $short = StateCatalog::shortCode($iso);
        $years = [$today->year];

        // Am Jahresende liegt das naechste Fenster bereits im Folgejahr.
        if ($today->addDays(self::LOOKAHEAD_DAYS)->year !== $today->year) {
            $years[] = $today->year + 1;
        }

        $holidays = [];

        foreach ($years as $year) {
            try {
                $response = $this->json("{$base}/{$short}/{$year}", $context);
                $payload = $response->json();

                if (is_array($payload)) {
                    $holidays = array_merge($holidays, $payload);
                }
            } catch (Throwable $exception) {
                Log::warning('Ferientermine nicht abrufbar.', [
                    'connector' => $this->key(),
                    'state' => $short,
                    'year' => $year,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        return $holidays;
    }

    private function parse(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    private function option(string $key, mixed $default = null): mixed
    {
        return config("content.sources.seasonal_calendar.{$key}", $default);
    }
}
