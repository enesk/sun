<?php

namespace App\Livewire\Filament\Dashboard;

use App\Themes\ThemeManager;
use Filament\Facades\Filament;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Livewire\Component;

class ThemeSettings extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    public function render()
    {
        return view('livewire.filament.dashboard.theme-settings');
    }

    public function mount(): void
    {
        $tenant = Filament::getTenant();
        $themeManager = app(ThemeManager::class);

        $themeSlug = $themeManager->getTenantTheme($tenant);
        $themeOptions = $themeManager->getTenantThemeOptions($tenant);

        // Filament interprets dots as nested arrays, so we build the nested structure
        $this->form->fill([
            'theme' => [
                'active' => $themeSlug,
                'options' => $themeOptions,
            ],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $themeManager = app(ThemeManager::class);
        $themes = $themeManager->discover();

        $themeOptions = $themes->mapWithKeys(fn ($theme) => [
            $theme->slug => $theme->name . ' (v' . $theme->version . ')',
        ])->toArray();

        return $schema
            ->components([
                Section::make([
                    Select::make(ThemeManager::TENANT_THEME_KEY)
                        ->label('Aktives Theme')
                        ->options($themeOptions)
                        ->default(ThemeManager::DEFAULT_THEME)
                        ->required()
                        ->live()
                        ->helperText('Das Theme bestimmt das Aussehen Ihres Portals.'),

                    Placeholder::make('theme_info')
                        ->label('Theme-Info')
                        ->content(function ($get) use ($themeManager): string {
                            $slug = $get(ThemeManager::TENANT_THEME_KEY) ?? ThemeManager::DEFAULT_THEME;
                            $theme = $themeManager->get($slug);

                            if (! $theme) {
                                return 'Theme nicht gefunden.';
                            }

                            return $theme->description . ' — Autor: ' . ($theme->author ?: 'Team');
                        }),
                ])->heading('Theme-Auswahl')
                    ->description('Wählen Sie das Design für Ihr Portal.'),

                Section::make(function ($get) use ($themeManager): array {
                    $slug = $get(ThemeManager::TENANT_THEME_KEY) ?? ThemeManager::DEFAULT_THEME;
                    $theme = $themeManager->get($slug);

                    if (! $theme || empty($theme->options)) {
                        return [
                            Placeholder::make('no_options')
                                ->label('')
                                ->content('Dieses Theme hat keine konfigurierbaren Optionen.'),
                        ];
                    }

                    $fields = [];
                    foreach ($theme->options as $key => $config) {
                        $fieldName = ThemeManager::TENANT_THEME_OPTIONS_KEY . '.' . $key;
                        $label = $config['label'] ?? $key;

                        $fields[] = match ($config['type'] ?? 'string') {
                            'boolean' => Toggle::make($fieldName)
                                ->label($label)
                                ->default($config['default'] ?? false),

                            'select' => Select::make($fieldName)
                                ->label($label)
                                ->options(
                                    collect($config['choices'] ?? [])
                                        ->mapWithKeys(fn ($choice) => [$choice => $choice])
                                        ->toArray()
                                )
                                ->default($config['default'] ?? null),

                            default => TextInput::make($fieldName)
                                ->label($label)
                                ->default($config['default'] ?? ''),
                        };
                    }

                    return $fields;
                })
                    ->heading('Theme-Optionen')
                    ->description('Passen Sie das Verhalten des Themes an.')
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $tenant = Filament::getTenant();
        $themeManager = app(ThemeManager::class);

        // Extract nested structure: ['theme' => ['active' => '...', 'options' => [...]]]
        $themeSlug = data_get($data, 'theme.active', ThemeManager::DEFAULT_THEME);
        $options = data_get($data, 'theme.options', []);

        $themeManager->setTenantTheme($tenant, $themeSlug);
        $themeManager->setTenantThemeOptions($tenant, $options);

        Notification::make()
            ->title('Theme-Einstellungen gespeichert')
            ->success()
            ->send();
    }
}
