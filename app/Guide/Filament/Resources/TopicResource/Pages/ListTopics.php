<?php

declare(strict_types=1);

namespace App\Guide\Filament\Resources\TopicResource\Pages;

use App\Guide\Enums\TopicStatus;
use App\Guide\Filament\Concerns\HasTopicTabs;
use App\Guide\Filament\Pages\ConfirmOutlines;
use App\Guide\Filament\Pages\ImportWizard;
use App\Guide\Filament\Resources\TopicResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reiter "Themen" (design/guide-dashboard.md §5.1).
 */
class ListTopics extends ListRecords
{
    use HasTopicTabs;

    protected static string $resource = TopicResource::class;

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
        return __('Themen');
    }

    public function getSubheading(): ?string
    {
        return TopicResource::directory()->isNetworkWide()
            ? __('Alle Portale — eine Zeile je Thema und Portal.')
            : (string) TopicResource::directory()->tenants()->first()?->name;
    }

    protected function getHeaderActions(): array
    {
        $pending = collect(TopicResource::directory()->topics())
            ->where('status', TopicStatus::OUTLINE_PENDING->value)
            ->count();

        return [
            Action::make('confirmOutlines')
                ->label(trans_choice('{0} Gliederung bestätigen|[1,*] Gliederung bestätigen (:count)', $pending, ['count' => $pending]))
                ->icon('heroicon-o-list-bullet')
                ->color($pending > 0 ? 'primary' : 'gray')
                ->url(ConfirmOutlines::getUrl()),
            Action::make('import')
                ->label(__('Themen importieren'))
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->url(ImportWizard::getUrl()),
        ];
    }
}
