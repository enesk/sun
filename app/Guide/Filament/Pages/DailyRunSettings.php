<?php

declare(strict_types=1);

namespace App\Guide\Filament\Pages;

use App\Guide\Filament\Concerns\HasSettingsTabs;
use App\Guide\Services\DailyRunSettings as DailyRunSettingsService;
use App\Guide\Services\TopicAdminService;
use App\Guide\Services\TopicDirectory;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Throwable;

/**
 * Einstellungen › Tageslauf (#33, design/guide-dashboard.md §9.1):
 * globaler Schalter "Tageslauf aktiv", Laufzeitfenster (schreibgeschuetzt),
 * Pruefabstand je Kategorie mit Kostenfolge und die Liste der aktiven
 * Pausen mit "Fortsetzen". Nur fuer Inhaber, wie Einstellungen › Portal.
 */
class DailyRunSettings extends Page
{
    use HasSettingsTabs;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-play-pause';

    protected static ?string $slug = 'einstellungen/tageslauf';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'content.guide.daily-run-settings';

    /**
     * Auswahl je Kategorie-Slug: '' = Vorgabe, '1'/'3'/'7'/'14' = Tage,
     * 'gemischt' = Portale weichen ab und bleiben unveraendert.
     *
     * @var array<string, string>
     */
    public array $intervals = [];

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->canManageContentSettings();
    }

    public function getTitle(): string|Htmlable
    {
        return __('Tageslauf');
    }

    public function mount(): void
    {
        $this->fillIntervals();
    }

    /**
     * @return array{paused: bool, at: string|null, by: string|null}
     */
    public function state(): array
    {
        return $this->service()->state();
    }

    /**
     * @return list<array{slug: string, name: string, portals: int, topics: int, interval: int|string|null}>
     */
    public function categories(): array
    {
        return $this->service()->categories($this->directory()->tenants());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pauses(): array
    {
        return $this->service()->pauses($this->directory()->tenants());
    }

    /**
     * Gewaehlter Abstand einer Zeile als Zahl (null = Vorgabe, false =
     * gemischt/unveraendert).
     */
    public function selectedInterval(string $slug): int|null|false
    {
        $value = $this->intervals[$slug] ?? '';

        return match (true) {
            $value === 'gemischt' => false,
            $value === '' => null,
            default => (int) $value,
        };
    }

    public function toggleRunAction(): Action
    {
        $paused = $this->state()['paused'];

        $action = Action::make('toggleRun')
            ->label($paused ? __('Tageslauf fortsetzen') : __('Tageslauf anhalten'))
            ->requiresConfirmation()
            ->modalIcon(null)
            ->action(function (): void {
                $paused = $this->state()['paused'];
                $user = Filament::auth()->user();

                if ($paused) {
                    $this->service()->resume();
                }

                if (! $paused) {
                    $this->service()->pause($user instanceof User ? $user : null);
                }

                Notification::make()
                    ->title($paused ? __('Tageslauf fortgesetzt. Der nächste Lauf startet im Laufzeitfenster.') : __('Tageslauf pausiert. Es startet kein neuer Lauf.'))
                    ->success()
                    ->send();
            });

        return $paused
            ? $action
                ->modalHeading(__('Tageslauf fortsetzen?'))
                ->modalDescription(__('Fällige Themen laufen ab dem nächsten Laufzeitfenster wieder. Dabei entstehen Kosten.'))
                ->modalSubmitActionLabel(__('Fortsetzen'))
            : $action
                ->modalHeading(__('Tageslauf anhalten?'))
                ->modalDescription(__('Ab sofort startet kein neuer Lauf. Laufende Läufe werden beendet. Veröffentlichte Artikel bleiben online.'))
                ->modalSubmitActionLabel(__('Anhalten'))
                ->modalCancelAction(fn (Action $action): Action => $action->extraAttributes(['autofocus' => true]));
    }

    public function resumeTopicAction(): Action
    {
        return Action::make('resumeTopic')
            ->label(__('Fortsetzen'))
            ->link()
            ->action(function (array $arguments): void {
                $key = (string) ($arguments['key'] ?? '');
                $result = TopicDirectory::parseKey($key) !== null ? app(TopicAdminService::class)->activate([$key]) : ['done' => 0];

                if ($result['done'] === 0) {
                    Notification::make()->title(__('Das Thema lässt sich nicht fortsetzen, weil seine Gliederung nicht gesperrt ist.'))->warning()->send();

                    return;
                }

                Notification::make()->title(__('Thema fortgesetzt.'))->success()->send();
            });
    }

    public function saveIntervals(): void
    {
        $categories = collect($this->categories())->keyBy('slug');
        $changes = [];
        $delta = 0.0;

        foreach ($categories as $slug => $category) {
            $selected = $this->selectedInterval((string) $slug);

            if ($selected === false || $selected === $category['interval']) {
                continue;
            }

            $changes[(string) $slug] = $selected;
            $delta += DailyRunSettingsService::dailyCostDelta($category['topics'], $selected)
                - ($category['interval'] === 'gemischt' ? 0.0 : DailyRunSettingsService::dailyCostDelta($category['topics'], $category['interval']));
        }

        if ($changes === []) {
            Notification::make()->title(__('Keine Änderung.'))->send();

            return;
        }

        try {
            $this->service()->setIntervals($this->directory()->tenants(), $changes);
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->title($exception->getMessage())->danger()->send();

            return;
        }

        $this->fillIntervals();

        Notification::make()
            ->title(__('Prüfabstand gespeichert.'))
            ->body(__('Kostenfolge gegenüber bisher: :delta', ['delta' => DailyRunSettingsService::formatDelta($delta)]))
            ->success()
            ->send();
    }

    public function isNetworkWide(): bool
    {
        return $this->directory()->isNetworkWide();
    }

    private function fillIntervals(): void
    {
        $this->intervals = collect($this->categories())
            ->mapWithKeys(fn (array $row): array => [$row['slug'] => $row['interval'] === null ? '' : (string) $row['interval']])
            ->all();
    }

    private function service(): DailyRunSettingsService
    {
        return app(DailyRunSettingsService::class);
    }

    private function directory(): TopicDirectory
    {
        return app(TopicDirectory::class);
    }
}
