<?php

declare(strict_types=1);

namespace App\Content\Livewire\Concerns;

use App\Content\Services\ContentPipelineService;
use App\Content\Support\GenerationStart;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Throwable;

/**
 * Detailblatt einer Pipeline-Karte samt seiner Handlungen (#19, #36).
 *
 * Board und Artikelliste zeigen dasselbe Blatt von rechts und dieselben
 * Schaltflaechen (design/content-dashboard.md, §3a) — deshalb stehen sie hier
 * einmal statt zweimal. Welche Schaltflaeche im Blatt erscheint, entscheidet
 * content.partials.card-details anhand des Status.
 *
 * Die nutzende Komponente muss HasActions und HasSchemas umsetzen.
 */
trait HasPipelineCardActions
{
    /**
     * Budgetstand des laufenden Requests; nicht als Livewire-Zustand
     * gehalten, sondern je Aufbau neu geholt.
     *
     * @var array{available: bool, tight: bool, reason: ?string, limit_usd: float}|null
     */
    protected ?array $generationBudget = null;

    /**
     * Detailblatt von rechts: Scores und Quellen, Handlungen im Fuss.
     */
    public function cardDetailsAction(): Action
    {
        return Action::make('cardDetails')
            ->modalHeading(fn (array $arguments): string => (string) ($this->cardDetails($arguments)['title'] ?? __('Karte')))
            ->slideOver()
            ->modalWidth('lg')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Schließen'))
            ->modalContent(fn (array $arguments): View => view('content.partials.card-details', [
                'card' => $this->cardDetails($arguments),
                'generationBudget' => $this->generationBudget(),
            ]));
    }

    /**
     * Ablehnen mit Pflichtgrund. Der Grund fliesst in die Lernschleife (#23) —
     * eine Ablehnung ohne Grund ist verlorene Information.
     */
    public function rejectAction(): Action
    {
        return Action::make('reject')
            ->label(__('Ablehnen'))
            ->color('gray')
            ->icon('heroicon-o-x-circle')
            ->modalHeading(__('Wirklich ablehnen?'))
            ->modalDescription(__('Themen werden verworfen, Artikel zurückgezogen. Beides lässt sich nachvollziehen.'))
            ->modalSubmitActionLabel(__('Ablehnen'))
            ->schema([
                Textarea::make('reason')
                    ->label(__('Grund'))
                    ->required()
                    ->minLength((int) config('content.withdrawal.reason_min_length', 10))
                    ->rows(3),
            ])
            ->action(function (array $arguments, array $data): void {
                $this->handleCardAction(fn (ContentPipelineService $service): string => $service->reject(
                    (string) $arguments['card'],
                    (string) $data['reason'],
                    filament()->auth()->id(),
                ));
            });
    }

    /**
     * Jetzt generieren — stoesst den Generatorlauf sofort an (#42, §5).
     *
     * Der Dialog nennt die Dauer und den Kostenrahmen aus der Konfiguration;
     * eine geschaetzte Zahl waere eine Zusage, die niemand halten kann. Ist
     * das Tagesbudget aufgebraucht, bleibt die Handlung sichtbar, aber
     * deaktiviert — die Begruendung steht im Blatt unter der Schaltflaeche.
     */
    public function generateNowAction(): Action
    {
        $budget = $this->generationBudget();

        return Action::make('generateNow')
            ->label(__('Jetzt generieren'))
            ->color('content')
            ->icon('heroicon-o-sparkles')
            ->disabled(! $budget['available'])
            ->requiresConfirmation()
            ->modalHeading(__('Jetzt erzeugen?'))
            ->modalDescription(function () use ($budget): string {
                $lines = [__('Der Artikel wird sofort erzeugt und ist in etwa 3 bis 5 Minuten fertig.')];

                if ($budget['tight']) {
                    $lines[] = __('Das Tagesbudget reicht möglicherweise nicht für einen vollständigen Artikel.');
                }

                $lines[] = __('Kostenrahmen je Artikel: bis :amount USD.', [
                    'amount' => number_format((float) $budget['limit_usd'], 2, ',', '.'),
                ]);

                return implode(' ', $lines);
            })
            ->modalSubmitActionLabel(__('Jetzt erzeugen'))
            ->action(function (array $arguments): void {
                $this->handleCardAction(fn (ContentPipelineService $service): GenerationStart => $service->generateNow((string) $arguments['card']));
            });
    }

    /**
     * Reserve hochstufen: ein bewertetes Thema in den Plan des Tages heben.
     */
    public function promoteReserveAction(): Action
    {
        return Action::make('promoteReserve')
            ->label(__('Reserve hochstufen'))
            ->color('content')
            ->icon('heroicon-o-arrow-up-circle')
            ->requiresConfirmation()
            ->modalHeading(__('Thema hochstufen?'))
            ->modalDescription(__('Das Thema wird für den heutigen Tag eingeplant.'))
            ->action(function (array $arguments): void {
                $this->handleCardAction(fn (ContentPipelineService $service): string => $service->promoteReserve((string) $arguments['card']));
            });
    }

    /**
     * Haken fuer die nutzende Komponente: Zeitstempel nachziehen, Seite neu
     * aufbauen. Board und Liste tun hier Unterschiedliches.
     */
    protected function afterCardAction(): void {}

    /**
     * Eine Handlung ausfuehren und ihr Ergebnis melden. Fehlerhafte Uebergaenge
     * melden sich als Hinweis, nicht als Seitenfehler.
     *
     * Ein GenerationStart unterscheidet zwei Erfolge: eingereiht ist gruen,
     * nur vorgemerkt gelb (#42, §3). Handlungen ohne Job melden weiter einen
     * Text und damit gruen.
     *
     * @param  callable(ContentPipelineService): (string|GenerationStart)  $callback
     */
    protected function handleCardAction(callable $callback): void
    {
        try {
            $result = $callback(app(ContentPipelineService::class));

            $deferred = $result instanceof GenerationStart && ! $result->queued;
            $notification = Notification::make()->title(
                $result instanceof GenerationStart ? $result->message : $result,
            );

            ($deferred ? $notification->warning() : $notification->success())->send();
        } catch (Throwable $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();
        }

        $this->afterCardAction();
    }

    /**
     * Budgetstand fuer diesen Seitenaufbau. Einmal fragen, mehrfach nutzen —
     * das Board zeigt bis zu 50 Karten je Spalte (#42, §4).
     *
     * @return array{available: bool, tight: bool, reason: ?string, limit_usd: float}
     */
    public function generationBudget(): array
    {
        return $this->generationBudget ??= app(ContentPipelineService::class)->generationBudget();
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function cardDetails(array $arguments): array
    {
        return app(ContentPipelineService::class)->cardDetails((string) ($arguments['card'] ?? '')) ?? [];
    }
}
