<?php

declare(strict_types=1);

namespace App\Themes\SunV2;

use App\Models\Portal\Company;
use App\Models\Portal\CompanyOpeningHour;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Oeffnungsstatus einer Firmenkarte: "Jetzt geöffnet · bis 17:00" oder
 * "Geschlossen · öffnet Mo 7:30".
 *
 * Gerechnet wird in Europe/Berlin, nicht in der App-Zeitzone — die steht per
 * Vorgabe auf UTC, und dann waere jeder Betrieb eine bis zwei Stunden
 * zu frueh geoeffnet. day_of_week zaehlt ab Montag = 0.
 */
final class OpeningStatus
{
    private const TIMEZONE = 'Europe/Berlin';

    private const DAY_SHORT = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];

    public function __construct(
        public readonly bool $open,
        public readonly string $label,
    ) {}

    /**
     * null, wenn der Betrieb keine auswertbaren Oeffnungszeiten hat.
     */
    public static function for(Company $company, ?CarbonImmutable $now = null): ?self
    {
        /** @var Collection<int, CompanyOpeningHour> $hours */
        $hours = $company->openingHours
            ->filter(fn (CompanyOpeningHour $hour): bool => ! $hour->is_closed && $hour->opens_at && $hour->closes_at)
            ->keyBy('day_of_week');

        if ($hours->isEmpty()) {
            return null;
        }

        $now ??= CarbonImmutable::now(self::TIMEZONE);
        $today = $now->dayOfWeekIso - 1;
        $time = $now->format('H:i:s');

        $todayHours = $hours->get($today);

        if ($todayHours && $time >= $todayHours->opens_at && $time < $todayHours->closes_at) {
            return new self(true, __('portal.layout.opening.open_until', ['zeit' => self::clock($todayHours->closes_at)]));
        }

        if ($todayHours && $time < $todayHours->opens_at) {
            return new self(false, __('portal.layout.opening.opens_today', ['zeit' => self::clock($todayHours->opens_at)]));
        }

        for ($offset = 1; $offset <= 7; $offset++) {
            $day = ($today + $offset) % 7;
            $next = $hours->get($day);

            if ($next) {
                return new self(false, __('portal.layout.opening.opens_on', ['tag' => self::DAY_SHORT[$day], 'zeit' => self::clock($next->opens_at)]));
            }
        }

        return null;
    }

    /**
     * "07:30:00" -> "7:30", "17:00:00" -> "17:00" (Schreibweise der Vorlage).
     */
    private static function clock(string $time): string
    {
        [$hour, $minute] = explode(':', $time);

        return (int) $hour.':'.$minute;
    }
}
