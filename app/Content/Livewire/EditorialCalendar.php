<?php

declare(strict_types=1);

namespace App\Content\Livewire;

use App\Content\Enums\DisplayStatus;
use App\Content\Livewire\Concerns\HasPipelineFilters;
use App\Content\Services\ContentPipelineService;
use App\Content\Support\BranchResolver;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DateTimePicker;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

/**
 * Redaktionskalender der Produktionsansicht (#19).
 *
 * Monat als Standard, Woche als Umschaltung (design/content-dashboard.md, §3).
 * Die Monatszelle zeigt Zaehlerstand und gestapelte Statusbalken — bei 48
 * Artikeln am Tag waere eine Titelliste in der Zelle unlesbar. Einzelne
 * Artikel stehen in der Wochenansicht und im Tagesblatt.
 *
 * Verschieben von `scheduled_for` per Ziehen und Ablegen gibt es in der
 * Wochenansicht, wo ein Artikel ein eigener Block ist. Erlaubt ist es nur im
 * Anzeige-Status "Eingeplant"; veroeffentlichte Artikel sind fixiert. Fuer
 * Tastatur und Telefon steht dieselbe Aenderung als Handlung "Termin ändern"
 * im Tagesblatt.
 */
class EditorialCalendar extends Component implements HasActions, HasSchemas
{
    use HasPipelineFilters;
    use InteractsWithActions;
    use InteractsWithSchemas;

    public const VIEW_MONTH = 'monat';

    public const VIEW_WEEK = 'woche';

    #[Url(as: 'ansicht', history: true)]
    public string $mode = self::VIEW_MONTH;

    /**
     * Anker der Ansicht als Y-m-d; alle Zeitraeume leiten sich daraus ab.
     */
    #[Url(as: 'datum', history: true)]
    public string $anchor = '';

    public function mount(): void
    {
        if ($this->anchor === '') {
            $this->anchor = CarbonImmutable::today()->toDateString();
        }
    }

