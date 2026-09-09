<?php

declare(strict_types=1);

namespace App\Content\Livewire;

use App\Content\Livewire\Concerns\HasPipelineFilters;
use App\Content\Services\ContentPipelineService;
use App\Content\Services\ReviewQueueService;
use App\Content\Support\BranchResolver;
use App\Content\Support\GenerationStart;
use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

/**
 * Pruef-Queue mit Pruefflaeche (#20), design/content-dashboard.md, §4.
 *
 * Zwei Spalten statt Listen-Detail-Sprung: links die Warteschlange, rechts
 * Vorschau, Qualitaetsreport und Quellen — alle Abschnitte offen, kein
 * Reiterwechsel. Nach jeder Entscheidung rueckt die naechste Zeile
 * automatisch nach, damit eine Pruefung ohne Mausweg auskommt.
 */
class ReviewQueue extends Component implements HasActions, HasSchemas
{
    use HasPipelineFilters;
    use InteractsWithActions;
    use InteractsWithSchemas;

    /**
     * Kartenkennung des gerade geprueften Entwurfs ("7:draft:1042").
     */
    #[Url(as: 'artikel', history: true)]
    public ?string $selected = null;

    /**
     * Gruende einer Ablehnung. Sie fliessen in die Lernschleife (#23) —
     * eine Ablehnung ohne Grund ist verlorene Information.
     *
     * @return array<string, string>
     */
    public static function reasons(): array
    {
        return [
            'facts' => __('Fakten falsch oder unbelegt'),
            'sources' => __('Quellen fehlen oder taugen nicht'),
            'intent' => __('Verfehlt die Suchintention'),
            'duplicate' => __('Deckt sich mit einem bestehenden Artikel'),
            'tone' => __('Sprache und Ton unpassend'),
            'region' => __('Regionalbezug nur vorgetäuscht'),
            'legal' => __('Rechtlich oder medizinisch heikel'),
            'other' => __('Anderer Grund'),
        ];
    }

    /**
     * Budgetstand des laufenden Requests. Eine Abfrage je Seitenaufbau, nicht
     * je Schaltflaeche (#42, §4).
     *
     * @var array{available: bool, tight: bool, reason: ?string, limit_usd: float}|null
     */
    protected ?array $generationBudget = null;

    public function mount(): void
    {
        $this->ensureSelection();
    }

    /**
     * @return array{available: bool, tight: bool, reason: ?string, limit_usd: float}
     */
    public function generationBudget(): array
    {
        return $this->generationBudget ??= app(ContentPipelineService::class)->generationBudget();
    }

    /**
     * Eine Zeile der Warteschlange auswaehlen.
     */
    public function select(string $key): void
    {
        $this->selected = $key;
    }

    /**
     * Naechste bzw. vorherige Zeile — Tastenbelegung J und K.
     */
    public function step(int $offset): void
    {
        $keys = array_column($this->queue(), 'key');

        if ($keys === []) {
            $this->selected = null;

            return;
        }

        $current = $this->selected !== null ? array_search($this->selected, $keys, true) : false;
        $index = $current === false ? 0 : $current + $offset;

        $this->selected = $keys[max(0, min(count($keys) - 1, $index))];
    }

    public function approveAction(): Action
    {
        return Action::make('approve')
            ->label(__('Freigeben'))
            ->color('content')
            ->icon('heroicon-o-check-circle')
            ->requiresConfirmation()
            ->modalHeading(__('Artikel freigeben?'))
            ->modalDescription(__('Der Artikel wird freigegeben; Bilder und Veröffentlichung laufen anschließend automatisch.'))
            ->modalSubmitActionLabel(__('Freigeben'))
            ->action(fn () => $this->decide(fn (ReviewQueueService $service): GenerationStart => $service->approve($this->requireSelection())));
    }

    /**
     * Mit Hinweis neu generieren. Der Hinweis geht als `fix_instructions` an
     * den FixSectionsStep des Generators (#14).
     */
    public function regenerateAction(): Action
    {
        return Action::make('regenerate')
            ->label(__('Mit Hinweis neu generieren'))
            ->color('gray')
            ->icon('heroicon-o-arrow-path')
            ->modalHeading(__('Was soll anders werden?'))
            ->disabled(! $this->generationBudget()['available'])
            ->modalDescription(__('Der Hinweis geht wörtlich an den Generator und ersetzt keine Quellenarbeit. Die Überarbeitung läuft sofort und dauert etwa 3 bis 5 Minuten.'))
            ->modalSubmitActionLabel(__('Neu generieren'))
            ->schema([
                Textarea::make('fix_instructions')
                    ->label(__('Hinweis an den Generator'))
                    ->required()
                    ->minLength((int) config('content.withdrawal.reason_min_length', 10))
                    ->rows(4),
            ])
            ->action(fn (array $data) => $this->decide(
                fn (ReviewQueueService $service): GenerationStart => $service->regenerate(
                    $this->requireSelection(),
                    (string) $data['fix_instructions'],
                    filament()->auth()->id(),
                ),
            ));
    }

