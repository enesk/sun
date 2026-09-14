<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Portal\City;
use Illuminate\Database\Eloquent\Builder;

/**
 * Ortsfilter der Firmensuche: "Wo?" als Ortsname oder PLZ, optional mit Umkreis.
 *
 * Der Umkreis braucht Koordinaten an cities.latitude/longitude. Fehlen sie am
 * Ausgangsort, wird ohne Umkreis gefiltert (Ortsname bzw. PLZ exakt oder als
 * Praefix) und distances() bleibt leer — die Oberflaeche zeigt dann keine
 * Entfernung und keine Karte.
 */
final class CompanyLocationSearch
{
    public const RADII = [10, 25, 50];

    public const DEFAULT_RADIUS = 25;

    private const EARTH_RADIUS_KM = 6371.0;

    /** @var array<int, float> Entfernung in km je city_id, nur mit Umkreis */
    private array $distances = [];

    private ?City $origin = null;

    private ?int $radius = null;

    public function apply(Builder $query, string $place, ?int $radius = null): void
    {
        $place = trim($place);

        if ($place === '') {
            return;
        }

        $this->origin = $this->resolveOrigin($place);

        if ($this->origin && $this->origin->latitude && $this->origin->longitude) {
            $this->radius = in_array($radius, self::RADII, true) ? $radius : self::DEFAULT_RADIUS;
            $this->distances = $this->citiesWithin((float) $this->origin->latitude, (float) $this->origin->longitude, $this->radius);

            $query->whereIn('city_id', array_keys($this->distances) ?: [0]);

            return;
        }

        $patterns = self::zipPatterns($place);

        if ($patterns !== []) {
            // PLZ der Betriebe, dazu Orte mit dieser PLZ (cities.zipcode fuehrt nur eine PLZ je Ort)
            $query->where(function (Builder $inner) use ($patterns): void {
                foreach ($patterns as $pattern) {
                    $inner->orWhere('zipcode', 'like', $pattern)
                        ->orWhereIn('city_id', City::query()->where('zipcode', 'like', $pattern)->select('id'));
                }
            });

            return;
        }

        $query->whereIn('city_id', City::query()->where('name', $place)->select('id'));
    }

    public function origin(): ?City
    {
        return $this->origin;
    }

    public function radius(): ?int
    {
        return $this->radius;
    }

    /**
     * @return array<int, float>
     */
    public function distances(): array
    {
        return $this->distances;
    }

    private function resolveOrigin(string $place): ?City
    {
        $patterns = self::zipPatterns($place);

        return City::query()
            ->when(
                $patterns !== [],
                fn (Builder $query) => $query->where('zipcode', str_replace('%', '', $patterns[0])),
                fn (Builder $query) => $query->where('name', $place),
            )
            ->orderByRaw('latitude is null')
            ->first(['id', 'name', 'zipcode', 'latitude', 'longitude']);
    }

    /**
     * Orte im Umkreis mit Entfernung. Erst ein Rechteck ueber die Koordinaten
     * (grob, per SQL), dann die genaue Entfernung per Haversine in PHP.
     *
     * @return array<int, float>
     */
    private function citiesWithin(float $lat, float $lng, int $radius): array
    {
        $latDelta = $radius / 111.0;
        $lngDelta = $radius / (111.0 * max(cos(deg2rad($lat)), 0.01));

        return City::query()
            ->whereBetween('latitude', [$lat - $latDelta, $lat + $latDelta])
            ->whereBetween('longitude', [$lng - $lngDelta, $lng + $lngDelta])
            ->get(['id', 'latitude', 'longitude'])
            ->mapWithKeys(fn (City $city): array => [
                $city->id => self::haversine($lat, $lng, (float) $city->latitude, (float) $city->longitude),
            ])
            ->filter(fn (float $km): bool => $km <= $radius)
            ->all();
    }

    private static function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * PLZ-Suchmuster aus der Eingabe: "80331", "80331 München", "D-80331".
     * Weniger als fuenf Ziffern gelten als Praefix; vier Ziffern ohne
     * fuehrende Null zusaetzlich als PLZ mit weggelassener Null ("1067" ->
     * "01067"). Kein Ziffernblock = leer, dann ist die Eingabe ein Ortsname.
     *
     * @return array<int, string> LIKE-Muster
     */
    public static function zipPatterns(string $place): array
    {
        if (! preg_match('/(?<!\d)(\d{2,5})(?!\d)/', $place, $match)) {
            return [];
        }

        $zip = $match[1];

        if (strlen($zip) === 5) {
            return [$zip];
        }

        return strlen($zip) === 4 && ! str_starts_with($zip, '0')
            ? [$zip.'%', '0'.$zip]
            : [$zip.'%'];
    }
}
