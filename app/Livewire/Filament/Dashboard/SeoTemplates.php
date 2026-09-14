<?php

namespace App\Livewire\Filament\Dashboard;

use App\Constants\TenantConfigConstants;
use App\Services\Seo\CityMetaTemplates;
use App\Services\TenantBrandingService;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\UnorderedList;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Livewire\Component;

/**
 * Pflege der Stadtseiten-Templates (#10). Gespeichert als Tenant-Attribute,
 * aufgeloest von App\Services\Seo\CityMetaTemplates. Leere Felder = Vorgabe.
 */
class SeoTemplates extends Component implements HasForms
{
    use InteractsWithForms;

    /**
     * Formularfeld => Tenant-Attribut.
     */
    private const FIELDS = [
        'trade_plural' => TenantConfigConstants::SEO_TRADE_PLURAL,
        'city_title' => TenantConfigConstants::SEO_CITY_TITLE,
        'city_description' => TenantConfigConstants::SEO_CITY_DESCRIPTION,
        'city_heading' => TenantConfigConstants::SEO_CITY_HEADING,
        'city_fallback_title' => TenantConfigConstants::SEO_CITY_FALLBACK_TITLE,
        'city_fallback_description' => TenantConfigConstants::SEO_CITY_FALLBACK_DESCRIPTION,
        'city_fallback_heading' => TenantConfigConstants::SEO_CITY_FALLBACK_HEADING,
    ];

    public ?array $data = [];

    public function render()
    {
        return view('livewire.filament.dashboard.seo-templates');
    }

    public function mount(): void
    {
        $tenant = Filament::getTenant();

        $this->form->fill(array_map(
            fn (string $key): ?string => $tenant->getAttribute($key),
            self::FIELDS,
        ));
    }

    public function form(Schema $schema): Schema
    {
        $default = fn (string $field): string => CityMetaTemplates::DEFAULTS[self::FIELDS[$field]];
        $titleHint = 'Max. '.CityMetaTemplates::TITLE_MAX.' Zeichen nach dem Ersetzen, längere Titles werden am Wortende gekürzt.';
        $descriptionHint = 'Max. '.CityMetaTemplates::DESCRIPTION_MAX.' Zeichen nach dem Ersetzen, längere Beschreibungen werden am Wortende gekürzt.';

        return $schema
            ->components([
                Section::make([
                    TextInput::make('trade_plural')
                        ->label('Branchenbezeichnung (Plural)')
                        ->helperText('Wert für {trade}. Leer = Bezeichnung aus dem Theme, sonst "Firmen".')
                        ->maxLength(80),
                ])->heading('Allgemein'),

                Section::make([
                    TextInput::make('city_title')
                        ->label('Title')
                        ->placeholder($default('city_title'))
                        ->helperText($titleHint)
                        ->maxLength(255),
                    Textarea::make('city_description')
                        ->label('Meta-Description')
                        ->placeholder($default('city_description'))
                        ->helperText($descriptionHint)
                        ->rows(2)
                        ->maxLength(500),
                    TextInput::make('city_heading')
                        ->label('Überschrift (H1)')
                        ->placeholder($default('city_heading'))
                        ->maxLength(255),
                ])->heading('Stadtseite')
                    ->description('Gilt für Städte ab '.CityMetaTemplates::MIN_COUNT.' aktiven Betrieben. Ein eigener Meta-Title oder eine eigene Meta-Beschreibung an der Stadt hat Vorrang.'),

                Section::make([
                    TextInput::make('city_fallback_title')
                        ->label('Title')
                        ->placeholder($default('city_fallback_title'))
                        ->helperText($titleHint)
                        ->maxLength(255),
                    Textarea::make('city_fallback_description')
                        ->label('Meta-Description')
                        ->placeholder($default('city_fallback_description'))
                        ->helperText($descriptionHint)
                        ->rows(2)
                        ->maxLength(500),
                    TextInput::make('city_fallback_heading')
                        ->label('Überschrift (H1)')
                        ->placeholder($default('city_fallback_heading'))
                        ->maxLength(255),
                ])->heading('Stadtseite mit wenigen Betrieben')
                    ->description('Gilt für Städte mit weniger als '.CityMetaTemplates::MIN_COUNT.' aktiven Betrieben. Ohne {count}, damit kein "Die 1 besten ..." entsteht.'),

                self::placeholderHelp(),
            ])
            ->statePath('data');
    }

    private static function placeholderHelp(): Section
    {
        $items = [];

        foreach (CityMetaTemplates::PLACEHOLDERS as $placeholder => $description) {
            $items[] = new HtmlString('<code>'.e($placeholder).'</code> — '.e($description));
        }

        return Section::make([
            Text::make('Leere Felder verwenden die als Platzhaltertext angezeigte Vorgabe.')
                ->size('sm'),
            UnorderedList::make($items)
                ->size('sm'),
        ])
            ->heading('Verfügbare Platzhalter')
            ->compact()
            ->collapsible()
            ->columnSpanFull();
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $values = [];

        foreach (self::FIELDS as $field => $key) {
            $values[$key] = filled($data[$field] ?? null) ? trim((string) $data[$field]) : null;
        }

        app(TenantBrandingService::class)->setMany(Filament::getTenant(), $values);

        Notification::make()
            ->title('SEO-Templates gespeichert')
            ->success()
            ->send();
    }
}
