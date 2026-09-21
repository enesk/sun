<?php

declare(strict_types=1);

namespace App\Guide\Filament\Pages;

use App\Guide\Filament\Concerns\HasHistoryTabs;
use App\Guide\Filament\Widgets\CostsByDayChart;
use App\Guide\Services\CostReport;
use App\Guide\Services\TopicDirectory;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Verlauf › Kosten (#16, design/guide-dashboard.md §7.3), nur fuer Inhaber:
 * heute, Monat, Prognose, je Portal, je Thema, Anzahl Web-Suchen.
 *
 * Alle Zahlen aus llm_usage_logs (CostReport); das Dashboard ruft keinen
 * Anbieter live ab.
 */
class Costs extends Page
{
    use HasHistoryTabs;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $slug = 'verlauf/kosten';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'content.guide.costs';

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->canSeeContentCosts();
    }

    public function getTitle(): string|Htmlable
    {
        return __('Kosten');
    }

    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        return app(CostReport::class)->report(app(TopicDirectory::class)->tenants());
    }

    public function isNetworkWide(): bool
    {
        return app(TopicDirectory::class)->isNetworkWide();
    }

    public function chartWidget(): string
    {
        return CostsByDayChart::class;
    }
}
