<?php

namespace App\Livewire\Filament\Dashboard;

use App\Constants\TenantConfigConstants;
use App\Services\TenantBrandingService;
use Filament\Facades\Filament;
use Filament\Forms\Components\RichEditor;
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

class LegalPages extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    public function render()
    {
        return view('livewire.filament.dashboard.legal-pages');
    }

    public function mount(): void
    {
        $tenant = Filament::getTenant();

        $this->form->fill([
            'impressum' => $tenant->getAttribute(TenantConfigConstants::IMPRESSUM),
            'datenschutz' => $tenant->getAttribute(TenantConfigConstants::DATENSCHUTZ),
            'editorial_principles' => $tenant->getAttribute(TenantConfigConstants::EDITORIAL_PRINCIPLES),
            'responsible_name' => $tenant->getAttribute(TenantConfigConstants::RESPONSIBLE_NAME),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make([
                    RichEditor::make('impressum')
                        ->label('Impressum')
                        ->helperText('Pflichtangaben gemäß § 5 TMG. Wird auf der Impressum-Seite und im Footer angezeigt.')
                        ->toolbarButtons([
                            'bold', 'italic', 'underline',
                            'h2', 'h3',
                            'bulletList', 'orderedList',
                            'link',
                        ])
                        ->columnSpanFull(),

                    self::placeholderHelp(),
                ])->heading('Impressum')
                    ->description('Pflichtangaben für Ihr Portal gemäß § 5 TMG.'),

                Section::make([
                    RichEditor::make('datenschutz')
                        ->label('Datenschutzerklärung')
                        ->helperText('Datenschutzerklärung gemäß DSGVO. Wird auf der Datenschutz-Seite und im Footer angezeigt.')
                        ->toolbarButtons([
                            'bold', 'italic', 'underline',
                            'h2', 'h3',
                            'bulletList', 'orderedList',
                            'link',
                        ])
                        ->columnSpanFull(),

                    self::placeholderHelp(),
                ])->heading('Datenschutzerklärung')
                    ->description('Datenschutzerklärung für Ihr Portal gemäß DSGVO.'),

                Section::make([
                    TextInput::make('responsible_name')
                        ->label('Redaktionell verantwortlich')
                        ->helperText('Name der verantwortlichen Person gemäß § 18 Abs. 2 MStV. Ohne Angabe wird der Portalbetreiber genannt.')
                        ->maxLength(255)
                        ->columnSpanFull(),

                    RichEditor::make('editorial_principles')
                        ->label('So arbeitet unsere Redaktion')
                        ->helperText('Wird unter /ratgeber/redaktion ausgegeben und aus der Autorenbox jedes Ratgeber-Artikels verlinkt. Leer lassen für den Standardtext.')
                        ->toolbarButtons([
                            'bold', 'italic', 'underline',
                            'h2', 'h3',
                            'bulletList', 'orderedList',
                            'link',
                        ])
                        ->columnSpanFull(),

                    self::placeholderHelp(),
                ])->heading('Redaktionsprinzipien')
                    ->description('Wie die Ratgeber auf Ihrem Portal entstehen — Ziel des Verweises aus jedem Artikel.'),
            ])
            ->statePath('data');
    }

    /**
     * Ausklappbare Referenz der Platzhalter, standardmaessig zu (#50). Im
     * Rich-Text-Editor sind die Platzhalter sonst nicht dokumentiert.
     */
    private static function placeholderHelp(): Section
    {
        $items = [];

        foreach (TenantBrandingService::LEGAL_PLACEHOLDERS as $placeholder => $description) {
            $items[] = new HtmlString(
                '<code>'.e($placeholder).'</code> — '.e($description)
            );
        }

        return Section::make([
            Text::make('Diese Platzhalter werden beim Anzeigen der Seite durch Ihre Portaldaten ersetzt. Schreiben Sie sie genau so, mit eckigen Klammern — auch in Links, etwa mailto:[BETREIBER_EMAIL]. Überschreiben Sie sie nicht mit festen Angaben, sonst veraltet der Text bei jeder Änderung Ihrer Stammdaten.')
                ->size('sm'),
            UnorderedList::make($items)
                ->size('sm'),
        ])
            ->heading('Verfügbare Platzhalter')
            ->compact()
            ->collapsible()
            ->collapsed()
            ->columnSpanFull();
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $tenant = Filament::getTenant();
        $branding = app(TenantBrandingService::class);

        // HTML sanitizen — XSS-Schutz weil Templates {!! !!} nutzen.
        // sanitizeLegalHtml() haelt dabei die Platzhalter unversehrt (#50).
        $branding->setMany($tenant, [
            TenantConfigConstants::IMPRESSUM => $branding->sanitizeLegalHtml($data['impressum'] ?? null),
            TenantConfigConstants::DATENSCHUTZ => $branding->sanitizeLegalHtml($data['datenschutz'] ?? null),
            TenantConfigConstants::EDITORIAL_PRINCIPLES => $branding->sanitizeLegalHtml($data['editorial_principles'] ?? null),
            TenantConfigConstants::RESPONSIBLE_NAME => $data['responsible_name'] ?: null,
        ]);

        Notification::make()
            ->title('Rechtliche Seiten gespeichert')
            ->success()
            ->send();
    }
}