    public function anchorDate(): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($this->anchor);
        } catch (Throwable) {
            return CarbonImmutable::today();
        }
    }

    public function previous(): void
    {
        $this->anchor = $this->mode === self::VIEW_WEEK
            ? $this->anchorDate()->subWeek()->toDateString()
            : $this->anchorDate()->subMonthNoOverflow()->toDateString();
    }

    public function next(): void
    {
        $this->anchor = $this->mode === self::VIEW_WEEK
            ? $this->anchorDate()->addWeek()->toDateString()
            : $this->anchorDate()->addMonthNoOverflow()->toDateString();
    }

    public function today(): void
    {
        $this->anchor = CarbonImmutable::today()->toDateString();
    }

    public function switchTo(string $mode): void
    {
        $this->mode = $mode === self::VIEW_WEEK ? self::VIEW_WEEK : self::VIEW_MONTH;
    }

    /**
     * Ziel des Ziehens und Ablegens. Datum und Stunde kommen aus der Zelle;
     * ob der Artikel verschoben werden darf, entscheidet der Dienst noch
     * einmal selbst — die Sperre in der Ansicht ist nur die halbe Miete.
     */
    public function moveCard(string $key, string $date, ?int $hour = null): void
    {
        try {
            $target = CarbonImmutable::parse($date);

            if ($hour !== null) {
                $target = $target->setTime($hour, 0);
            }

            $message = app(ContentPipelineService::class)->reschedule($key, $target);

            Notification::make()->title($message)->success()->send();
        } catch (Throwable $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();
        }
    }

    /**
     * Tagesblatt von rechts: die Artikel des Tages, nach Portal gruppiert.
     */
    public function dayAction(): Action
    {
        return Action::make('day')
            ->modalHeading(fn (array $arguments): string => __('Artikel am :date', [
                'date' => CarbonImmutable::parse((string) $arguments['date'])->translatedFormat('d. F Y'),
            ]))
            ->slideOver()
            ->modalWidth('lg')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Schließen'))
            ->modalContent(function (array $arguments): View {
                $day = CarbonImmutable::parse((string) $arguments['date']);

                return view('content.partials.day-sheet', [
                    'date' => $day,
                    'groups' => collect(app(ContentPipelineService::class)
                        ->scheduleBetween($day->startOfDay(), $day->endOfDay(), $this->filters()))
                        ->groupBy('tenant')
                        ->all(),
                ]);
            });
    }

    /**
     * Termin aendern ohne Maus — dieselbe Aenderung wie das Ziehen, aber mit
     * der Tastatur und auf dem Telefon bedienbar.
     */
    public function rescheduleAction(): Action
    {
        return Action::make('reschedule')
            ->label(__('Termin ändern'))
            ->icon('heroicon-o-calendar-days')
            ->color('content')
            ->modalHeading(__('Termin ändern'))
            ->modalSubmitActionLabel(__('Verschieben'))
            ->schema([
                DateTimePicker::make('scheduled_for')
                    ->label(__('Geplante Veröffentlichung'))
                    ->seconds(false)
                    ->required(),
            ])
            ->action(function (array $arguments, array $data): void {
                $this->moveCard(
                    (string) $arguments['card'],
                    (string) $data['scheduled_for'],
                );
            });
    }

    public function render(ContentPipelineService $service): View
    {
        $anchor = $this->anchorDate();
        $filters = $this->filters();

        $data = $this->mode === self::VIEW_WEEK
            ? $this->week($service, $anchor, $filters)
            : $this->month($service, $anchor, $filters);

        // Bewusst nicht 'anchor': eine oeffentliche Eigenschaft gleichen Namens
        // gewinnt in der Ansicht gegen die Daten aus render().
        return view('content.editorial-calendar', array_merge($data, [
            'anchorDate' => $anchor,
            'portalOptions' => $service->tenants()->mapWithKeys(
                fn (Tenant $tenant): array => [(string) $tenant->getKey() => (string) $tenant->name],
            )->all(),
            'statusOptions' => DisplayStatus::options(),
            'regionOptions' => $service->regionOptions(),
            'branchOptions' => BranchResolver::all(),
        ]));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function month(ContentPipelineService $service, CarbonImmutable $anchor, array $filters): array
    {
        $start = $anchor->startOfMonth()->startOfWeek();
        $end = $anchor->endOfMonth()->endOfWeek();

        $days = $service->calendarDays($start, $end, $filters);
        $target = $service->dailyTarget($filters);

        $weeks = [];
        $cursor = $start;
        $publishedInMonth = 0;

        while ($cursor->lessThanOrEqualTo($end)) {
            $week = [];

            for ($i = 0; $i < 7; $i++) {
                $key = $cursor->toDateString();
                $day = $days[$key] ?? ['counts' => [], 'total' => 0, 'published' => 0];

                if ($cursor->month === $anchor->month) {
                    $publishedInMonth += $day['published'];
                }

                $week[] = [
                    'date' => $cursor,
                    'key' => $key,
                    'in_month' => $cursor->month === $anchor->month,
                    'is_today' => $cursor->isToday(),
                    'counts' => $day['counts'],
                    'total' => $day['total'],
                    'published' => $day['published'],
                    'target' => $target,
                ];

                $cursor = $cursor->addDay();
            }

            $weeks[] = $week;
        }

        return [
            'weeks' => $weeks,
            'blocks' => [],
            'hours' => [],
            'monthTarget' => $target * $anchor->daysInMonth,
            'monthPublished' => $publishedInMonth,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function week(ContentPipelineService $service, CarbonImmutable $anchor, array $filters): array
    {
        $start = $anchor->startOfWeek();
        $end = $anchor->endOfWeek();

        $cards = $service->scheduleBetween($start->startOfDay(), $end->endOfDay(), $filters);
        $blocks = [];

        foreach ($cards as $card) {
            if ($card['at'] === null) {
                continue;
            }

            $at = CarbonImmutable::parse((string) $card['at']);
            $hour = min(max($at->hour, $this->hours()[0]), $this->hours()[count($this->hours()) - 1]);
            $blocks[$at->toDateString()][$hour][] = $card;
        }

        $days = [];
        $cursor = $start;

        for ($i = 0; $i < 7; $i++) {
            $days[] = [
                'date' => $cursor,
                'key' => $cursor->toDateString(),
                'is_today' => $cursor->isToday(),
            ];
            $cursor = $cursor->addDay();
        }

        return [
            'weeks' => [$days],
            'blocks' => $blocks,
            'hours' => $this->hours(),
            'monthTarget' => 0,
            'monthPublished' => 0,
        ];
    }

    /**
     * Zeitachse der Wochenansicht: das Veroeffentlichungsfenster aus
     * config/content.php, gerundet auf volle Stunden.
     *
     * @return array<int, int>
     */
    private function hours(): array
    {
        $window = (array) config('content.pipeline.schedule.publish_window', ['09:00', '17:00']);
        $from = max(0, (int) CarbonImmutable::parse((string) ($window[0] ?? '09:00'))->hour - 4);
        $to = min(23, (int) CarbonImmutable::parse((string) ($window[1] ?? '17:00'))->hour + 3);

        return range($from, $to);
    }
}
