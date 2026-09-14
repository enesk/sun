<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Portal\City;
use App\Support\ForeignCompanyDetector;
use App\Support\GermanState;

/**
 * Bundesland eines deutschen Orts aus dem Bestand des Tenants (#18).
 *
 * Google liefert zu einem Place das Bundesland seiner eigenen Anschrift. Hing
 * der Import einen US-Betrieb mit gleicher PLZ an eine deutsche Stadt
 * (27616 Beverstedt / Raleigh), wurde die Stadt mit "North Carolina" angelegt.
 * Verlaesslich ist dagegen die PLZ: die uebrigen Orte des Tenants mit derselben
 * PLZ bzw. demselben dreistelligen PLZ-Bereich tragen fast immer ein gueltiges
 * Bundesland. Entschieden wird nur bei klarer Mehrheit, sonst null — Leitbereiche
 * wie 897 (Bayern/Baden-Württemberg) bleiben lieber offen als falsch.
 *
 * Arbeitet auf der Tenant-Verbindung, muss also im Tenant-Kontext laufen.
 */
class CityStateResolver
{
    private const EXACT_MIN_SHARE = 0.8;

    private const PREFIX_MIN_VOTES = 3;

    private const PREFIX_MIN_SHARE = 0.9;

    /**
     * Bundesland fuer eine neu anzulegende deutsche Stadt: das gelieferte, wenn
     * es ein Bundesland ist, sonst das aus der PLZ abgeleitete, sonst null.
     */
    public function forGermanCity(?string $state, ?string $zipcode): ?string
    {
        return GermanState::canonical($state) ?? $this->fromZipcode($zipcode);
    }

    public function fromZipcode(?string $zipcode, ?int $exceptCityId = null): ?string
    {
        $zipcode = trim((string) $zipcode);

        if (! ForeignCompanyDetector::isGermanZipcode($zipcode)) {
            return null;
        }

        return $this->majority($this->votes($zipcode, false, $exceptCityId), 1, self::EXACT_MIN_SHARE)
            ?? $this->majority($this->votes(substr($zipcode, 0, 3), true, $exceptCityId), self::PREFIX_MIN_VOTES, self::PREFIX_MIN_SHARE);
    }

    /**
     * @return array<string, int> Bundesland => Anzahl Orte
     */
    private function votes(string $zipcode, bool $prefix, ?int $exceptCityId): array
    {
        return City::query()
            ->when($prefix, fn ($query) => $query->where('zipcode', 'like', "{$zipcode}%"), fn ($query) => $query->where('zipcode', $zipcode))
            ->whereIn('administrative_area_level_1', GermanState::NAMES)
            ->when($exceptCityId !== null, fn ($query) => $query->whereKeyNot($exceptCityId))
            ->selectRaw('administrative_area_level_1 as state, COUNT(*) as votes')
            ->groupBy('administrative_area_level_1')
            ->pluck('votes', 'state')
            ->map(fn ($votes): int => (int) $votes)
            ->all();
    }

    /**
     * @param  array<string, int>  $votes
     */
    private function majority(array $votes, int $minVotes, float $minShare): ?string
    {
        $total = array_sum($votes);

        if ($total < $minVotes) {
            return null;
        }

        arsort($votes);
        $state = (string) array_key_first($votes);

        return $minShare <= $votes[$state] / $total ? $state : null;
    }
}
