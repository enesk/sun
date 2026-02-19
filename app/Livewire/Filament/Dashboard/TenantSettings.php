<?php

namespace App\Livewire\Filament\Dashboard;

use App\Constants\TenantConfigConstants;
use App\Services\TenantService;
use Filament\Facades\Filament;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Livewire\Component;
use Parfaitementweb\FilamentCountryField\Forms\Components\Country;

class TenantSettings extends Component implements HasForms
{
    use InteractsWithForms;

    private TenantService $tenantService;

    public ?array $data = [];

    public function render()
    {
        return view('livewire.filament.dashboard.tenant-settings');
    }

    public function boot(TenantService $tenantService): void
    {
        $this->tenantService = $tenantService;
    }

    public function mount(): void
    {
        $tenant = Filament::getTenant();

        $fields = [
            'tenant_name' => $tenant->name,
            'impressum' => $tenant->getAttribute(TenantConfigConstants::IMPRESSUM),
            'datenschutz' => $tenant->getAttribute(TenantConfigConstants::DATENSCHUTZ),
        ];

        $address = $tenant->address()->first();

        if ($address) {
            $fields = array_merge($fields, [
                'address_line_1' => $address->address_line_1,
                'address_line_2' => $address->address_line_2,
                'city' => $address->city,
                'state' => $address->state,
                'zip' => $address->zip,
                'country_code' => $address->country_code,
                'phone' => $address->phone,
                'tax_number' => $address->tax_number,
                'tenant_name' => $tenant->name,
            ]);
        }

        $this->form->fill($fields);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('tenant_name')
                    ->label(__('Workspace Name'))
                    ->helperText(__('Edit the name of your workspace'))
                    ->required(),

                Section::make([
                    TextInput::make('address_line_1')
                        ->label(__('Address Line 1'))
                        ->helperText(__('Street address, company name, c/o')),
                    TextInput::make('address_line_2')
                        ->label(__('Address Line 2'))
                        ->helperText(__('Apartment, suite, unit, building, floor, etc.')),
                    TextInput::make('city')
                        ->label(__('City')),
                    TextInput::make('state')
                        ->label(__('State')),
                    TextInput::make('zip')
                        ->label(__('Zip')),
                    Country::make('country_code')
                        ->label(__('Country')),
                    TextInput::make('phone')
                        ->label(__('Phone')),
                    TextInput::make('tax_number')
                        ->label(__('Tax Number')),
                ])->heading(__('Organization Address'))
                    ->description(__('This address will be used for issuing invoices')),

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
                ])->heading('Rechtliche Seiten')
                    ->description('Impressum und Datenschutzerklärung für Ihr Portal. Links erscheinen automatisch im Footer sobald Inhalte hinterlegt sind.'),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $tenant = Filament::getTenant();

        $this->tenantService->updateTenantName($tenant, $data['tenant_name']);

        $address = $tenant->address()->first();

        if ($address) {
            $address->update($data);
        } else {
            $tenant->address()->create($data);
        }

        // Rechtliche Seiten — HTML sanitizen wegen {!! !!} im Template
        $allowedHtml = 'p,br,strong,em,u,h2,h3,ul,ol,li,a[href|target|rel]';

        $tenant->update([
            TenantConfigConstants::IMPRESSUM => $data['impressum']
                ? clean($data['impressum'], ['HTML.Allowed' => $allowedHtml])
                : null,
            TenantConfigConstants::DATENSCHUTZ => $data['datenschutz']
                ? clean($data['datenschutz'], ['HTML.Allowed' => $allowedHtml])
                : null,
        ]);

        Notification::make()
            ->title(__('Settings Saved'))
            ->success()
            ->send();
    }
}