    public function discardAction(): Action
    {
        return Action::make('discard')
            ->label(__('Verwerfen'))
            // Statusfarben kennzeichnen Zustand, nicht Handlung
            // (design/content-dashboard.md, §0) — deshalb `danger`.
            ->color('danger')
            ->icon('heroicon-o-x-circle')
            ->modalHeading(__('Artikel verwerfen?'))
            ->modalDescription(__('Der Artikel wird zurückgezogen. Das Thema bleibt für die Duplikatsprüfung registriert.'))
            ->modalSubmitActionLabel(__('Verwerfen'))
            ->schema([
                Select::make('reason_code')
                    ->label(__('Grund'))
                    ->options(self::reasons())
                    ->required()
                    ->native(false),
                Textarea::make('reason')
                    ->label(__('Erläuterung'))
                    ->helperText(__('Wird unverändert in der Artikelliste angezeigt.'))
                    ->required()
                    ->minLength((int) config('content.withdrawal.reason_min_length', 10))
                    ->rows(3),
            ])
            ->action(fn (array $data) => $this->decide(
                fn (ReviewQueueService $service): GenerationStart => $service->discard(
                    $this->requireSelection(),
                    self::reasons()[$data['reason_code']].': '.trim((string) $data['reason']),
                    filament()->auth()->id(),
                ),
            ));
    }

    /**
     * Eine Entscheidung ausfuehren, melden und zur naechsten Zeile ruecken.
     *
     * Der Entwurf verlaesst die Queue in beiden Erfolgsfaellen — auch wenn
     * der Generatorjob fehlt und es beim vorgemerkten Zustand bleibt. Nur
     * dann ist die Meldung gelb statt gruen (#42, §8).
     *
     * @param  callable(ReviewQueueService): GenerationStart  $callback
     */
    private function decide(callable $callback): void
    {
        $service = app(ReviewQueueService::class);
        $keys = array_column($this->queue(), 'key');
        $position = $this->selected !== null ? array_search($this->selected, $keys, true) : false;

        try {
            $result = $callback($service);
            $notification = Notification::make()->title($result->message);

            ($result->queued ? $notification->success() : $notification->warning())->send();
        } catch (Throwable $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return;
        }

        // Der erledigte Eintrag faellt aus der Queue; an seiner Position steht
        // danach der naechste. Das ist die Bewegung, die eine Pruefung ohne
        // Mausweg schnell macht.
        $remaining = array_column($this->queue(), 'key');
        $this->selected = $remaining === []
            ? null
            : ($remaining[min(max(0, $position === false ? 0 : $position), count($remaining) - 1)] ?? null);
    }

    private function requireSelection(): string
    {
        return $this->selected ?? throw new \RuntimeException(__('Es ist kein Artikel ausgewählt.'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function queue(): array
    {
        return app(ReviewQueueService::class)->queue($this->filters());
    }

    /**
     * Ohne Auswahl steht die laengste Wartezeit oben — sie ist der Grund,
     * warum jemand diesen Screen geoeffnet hat.
     *
     * @param  list<array<string, mixed>>|null  $entries
     */
    private function ensureSelection(?array $entries = null): void
    {
        $entries ??= $this->queue();
        $keys = array_column($entries, 'key');

        if ($keys === []) {
            $this->selected = null;

            return;
        }

        if ($this->selected === null || ! in_array($this->selected, $keys, true)) {
            $this->selected = $keys[0];
        }
    }

    public function render(ReviewQueueService $service): View
    {
        $entries = $service->queue($this->filters());
        $this->ensureSelection($entries);

        return view('content.review-queue', [
            'entries' => $entries,
            'generationBudget' => $this->generationBudget(),
            'detail' => $this->selected !== null ? $service->detail($this->selected) : null,
            'lastDecisionAt' => $entries === [] ? $service->lastDecisionAt() : null,
            'portalOptions' => collect($service->tenants())->mapWithKeys(
                fn (Tenant $tenant): array => [(string) $tenant->getKey() => (string) $tenant->name],
            )->all(),
            'branchOptions' => BranchResolver::all(),
        ]);
    }
}
