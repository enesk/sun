<?php

namespace App\Livewire\Filament\Dashboard;

use App\Filament\Dashboard\Resources\CityContents\CityContentResource;
use App\Models\Portal\City;
use App\Models\Portal\CityContent;
use App\Models\Portal\CityContentTemplate;
use App\Services\Content\CityContentResolver;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\UnorderedList;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Livewire\Component;

/**
 * Pflege der Tenant-Vorlage fuer Introtext und FAQ der Stadtseiten (#11).
 * Gespeichert in city_content_templates (eine Zeile je Tenant-Datenbank),
 * aufgeloest von App\Services\Content\CityContentResolver.
 */
class CityContentTemplates extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    public function render()
    {
        return view('livewire.filament.dashboard.city-content-templates');
    }

    public function mount(): void
    {
        $template = CityContentTemplate::current();

        $this->form->fill([
            'intro_template' => $template?->intro_template,
            'faq_templates' => $template?->faq_templates ?? [],
            'preview_city_id' => $this->defaultPreviewCityId(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make([
                    Textarea::make('intro_template')
                        ->label('Einleitung')
                        ->helperText('Klartext mit Platzhaltern, Absätze durch Leerzeile. Sätze mit {districts} entfallen bei Städten ohne gepflegte Stadtteile — {districts} deshalb in einen eigenen Satz setzen.')
                        ->rows(6)
                        ->maxLength(5000)
                        ->live(onBlur: true),
                ])->heading('Einleitung')
                    ->description('Gilt für jede Stadt ohne eigene Einleitung unter "Stadtinhalte".'),

                Section::make([
                    Repeater::make('faq_templates')
                        ->hiddenLabel()
                        ->schema(CityContentResource::faqFields())
                        ->addActionLabel('Frage hinzufügen')
                        ->reorderable()
                        ->collapsible()
                        ->defaultItems(0)
                        ->live(onBlur: true),
                ])->heading('FAQ')
                    ->description('Gilt für jede Stadt ohne eigene FAQ. Antworten als Klartext, sie werden 1:1 ins FAQPage-Schema übernommen. Eine Frage mit {districts} entfällt ohne Stadtteile ganz.'),

                self::placeholderHelp(),

                Section::make([
                    Select::make('preview_city_id')
                        ->label('Beispielstadt')
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => City::query()
                            ->named()
                            ->where('name', 'like', "{$search}%")
                            ->orderBy('name')
                            ->limit(50)
                            ->pluck('name', 'id')
                            ->all())
                        ->getOptionLabelUsing(fn ($value): ?string => City::query()->find($value)?->name)
                        ->live(),
                    View::make('filament.dashboard.partials.city-content-preview')
                        ->viewData(fn (Get $get): array => self::previewData($get)),
                ])->heading('Vorschau')
                    ->description('Aufgelöst mit dem ungespeicherten Formularstand und den gepflegten Stadtteilen der Beispielstadt. Eigene Einleitung oder FAQ der Stadt bleiben hier unberücksichtigt.'),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $template = CityContentTemplate::current() ?? new CityContentTemplate;
        $template->fill([
            'intro_template' => filled($data['intro_template'] ?? null) ? trim(strip_tags((string) $data['intro_template'])) : null,
            'faq_templates' => CityContentResolver::normalizeFaqs($data['faq_templates'] ?? null) ?: null,
        ])->save();

        Notification::make()
            ->title('Stadtinhalte-Vorlage gespeichert')
            ->success()
            ->send();
    }

    /**
     * @return array{city: ?City, content: ?array<string, mixed>}
     */
    private static function previewData(Get $get): array
    {
        $city = filled($get('preview_city_id')) ? City::query()->with('cityContent')->find($get('preview_city_id')) : null;

        if ($city === null) {
            return ['city' => null, 'content' => null];
        }

        $template = new CityContentTemplate([
            'intro_template' => $get('intro_template'),
            'faq_templates' => array_values((array) $get('faq_templates')),
        ]);

        // Nur die Stadtteile der Stadt, damit die Vorschau die Vorlage zeigt.
        $districts = new CityContent([
            'districts' => $city->cityContent?->getAttribute('is_published') ? $city->cityContent->getAttribute('districts') : null,
        ]);

        return [
            'city' => $city,
            'content' => app(CityContentResolver::class)->resolve($city, $districts, $template),
        ];
    }

    private static function placeholderHelp(): Section
    {
        $items = [];

        foreach (CityContentResolver::PLACEHOLDERS as $placeholder => $description) {
            $items[] = new HtmlString('<code>'.e($placeholder).'</code> — '.e($description));
        }

        return Section::make([
            Text::make('Platzhalter gelten nur in der Vorlage, nicht in stadtspezifischen Inhalten.')
                ->size('sm'),
            UnorderedList::make($items)
                ->size('sm'),
        ])
            ->heading('Verfügbare Platzhalter')
            ->compact()
            ->collapsible()
            ->columnSpanFull();
    }

    /**
     * Vorbelegung: Stadt mit gepflegten Stadtteilen, sonst die mit den meisten Betrieben.
     */
    private function defaultPreviewCityId(): ?int
    {
        $withDistricts = CityContent::query()->whereNotNull('districts')->where('is_published', true)->value('city_id');

        if ($withDistricts !== null) {
            return (int) $withDistricts;
        }

        $id = City::query()->named()->withCount('companies')->orderByDesc('companies_count')->value('id');

        return $id !== null ? (int) $id : null;
    }
}
