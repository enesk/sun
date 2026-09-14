<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Gemeinsame Filter der Firmenlisten (Suche /firmen und Stadtseite
 * /staedte/{slug}): ab N Sternen, nur bewertete, jetzt geoeffnet.
 */
final class CompanyListingFilters
{
    public function apply(Builder $query, Request $request): void
    {
        if ($request->filled('min_rating')) {
            $query->where('rating', '>=', min(5, max(1, $request->integer('min_rating'))))
                ->where('rating_count', '>', 0);
        }

        if ($request->boolean('rated')) {
            $query->where('rating_count', '>', 0);
        }

        // Oeffnungszeiten stehen in Ortszeit, day_of_week ab Montag = 0
        if ($request->boolean('open_now')) {
            $now = CarbonImmutable::now('Europe/Berlin');
            $query->whereHas('openingHours', fn ($hours) => $hours
                ->where('day_of_week', $now->dayOfWeekIso - 1)
                ->where('is_closed', false)
                ->where('opens_at', '<=', $now->format('H:i:s'))
                ->where('closes_at', '>', $now->format('H:i:s')));
        }
    }
}
