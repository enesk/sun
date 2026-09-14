<?php

declare(strict_types=1);

namespace App\Filament\Content\Pages;

use App\Content\Services\ContentTenantContext;
use App\Content\Services\SourceMonitorService;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Quellen-Monitor (#20), design/content-dashboard.md, §6.
 *
 * Bewusst kein sechster Navigationspunkt, sondern der Reiter "Quellen" unter
 * den Einstellungen: er wird selten und anlassbezogen geoeffnet, ein eigener
 * Punkt wuerde die Navigation ohne Nutzen verlaengern. Deshalb
 * shouldRegisterNavigation() = false und ein Pfad unterhalb der
 * Einstellungen.
 */
class SourceMonitor extends ContentPage
{
    protected static ?string $slug = 'einstellungen/quellen';

    protected static string $followUpTicket = '#20';

    protected string $view = 'content.pages.source-monitor';

    protected static bool $shouldRegisterNavigation = false;

    public static function getNavigationLabel(): string
    {
        return __('Quellen');
    }

    public function getTitle(): string
    {
        return __('Quellen-Monitor');
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->canManageContentSettings();
    }

    /**
     * Manueller Abruf. Das Portal ist Pflicht: `source_items` landen in der
     * Mandantendatenbank, ein Lauf "für alle" waere 24 Jobs auf einen Klick.
     */
    public function runNowAction(): Action
    {
        return Action::make('runNow')
            ->label(__('Jetzt ausführen'))
            ->color('content')
            ->icon('heroicon-o-play')
            ->modalHeading(fn (array $arguments): string => __('":source" jetzt ausführen', [
                'source' => app(SourceMonitorService::class)->label((string) ($arguments['connector'] ?? '')),
            ]))
            ->modalSubmitActionLabel(__('Ausführen'))
            ->schema([
                Select::make('tenant_id')
                    ->label(__('Portal'))
                    ->options($this->portalOptions())
                    ->default($this->defaultPortal())
                    ->required()
                    ->native(false),
            ])
            ->action(function (array $arguments, array $data): void {
                try {
                    $message = app(SourceMonitorService::class)->runNow(
                        (string) $arguments['connector'],
                        (int) $data['tenant_id'],
                    );

                    Notification::make()->title($message)->success()->send();
                } catch (Throwable $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();
                }
            });
    }

    /**
     * Quelle einstellen (#55), design/content-dashboard.md, §7a.
     *
     * Bewusst dieselbe Mechanik wie "Jetzt ausführen": ein Formular mit drei
     * Feldern rechtfertigt kein zweites Muster auf derselben Seite.
     */
    public function configureAction(): Action
    {
        return Action::make('configure')
            ->label(__('Einstellen'))
            ->color('gray')
            ->icon('heroicon-o-adjustments-horizontal')
            ->modalWidth('lg')
            ->modalHeading(fn (array $arguments): string => __('Quelle einstellen: :source', [
                'source' => app(SourceMonitorService::class)->label((string) ($arguments['connector'] ?? '')),
            ]))
            ->modalDescription(__('Gilt für alle Portale ab dem nächsten Lauf.'))
            ->modalSubmitActionLabel(__('Speichern'))
            ->fillForm(fn (array $arguments): array => $this->configurationFor((string) $arguments['connector']))
            ->schema(fn (array $arguments): array => $this->configureFormSchema((string) $arguments['connector']))
            ->extraModalFooterActions(fn (array $arguments): array => $this->resetFooterAction($arguments))
            ->action(function (array $arguments, array $data): void {
                try {
                    $message = app(SourceMonitorService::class)->saveConfiguration(
                        (string) $arguments['connector'],
                        $data,
                    );

                    Notification::make()->title($message)->success()->send();
                } catch (Throwable $exception) {
                    Notification::make()->title($exception->getMessage())->danger()->send();
                }
            });
    }

    /**
     * Die fünf Felder aus §7a, in dieser Reihenfolge.
     *
     * @return array<int, mixed>
     */
    private function configureFormSchema(string $connectorKey): array
    {
        $service = app(SourceMonitorService::class);
        $configuration = $service->configuration($connectorKey);
        $credentials = $configuration['credentials'];

        return [
            Toggle::make('is_enabled')
                ->label(__('Quelle aktiv'))
                ->live()
                ->helperText(fn (Get $get): string => $get('is_enabled')
                    ? __('Wird im deklarierten Rhythmus abgerufen.')
                    : __('Kein Abruf mehr, auch nicht von Hand. Bereits gesammelte Signale bleiben erhalten und laufen normal aus.')),

            // Der Grund beantwortet in drei Wochen die Frage, die sonst
            // niemand mehr beantworten kann.
            TextInput::make('disabled_reason')
                ->label(__('Warum wird die Quelle abgeschaltet?'))
                ->maxLength(200)
                ->visible(fn (Get $get): bool => ! $get('is_enabled'))
                ->required(fn (Get $get): bool => ! $get('is_enabled')),

            Select::make('frequency')
                ->label(__('Abrufrhythmus'))
                ->options($service->frequencyOptions($connectorKey))
                ->native(false)
                ->required()
                ->helperText(__('Häufiger als die Vorgabe ist nicht wählbar — der Takt bildet Ratenlimit und Kosten der Quelle ab.')),

            TextInput::make('weight')
                ->label(__('Gewicht im Scoring'))
                ->numeric()
                ->suffix('%')
                ->step(10)
                ->minValue(0)
                ->maxValue(200)
                ->required()
                ->helperText(__('100 % = unverändert. 0 % = Signale werden weiter gesammelt, zählen im Themen-Scoring aber nicht.')),

            // Kein Eingabefeld: Zugangsdaten gehören nicht in die Datenbank.
            Placeholder::make('credentials')
                ->label(__('Zugang'))
                ->content(new HtmlString($this->credentialMarkup($credentials))),
        ];
    }

    /**
     * @param  array{state: string, text: string, missing: array<int, string>}  $credentials
     */
    private function credentialMarkup(array $credentials): string
    {
        $text = e($credentials['text']);

        if ($credentials['state'] !== 'missing') {
            return '<span class="text-content-label text-text-muted">'.$text.'</span>';
        }

        return '<span class="text-content-label" style="color: var(--color-status-failed-fg)">'.$text.'</span>'
            .'<span class="block text-content-label text-text-muted">'
            .e(__('Zugangsdaten werden auf dem Server gepflegt, nicht hier.'))
            .'</span>';
    }

    /**
     * "Auf Vorgabe zurücksetzen" erscheint nur, wenn überhaupt etwas
     * abweicht — sonst wäre es ein Verweis ohne Wirkung.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<int, Action>
     */
    private function resetFooterAction(array $arguments): array
    {
        $connectorKey = (string) ($arguments['connector'] ?? '');

        if (! app(SourceMonitorService::class)->configuration($connectorKey)['deviates']) {
            return [];
        }

        return [
            Action::make('resetConfiguration')
                ->label(__('Auf Vorgabe zurücksetzen'))
                ->link()
                ->color('gray')
                ->action(function () use ($connectorKey): void {
                    $message = app(SourceMonitorService::class)->resetConfiguration($connectorKey);

                    Notification::make()->title($message)->success()->send();

                    $this->unmountAction();
                }),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function configurationFor(string $connectorKey): array
    {
        $configuration = app(SourceMonitorService::class)->configuration($connectorKey);

        return [
            'is_enabled' => $configuration['is_enabled'],
            'disabled_reason' => $configuration['disabled_reason'],
            'frequency' => $configuration['frequency'],
            'weight' => $configuration['weight'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getMonitorData(): array
    {
        $service = app(SourceMonitorService::class);
        $connectors = $service->connectors();

        return [
            'connectors' => $connectors,
            'summary' => $service->summary($connectors),
            'not_configured' => $service->notConfigured(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function portalOptions(): array
    {
        $options = [];

        foreach (app(SourceMonitorService::class)->tenants() as $tenant) {
            /** @var Tenant $tenant */
            $options[(int) $tenant->getKey()] = (string) $tenant->name;
        }

        return $options;
    }

    /**
     * Das oben gewaehlte Portal steht vor; bei "Alle Portale" muss der
     * Pruefer eines benennen.
     */
    private function defaultPortal(): ?int
    {
        return app(ContentTenantContext::class)->selectedId();
    }
}
