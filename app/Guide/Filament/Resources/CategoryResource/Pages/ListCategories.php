<?php

declare(strict_types=1);

namespace App\Guide\Filament\Resources\CategoryResource\Pages;

use App\Guide\Filament\Concerns\HasTopicTabs;
use App\Guide\Filament\Resources\CategoryResource;
use App\Guide\Services\CategoryAdminService;
use App\Guide\Services\TopicDirectory;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reiter "Kategorien" (design/guide-dashboard.md §6). Die Reihenfolge wird
 * je Portal gespeichert: Ziehen (Filament-Reorder) und ↑/↓ gehen beide ueber
 * CategoryAdminService::reorder().
 */
class ListCategories extends ListRecords
{
    use HasTopicTabs;

    protected static string $resource = CategoryResource::class;

    /**
     * Die Tabelle hat eine eigene Datenquelle (TopicDirectory). ListRecords
     * haengt sonst eine Eloquent-Abfrage an — das Modell liegt aber in der
     * Tenant-DB, und das Panel arbeitet central.
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
        return __('Kategorien');
    }

    public function getSubheading(): ?string
    {
        return app(TopicDirectory::class)->isNetworkWide()
            ? __('Alle Portale — eine Zeile je Adresse. Zum Sortieren oben ein Portal wählen.')
            : __('Reihenfolge wie im Ratgeber des Portals. Ziehen oder mit ↑/↓ verschieben.');
    }

    /**
     * Ziehen in der Tabelle (Filament-Reorder mit eigener Datenquelle).
     *
     * @param  array<int, int|string>  $order  Zeilenschluessel in neuer Reihenfolge
     */
    public function reorderTable(array $order, int|string|null $draggedRecordKey = null): void
    {
        $this->saveOrder(array_map('strval', $order));
    }

    public function move(string $key, int $direction): void
    {
        $keys = CategoryResource::rows()->sortBy('position')->keys()->values()->all();
        $index = array_search($key, $keys, true);
        $target = $index === false ? -1 : $index + $direction;

        if ($target < 0 || $target >= count($keys)) {
            return;
        }

        [$keys[$index], $keys[$target]] = [$keys[$target], $keys[$index]];

        $this->saveOrder($keys);
    }

    /**
     * @param  list<string>  $keys
     */
    private function saveOrder(array $keys): void
    {
        $directory = app(TopicDirectory::class);

        if ($directory->isNetworkWide()) {
            return;
        }

        foreach (TopicDirectory::groupKeys($keys) as $tenantId => $ids) {
            $tenant = $directory->tenant($tenantId);

            if ($tenant !== null) {
                app(CategoryAdminService::class)->reorder($tenant, $ids);
            }
        }

        $this->flushCachedTableRecords();
    }
}
