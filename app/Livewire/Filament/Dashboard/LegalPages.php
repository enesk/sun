<?php

namespace App\Livewire\Filament\Dashboard;

use App\Constants\TenantConfigConstants;
use App\Services\TenantBrandingService;
use Filament\Facades\Filament;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
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
                ])->heading('Datenschutzerklärung')
                    ->description('Datenschutzerklärung für Ihr Portal gemäß DSGVO.'),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $tenant = Filament::getTenant();
        $branding = app(TenantBrandingService::class);

        // HTML sanitizen — XSS-Schutz weil Templates {!! !!} nutzen
        $allowedHtml = 'p,br,strong,em,u,h1,h2,h3,h4,ul,ol,li,a[href|target|rel],table,thead,tbody,tr,th,td';

        $branding->setMany($tenant, [
            TenantConfigConstants::IMPRESSUM => $data['impressum']
                ? clean($data['impressum'], ['HTML.Allowed' => $allowedHtml])
                : null,
            TenantConfigConstants::DATENSCHUTZ => $data['datenschutz']
                ? clean($data['datenschutz'], ['HTML.Allowed' => $allowedHtml])
                : null,
        ]);

        Notification::make()
            ->title('Rechtliche Seiten gespeichert')
            ->success()
            ->send();
    }
}
