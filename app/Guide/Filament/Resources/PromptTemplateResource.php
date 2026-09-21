<?php

declare(strict_types=1);

namespace App\Guide\Filament\Resources;

use App\Filament\Content\Resources\PromptTemplates\PromptTemplateResource as ContentPromptTemplateResource;
use App\Guide\Filament\Resources\PromptTemplateResource\Pages\EditPromptTemplate;
use App\Guide\Filament\Resources\PromptTemplateResource\Pages\ListPromptTemplates;
use App\Guide\Llm\BudgetGuard;
use App\Guide\Models\Central\PromptTemplate;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Einstellungen › Prompts (#16, design/guide-dashboard.md §9.3): der
 * bestehende Prompt-Editor (#20/#43/#54), beschraenkt auf die Vorlagen des
 * Ratgebersystems (Schluessel mit Praefix "guide.").
 *
 * Formular, Vorschau und Versionierung erbt die Klasse unveraendert:
 * Speichern legt eine neue Version an, "Historie" zeigt alle Fassungen
 * desselben Schluessels (EditPromptTemplate). Die Vorlagen der alten Pipeline
 * bleiben unter /einstellungen/prompts, bis #23 sie entfernt.
 */
class PromptTemplateResource extends ContentPromptTemplateResource
{
    protected static ?string $slug = 'einstellungen/ratgeber-prompts';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('key', 'like', BudgetGuard::OPERATION_PREFIX.'%');
    }

    public static function table(Table $table): Table
    {
        $table = parent::table($table);

        // Schluessel-Filter nur mit Ratgeber-Vorlagen.
        $filter = $table->getFilter('key');

        if ($filter instanceof SelectFilter) {
            $filter->options(fn (): array => PromptTemplate::query()
                ->where('key', 'like', BudgetGuard::OPERATION_PREFIX.'%')
                ->distinct()
                ->orderBy('key')
                ->pluck('key', 'key')
                ->all());
        }

        return $table;
    }

    /**
     * @return array<string, \Filament\Resources\Pages\PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListPromptTemplates::route('/'),
            'edit' => EditPromptTemplate::route('/{record}/bearbeiten'),
        ];
    }
}
