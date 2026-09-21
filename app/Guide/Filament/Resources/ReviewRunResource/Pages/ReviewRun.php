<?php

declare(strict_types=1);

namespace App\Guide\Filament\Resources\ReviewRunResource\Pages;

use App\Guide\Filament\Resources\ReviewRunResource;
use App\Guide\Filament\Resources\TopicResource;
use App\Guide\Services\ReviewService;
use App\Guide\Services\TopicDirectory;
use App\Guide\Support\ReviewShortcuts;
use App\Guide\Support\UnreachableSources;
use App\Guide\Support\Usd;
use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Throwable;

/**
 * Pruefblatt eines Laufs (#16, design/guide-dashboard.md §8.2): Anlass,
 * Stand-Zeile, Changelog-Vorschlag, geaenderte Abschnitte als Diff mit Quellen,
 * Qualitaetsbericht und Faktenabgleich. Aktionen Freigeben (F), Mit Hinweis
 * neu schreiben (S), Verwerfen (V), Ueberspringen (J), vorheriger Eintrag (K),
 * Uebersicht (?). Nach jeder Entscheidung geht es zum naechsten Eintrag.
 *
 * Ab 1280 px zweispaltig (§8.1, #33): links die Warteschlange, rechts das
 * Pruefblatt. Der Wechsel zwischen Eintraegen laedt die Seite nicht neu
 * (showEntry()), nur die Adresse wird mitgefuehrt; danach liegt der Fokus
 * auf der Ueberschrift. Die Einzeltasten setzt der Tastenhandler im View
 * (nicht Filament-keyBindings), damit sie nur im Pruefblatt, nie in
 * Eingabefeldern und nicht bei abgeschaltetem Schalter wirken
 * (ReviewShortcuts, WCAG 2.1.4).
 *
 * Adresse /pruefung/{tenant-id}-{run-id}; gelesen und geschrieben wird ueber
 * ReviewService im Tenant-Kontext des Laufs.
 */
class ReviewRun extends Page
{
    protected static string $resource = ReviewRunResource::class;

    protected string $view = 'content.guide.review-detail';

    #[Locked]
    public int $tenantId;

    #[Locked]
    public int $runId;

    /** @var array<string, mixed>|null */
    protected ?array $sheet = null;

    public function mount(string $record): void
    {
        $parsed = TopicDirectory::parseKey($record);

        abort_if($parsed === null || app(TopicDirectory::class)->tenant($parsed[0]) === null, 404);

        [$this->tenantId, $this->runId] = $parsed;

        abort_if($this->sheet() === null, 404);
    }

