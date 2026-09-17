<?php

declare(strict_types=1);

namespace App\Services\ProfileDescriptions;

use App\Models\Portal\City;
use App\Models\Portal\Company;
use App\Models\Portal\CompanyOpeningHour;

/**
 * Faktenobjekt je Firma fuer den Schreib-Prompt.
 *
 * Nur strukturierte Daten, leere Felder fallen weg. Die bisherige
 * Beschreibung ist bewusst keine Quelle, ebenso Telefon, E-Mail und die
 * Webadresse selbst: der Text soll keine Kontaktdaten enthalten.
 */
final class ProfileFactsBuilder
{
    private const DAYS = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];

    /**
     * Relationen, die build() braucht.
     *
     * @return list<string>
     */
    public static function relations(): array
    {
        return ['city', 'categories', 'openingHours'];
    }

    /**
     * @return array<string, mixed>
     */
    public function build(Company $company): array
    {
        /** @var City|null $city */
        $city = $company->city;

        $facts = [
            'id' => $company->id,
            'name' => trim((string) $company->name),
            'strasse' => trim("{$company->street} {$company->house_no}"),
            'plz' => trim((string) ($company->zipcode ?: $city?->zipcode)),
            'ort' => trim((string) $city?->name),
            'stadtteil' => trim((string) $city?->community),
            'bundesland' => trim((string) $city?->administrative_area_level_1),
            'leistungen' => $company->categories->pluck('name')->filter()->values()->all(),
            'bewertung' => $this->rating($company),
            'oeffnungszeiten' => $this->openingHours($company),
            'webseite' => filled($company->website) ? true : null,
        ];

        return array_filter($facts, static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);
    }

    /**
     * @return array{sterne: float, anzahl: int}|null
     */
    private function rating(Company $company): ?array
    {
        $count = (int) $company->rating_count;

        if ($count < 1 || (float) $company->rating <= 0) {
            return null;
        }

        return ['sterne' => round((float) $company->rating, 1), 'anzahl' => $count];
    }

    /**
     * @return array<string, string>|null
     */
    private function openingHours(Company $company): ?array
    {
        if ($company->openingHours->isEmpty()) {
            return null;
        }

        $days = [];

        foreach ($company->openingHours->groupBy('day_of_week') as $day => $hours) {
            $label = self::DAYS[(int) $day] ?? null;

            if ($label === null) {
                continue;
            }

            $open = $hours->reject(fn (CompanyOpeningHour $hour): bool => (bool) $hour->is_closed
                || blank($hour->opens_at) || blank($hour->closes_at));

            $days[$label] = $open->isEmpty()
                ? 'geschlossen'
                : $open->map(fn (CompanyOpeningHour $hour): string => substr((string) $hour->opens_at, 0, 5).'–'.substr((string) $hour->closes_at, 0, 5))->implode(', ');
        }

        return $days === [] ? null : $days;
    }
}
