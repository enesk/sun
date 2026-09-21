<?php

declare(strict_types=1);

namespace App\Guide\Filament\Pages;

use App\Guide\Enums\RunDisplay;
use App\Guide\Filament\Concerns\HasHistoryTabs;
use App\Guide\Services\RunOverviewService;
use App\Guide\Services\TopicDirectory;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;

/**
 * Verlauf › Laeufe (#33, design/guide-dashboard.md §7.1): alle Laeufe der
 * gewaehlten Portale im Zeitraum, 50 je Seite — Zeit, Portal, Thema, Art,
 * Ergebnis, Dauer, Kosten. Darueber ein Kleinbalken je Tag der letzten
 * 14 Tage; ein Klick setzt den Zeitraum auf diesen Tag.
 *
 * Filter in der URL (§1.3): zeitraum (heute|7|30|frei, Vorgabe 7), von/bis
 * bei "frei", lauf (Anzeigewert nach §2.2), seite.
 */
class RunHistory extends Page
{
    use HasHistoryTabs;

    public const PER_PAGE = 50;

    public const BAR_DAYS = 14;

    /** @var array<int|string, string> Schluessel = URL-Wert von zeitraum */
    public const PERIODS = ['heute' => 'Heute', '7' => '7 Tage', '30' => '30 Tage', 'frei' => 'Frei'];

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'verlauf';

    protected string $view = 'content.guide.run-history';

    #[Url(as: 'zeitraum')]
    public string $period = '7';

    #[Url(as: 'von')]
    public ?string $from = null;

    #[Url(as: 'bis')]
    public ?string $until = null;

    #[Url(as: 'lauf')]
    public ?string $display = null;

    #[Url(as: 'seite')]
    public int $pageNumber = 1;

    public static function getNavigationLabel(): string
    {
        return __('Verlauf');
    }

    /**
     * "Verlauf" bleibt unter allen drei Reitern markiert.
     *
     * @return array<string>
     */
    public static function getNavigationItemActiveRoutePattern(): string|array
    {
        return [static::getRouteName(), ArticleVersions::getRouteName(), Costs::getRouteName()];
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->canAccessContentPanel();
    }

    public function getTitle(): string|Htmlable
    {
        return __('Läufe');
    }

    public function getSubheading(): ?string
    {
        [$from, $until] = $this->range();

        return $from->isSameDay($until)
            ? __('Läufe vom :date', ['date' => $from->translatedFormat('j. F Y')])
            : __('Läufe vom :from bis :until', ['from' => $from->format('d.m.Y'), 'until' => $until->format('d.m.Y')]);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['period', 'from', 'until', 'display'], true)) {
            $this->pageNumber = 1;
        }
    }

    /**
     * Kleinbalken: Zeitraum auf genau diesen Tag.
     */
    public function selectDay(string $date): void
    {
        $day = $this->parseDate($date);

        if ($day === null) {
            return;
        }

        $this->period = 'frei';
        $this->from = $day->toDateString();
        $this->until = $day->toDateString();
        $this->pageNumber = 1;
    }

    public function resetFilters(): void
    {
        $this->period = '7';
        $this->from = null;
        $this->until = null;
        $this->display = null;
        $this->pageNumber = 1;
    }

    public function isFiltered(): bool
    {
        return $this->period !== '7' || $this->display !== null;
    }

    public function goToPage(int $page): void
    {
        $this->pageNumber = max(1, min($page, $this->lastPage()));
    }

    /**
     * Zeitraum als Kalendertage in guide.timezone; "frei" ohne gueltige
     * Daten faellt auf 7 Tage zurueck, von > bis wird getauscht.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function range(): array
    {
        $today = app(RunOverviewService::class)->today();

        return match ($this->period) {
            'heute' => [$today, $today->copy()],
            '30' => [$today->copy()->subDays(29), $today],
            'frei' => $this->customRange($today),
            default => [$today->copy()->subDays(6), $today],
        };
    }

    /**
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function runs(): array
    {
        [$from, $until] = $this->range();
        $displays = $this->display !== null && RunDisplay::tryFrom($this->display) !== null ? [$this->display] : [];

        return app(RunOverviewService::class)->runs($this->directory()->tenants(), $from, $until, $displays, $this->pageNumber, self::PER_PAGE);
    }

    /**
     * @return list<array{date: string, counts: array<string, int>, total: int, checked: int}>
     */
    public function days(): array
    {
        return app(RunOverviewService::class)->dailyCounts($this->directory()->tenants(), self::BAR_DAYS);
    }

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->runs()['total'] / self::PER_PAGE));
    }

    public function isSelectedDay(string $date): bool
    {
        [$from, $until] = $this->range();

        return $from->toDateString() === $date && $until->toDateString() === $date;
    }

    public function canSeeCosts(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->canSeeContentCosts();
    }

    public function isNetworkWide(): bool
    {
        return $this->directory()->isNetworkWide();
    }

    public static function durationLabel(?int $seconds): string
    {
        if ($seconds === null) {
            return '–';
        }

        if ($seconds < 60) {
            return __(':s s', ['s' => $seconds]);
        }

        return $seconds < 3600
            ? __(':m min', ['m' => intdiv($seconds, 60)])
            : __(':h Std. :m min', ['h' => intdiv($seconds, 3600), 'm' => intdiv($seconds % 3600, 60)]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function customRange(Carbon $today): array
    {
        $from = $this->parseDate($this->from) ?? $today->copy()->subDays(6);
        $until = $this->parseDate($this->until) ?? $today->copy();

        return $from->lessThanOrEqualTo($until) ? [$from, $until] : [$until, $from];
    }

    private function parseDate(?string $value): ?Carbon
    {
        if (blank($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value) !== 1) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', (string) $value, $this->timezone())?->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function timezone(): string
    {
        return (string) config('guide.timezone', 'Europe/Berlin');
    }

    private function directory(): TopicDirectory
    {
        return app(TopicDirectory::class);
    }
}