    public function getTitle(): string|Htmlable
    {
        return (string) ($this->sheet()['question'] ?? __('Prüfung'));
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [
            ReviewRunResource::getUrl() => __('Prüfung'),
            __('Prüfblatt'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function sheet(): array
    {
        return $this->sheet ??= app(ReviewService::class)->sheet($this->tenant(), $this->runId) ?? [];
    }

    public function tenant(): Tenant
    {
        return app(TopicDirectory::class)->tenant($this->tenantId) ?? abort(404);
    }

    public function canDecide(): bool
    {
        $sheet = $this->sheet();

        return ($sheet['is_review'] ?? false)
            && ($sheet['decision']['decision'] ?? null) !== 'approved'
            && ($sheet['has_version'] ?? false)
            && ! ($sheet['awaits_outline'] ?? false);
    }

    /**
     * Fakt geaendert, aber kein Changelog-Eintrag: das Aktualisiert-Datum
     * darf sich dann nicht bewegen (§8.2 Punkt 3).
     */
    public function missingChangelog(): bool
    {
        $sheet = $this->sheet();

        return ($sheet['mode'] ?? null) !== 'create'
            && ($sheet['changelog']['new'] ?? null) === null
            && collect($sheet['sections'] ?? [])->contains(fn (array $section): bool => $section['reasons'] !== []);
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        // Closures: nach showEntry() gilt im selben Request schon der neue Lauf.
        return [
            Action::make('preview')
                ->label(__('Vorschau'))
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->url(fn (): ?string => $this->sheet()['preview_url'] ?? null, shouldOpenInNewTab: true)
                ->visible(fn (): bool => filled($this->sheet()['preview_url'] ?? null)),
            Action::make('topic')
                ->label(__('Zum Thema'))
                ->icon('heroicon-o-queue-list')
                ->color('gray')
                ->url(fn (): string => TopicResource::detailUrl((string) ($this->sheet()['topic_key'] ?? ''), 'laeufe')),
        ];
    }

    public function approveAction(): Action
    {
        // Bestaetigung nur, wenn eine Quelle nicht erreichbar ist (§5.7.5 A.3).
        // Fokus auf „Abbrechen“ (x-trap nimmt das [autofocus]-Element), damit
        // ein zweites F nicht gleich freigibt; Esc schliesst wie jedes Modal,
        // der Fokus geht an „Freigeben“ zurueck (Tastenhandler im View).
        // Als Closures, weil showEntry() den Lauf im selben Request wechselt.
        return Action::make('approve')
            ->label(__('Freigeben'))
            ->color('primary')
            ->extraAttributes(['data-shortcut' => 'f'])
            ->disabled(fn (): bool => ! $this->canDecide() || $this->missingChangelog())
            ->requiresConfirmation()
            ->modalHidden(fn (): bool => ($this->sheet()['unreachable'] ?? []) === [])
            ->modalHeading(__('Trotzdem freigeben?'))
            ->modalDescription(fn (): string => UnreachableSources::approveConfirmation())
            ->modalIcon(null)
            ->modalSubmitActionLabel(__('Freigeben'))
            ->modalCancelAction(fn (Action $action): Action => $action->extraAttributes(['autofocus' => true]))
            ->action(fn () => $this->decide(
                fn (ReviewService $service) => $service->approve($this->tenant(), $this->runId),
                __('Freigegeben. Die Veröffentlichung läuft.'),
            ));
    }

    public function rewriteAction(): Action
    {
        return Action::make('rewrite')
            ->label(__('Zurück zum Schreiben …'))
            ->color('gray')
            ->extraAttributes(['data-shortcut' => 's'])
            ->disabled(fn (): bool => ! $this->canDecide())
            ->modalHeading(__('Mit Hinweis neu schreiben'))
            ->modalDescription(__('Die geprüften Abschnitte werden mit Ihrem Hinweis überarbeitet und gehen danach erneut durch das Qualitätsgate. Kosten ≈ :cost.', [
                'cost' => Usd::format(Usd::estimate('update')),
            ]))
            ->modalSubmitActionLabel(__('Neu schreiben'))
            ->schema([
                Textarea::make('instructions')
                    ->label(__('Was soll anders werden?'))
                    ->placeholder(fn (): string => ($this->sheet()['unreachable'] ?? []) !== [] ? __('z. B. Ersatzquelle nennen') : __('z. B. Förderhöchstbetrag mit Quelle KfW belegen, Absatz zu Fristen kürzen'))
                    ->required()
                    ->maxLength(2000)
                    ->rows(5),
            ])
            ->action(fn (array $data) => $this->decide(
                fn (ReviewService $service) => $service->rewrite($this->tenant(), $this->runId, (string) $data['instructions']),
                __('Zurück zum Schreiben. Der Lauf erscheint nach der Überarbeitung wieder hier, falls das Gate nicht freigibt.'),
            ));
    }

    public function discardAction(): Action
    {
        return Action::make('discard')
            ->label(__('Verwerfen …'))
            ->color('status-failed')
            ->link()
            ->extraAttributes(['data-shortcut' => 'v'])
            ->disabled(fn (): bool => ! ($this->sheet()['is_review'] ?? false) || ($this->sheet()['decision']['decision'] ?? null) === 'approved')
            ->modalHeading(__('Lauf verwerfen'))
            ->modalDescription(__('Der Lauf endet als „Fehlgeschlagen — verworfen“. Der Artikel bleibt in der bisherigen Fassung online.'))
            ->modalSubmitActionLabel(__('Verwerfen'))
            ->schema([
                Select::make('reason')
                    ->label(__('Grund'))
                    ->options(collect(ReviewService::DISCARD_REASONS)->map(fn (string $label): string => __($label))->all())
                    ->required(),
                Textarea::make('note')
                    ->label(__('Anmerkung'))
                    ->maxLength(1000)
                    ->rows(3),
            ])
            ->action(fn (array $data) => $this->decide(
                fn (ReviewService $service) => $service->discard($this->tenant(), $this->runId, (string) $data['reason'], $data['note'] ?? null),
                __('Verworfen. Die bisherige Fassung bleibt online.'),
            ));
    }

    public function skipAction(): Action
    {
        return Action::make('skip')
            ->label(__('Überspringen'))
            ->link()
            ->color('gray')
            ->extraAttributes(['data-shortcut' => 'j'])
            ->action(fn () => $this->goTo($this->neighbour(1)));
    }

    public function previousAction(): Action
    {
        return Action::make('previous')
            ->label(__('Vorheriger'))
            ->link()
            ->color('gray')
            ->extraAttributes(['data-shortcut' => 'k'])
            ->disabled(fn (): bool => $this->neighbour(-1) === null)
            ->action(fn () => $this->goTo($this->neighbour(-1), fallbackToList: false));
    }

    /**
     * Uebersicht der Tastenkuerzel (?), mit Hinweis auf den Schalter im
     * Nutzermenue.
     */
    public function shortcutsAction(): Action
    {
        return Action::make('shortcuts')
            ->label(__('Tastenkürzel'))
            ->link()
            ->color('gray')
            ->extraAttributes(['data-shortcut' => '?'])
            ->modalHeading(__('Tastenkürzel'))
            ->modalWidth('md')
            ->modalContent(fn (): View => view('content.guide.partials.review-shortcuts', [
                'keys' => ReviewShortcuts::keys(),
                'enabled' => ReviewShortcuts::enabled(),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Schließen'));
    }

    /**
     * "Formulierung aendern" (§8.2 Punkt 3): Changelog-Satz vor der
     * Freigabe bearbeiten, ein Satz, hoechstens 160 Zeichen. Fehlt der
     * Eintrag, legt die Aktion ihn an.
     */
    public function editChangelogAction(): Action
    {
        return Action::make('editChangelog')
            ->label(fn (): string => ($this->sheet()['changelog']['new'] ?? null) !== null ? __('Formulierung ändern') : __('Changelog-Satz ergänzen'))
            ->link()
            ->icon('heroicon-m-pencil-square')
            ->visible(fn (): bool => $this->canDecide() && ($this->sheet()['mode'] ?? null) !== 'create')
            ->modalHeading(__('Changelog-Satz'))
            ->modalDescription(__('Erscheint unter „Was ist neu?“ im Artikel. Ein Satz, höchstens :max Zeichen.', ['max' => ReviewService::CHANGELOG_MAX_LENGTH]))
            ->modalSubmitActionLabel(__('Übernehmen'))
            ->fillForm(fn (): array => ['summary' => (string) ($this->sheet()['changelog']['new']['summary'] ?? '')])
            ->schema([
                TextInput::make('summary')
                    ->label(__('Satz'))
                    ->required()
                    ->maxLength(ReviewService::CHANGELOG_MAX_LENGTH)
                    ->autofocus(),
            ])
            ->action(function (array $data): void {
                try {
                    app(ReviewService::class)->updateChangelog($this->tenant(), $this->runId, (string) $data['summary']);
                } catch (Throwable $exception) {
                    report($exception);
                    Notification::make()->title($exception->getMessage())->danger()->send();

                    return;
                }

                $this->sheet = null;

                Notification::make()->title(__('Changelog-Satz übernommen.'))->success()->send();
            });
    }

    /**
     * Klick in der Warteschlange (§8.1): Eintrag ohne Seitenwechsel oeffnen.
     */
    public function showEntry(string $key): void
    {
        $row = collect(ReviewRunResource::queue())->firstWhere('__key', $key);

        if ($row !== null) {
            $this->goTo($row, fallbackToList: false);
        }
    }

    /**
     * Warteschlange fuer die linke Spalte (§8.1), aelteste zuerst.
     *
     * @return list<array<string, mixed>>
     */
    public function queue(): array
    {
        return ReviewRunResource::queue();
    }

    public function currentKey(): string
    {
        return TopicDirectory::key($this->tenantId, $this->runId);
    }

    public function shortcutsEnabled(): bool
    {
        return ReviewShortcuts::enabled();
    }

    /**
     * @param  callable(ReviewService): void  $decision
     */
    private function decide(callable $decision, string $message): void
    {
        try {
            $decision(app(ReviewService::class));
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()->title($exception->getMessage())->danger()->send();
            $this->sheet = null;

            return;
        }

        Notification::make()->title($message)->success()->send();

        $this->goTo($this->neighbour(1));
    }

    /**
     * Eintrag oeffnen: ohne Seitenwechsel, Adresse per replaceState
     * mitgefuehrt, Fokus auf die Ueberschrift (§8.2). Wartet der Lauf auf
     * die Gliederung, geht es in den Editor; ohne Eintrag zurueck zur Liste
     * (§8.4 "Leer und gut").
     *
     * @param  array<string, mixed>|null  $row
     */
    private function goTo(?array $row, bool $fallbackToList = true): void
    {
        if ($row === null) {
            if ($fallbackToList) {
                $this->redirect(ReviewRunResource::getUrl());
            }

            return;
        }

        if ($row['awaits_outline']) {
            $this->redirect(ReviewRunResource::entryUrl($row));

            return;
        }

        $this->tenantId = (int) $row['tenant_id'];
        $this->runId = (int) $row['run_id'];
        $this->sheet = null;

        $this->js('history.replaceState(history.state, "", '.json_encode(ReviewRunResource::entryUrl($row)).'); window.scrollTo({top: 0});');
        $this->dispatch('guide-review-entry-shown');
    }

    /**
     * Nachbar in der Warteschlange: +1 naechster, -1 vorheriger. Steht der
     * aktuelle Lauf nicht (mehr) darin, ist der naechste der erste Eintrag
     * und einen vorherigen gibt es nicht. Der naechste nach dem letzten
     * ist der erste andere Eintrag.
     *
     * @return array<string, mixed>|null
     */
    private function neighbour(int $step): ?array
    {
        $queue = collect(ReviewRunResource::queue())->values();
        $current = $this->currentKey();
        $index = $queue->search(fn (array $row): bool => $row['__key'] === $current);

        if ($index === false) {
            return $step > 0 ? $queue->first() : null;
        }

        if ($step < 0) {
            return $queue->get($index - 1);
        }

        return $queue->get($index + 1) ?? $queue->reject(fn (array $row): bool => $row['__key'] === $current)->first();
    }
}
