<?php

declare(strict_types=1);

namespace App\Guide\Filament\Resources\ReviewRunResource\Pages;

use App\Guide\Filament\Resources\ReviewRunResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Warteschlange der Pruefung (design/guide-dashboard.md §8.1), aelteste zuerst.
 */
class ListReviewRuns extends ListRecords
{
    protected static string $resource = ReviewRunResource::class;

    /**
     * Eigene Datenquelle (RunOverviewService) statt Eloquent — die Laeufe
     * liegen in den Tenant-DBs, das Panel arbeitet central.
     */
    protected function makeTable(): Table
    {
        $table = $this->makeBaseTable()
            ->modelLabel($this->getModelLabel() ?? static::getResource()::getModelLabel())
            ->pluralModelLabel($this->getPluralModelLabel() ?? static::getResource()::getPluralModelLabel());

        static::getResource()::configureTable($table);

        return $table;
    }

    protected function getTableQuery(): ?Builder
    {
        return null;
    }

    public function getTitle(): string|Htmlable
    {
        return __('Prüfung');
    }

    public function getSubheading(): ?string
    {
        $count = count(ReviewRunResource::queue());

        return $count > 0
            ? trans_choice('{1} Ein Lauf wartet auf Ihre Entscheidung.|[2,*] :count Läufe warten auf Ihre Entscheidung.', $count, ['count' => $count])
            : null;
    }
}
