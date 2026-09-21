<?php

declare(strict_types=1);

namespace App\Guide\Filament\Pages;

use App\Guide\Enums\TopicStatus;
use App\Guide\Filament\Concerns\HasTopicTabs;
use App\Guide\Filament\Resources\TopicResource;
use App\Guide\Models\Category;
use App\Guide\Models\Topic;
use App\Guide\Services\TopicAdminService;
use App\Guide\Services\TopicDirectory;
use App\Guide\Support\OutlineAnchors;
use App\Models\Tenant;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * "Gliederung bestaetigen" (#15, design/guide-dashboard.md §5.4
 * Sammelfreigabe): alle Themen im Status outline_pending der gewaehlten
 * Portale, gruppiert nach Kategorie, jede Gliederung aufklappbar.
 *
 * "Gliederungen sperren" sperrt die ausgewaehlten Vorschlaege, "Alle
 * bestaetigen" alle auf einmal — unveraendert, ohne Kosten (die
 * Ersterstellung ist mit dem Thema bereits eingeplant). Anpassen geht im
 * Gliederungs-Editor des Themas.
 */
class ConfirmOutlines extends Page
{
    use HasTopicTabs;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?string $slug = 'themen/gliederung-bestaetigen';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'content.guide.confirm-outlines';

    /**
     * Ausgewaehlte Themen, Schluessel "<tenant-id>-<topic-id>".
     *
     * @var list<string>
     */
    public array $selected = [];

    /**
     * @var array<string, array{name: string, topics: list<array<string, mixed>>}>|null
     */
    protected ?array $groups = null;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->canAccessContentPanel();
    }

    public function getTitle(): string|Htmlable
    {
        return __('Gliederung bestätigen');
    }

    public function getSubheading(): ?string
    {
        return __('Vorschläge des Systems prüfen und sperren. Erst mit der Sperre entsteht der Artikel.');
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [TopicResource::getUrl() => __('Themen'), __('Gliederung bestätigen')];
    }

    /**
     * Themen mit Vorschlag, nach Kategorie gruppiert.
     *
     * @return array<string, array{name: string, topics: list<array<string, mixed>>}>
     */
    public function groups(): array
    {
        if ($this->groups !== null) {
            return $this->groups;
        }

        $network = app(TopicDirectory::class)->isNetworkWide();
        $groups = [];

        foreach (app(TopicDirectory::class)->tenants() as $tenant) {
            foreach ($this->pendingTopics($tenant) as $topic) {
                $slug = $topic['category_slug'] ?? '';
                $groups[$slug]['name'] ??= $topic['category_name'] ?? __('Ohne Kategorie');
                $groups[$slug]['topics'][] = [...$topic, 'show_portal' => $network];
            }
        }

        uasort($groups, fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $this->groups = $groups;
    }

    /**
     * @return list<string>
     */
    public function proposalKeys(): array
    {
        return collect($this->groups())
            ->flatMap(fn (array $group): array => $group['topics'])
            ->filter(fn (array $topic): bool => $topic['outline'] !== [])
            ->pluck('key')
            ->values()
            ->all();
    }

    public function waitingCount(): int
    {
        return collect($this->groups())
            ->flatMap(fn (array $group): array => $group['topics'])
            ->filter(fn (array $topic): bool => $topic['outline'] === [])
            ->count();
    }

    /**
     * Alle Themen einer Kategorie an- oder abwaehlen.
     */
    public function toggleCategory(string $slug): void
    {
        $keys = collect($this->groups()[$slug]['topics'] ?? [])
            ->filter(fn (array $topic): bool => $topic['outline'] !== [])
            ->pluck('key')
            ->all();

        $this->selected = array_values(array_diff($keys, $this->selected) === []
            ? array_diff($this->selected, $keys)
            : array_unique([...$this->selected, ...$keys]));
    }

    public function lockSelectedAction(): Action
    {
        return Action::make('lockSelected')
            ->label(fn (): string => trans_choice('{0} Gliederungen sperren|[1,*] Gliederungen sperren (:count)', count($this->selected), ['count' => count($this->selected)]))
            ->icon('heroicon-o-lock-closed')
            ->color('gray')
            ->disabled(fn (): bool => $this->selected === [])
            ->requiresConfirmation()
            ->modalHeading(__('Ausgewählte Gliederungen sperren'))
            ->modalDescription(fn (): string => $this->confirmText(count($this->selected)))
            ->modalSubmitActionLabel(__('Sperren'))
            ->action(fn () => $this->lock($this->selected));
    }

    public function confirmAllAction(): Action
    {
        return Action::make('confirmAll')
            ->label(fn (): string => trans_choice('{0} Alle bestätigen|[1,*] Alle bestätigen (:count)', count($this->proposalKeys()), ['count' => count($this->proposalKeys())]))
            ->icon('heroicon-o-check-circle')
            ->disabled(fn (): bool => $this->proposalKeys() === [])
            ->requiresConfirmation()
            ->modalHeading(__('Alle Vorschläge bestätigen'))
            ->modalDescription(fn (): string => $this->confirmText(count($this->proposalKeys())))
            ->modalSubmitActionLabel(__('Alle sperren'))
            ->action(fn () => $this->lock($this->proposalKeys()));
    }

    private function confirmText(int $count): string
    {
        return trans_choice(
            '{1} Eine Gliederung wird unverändert gesperrt.|[2,*] :count Gliederungen werden unverändert gesperrt.',
            $count,
            ['count' => $count],
        ).' '.__('Überschriften und Sprungziele sind danach fest; die Artikel entstehen im nächsten Tageslauf. Es entstehen keine zusätzlichen Kosten — die Ersterstellung ist mit dem Thema bereits eingeplant.');
    }

    /**
     * @param  list<string>  $keys
     */
    private function lock(array $keys): void
    {
        // Nur, was hier als Vorschlag angezeigt wird.
        $keys = array_values(array_intersect($keys, $this->proposalKeys()));

        TopicResource::notifyResult(app(TopicAdminService::class)->lockOutlines($keys), __('gesperrt'));

        $this->selected = [];
        $this->groups = null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pendingTopics(Tenant $tenant): array
    {
        try {
            return $tenant->run(function () use ($tenant): array {
                $categories = Category::query()->get(['id', 'name', 'slug'])->keyBy('id');

                return Topic::query()
                    ->where('status', TopicStatus::OUTLINE_PENDING->value)
                    ->orderBy('question')
                    ->get(['id', 'question', 'guide_category_id', 'outline_json', 'updated_at'])
                    ->map(fn (Topic $topic): array => [
                        'key' => TopicDirectory::key($tenant->getKey(), $topic->getKey()),
                        'question' => (string) $topic->question,
                        'tenant_name' => (string) $tenant->name,
                        'category_name' => $categories->get($topic->guide_category_id)?->name,
                        'category_slug' => $categories->get($topic->guide_category_id)?->slug,
                        'outline' => OutlineAnchors::flatten($topic->outline_json),
                        'proposed_at' => $topic->updated_at?->timezone(config('guide.timezone'))->format('d.m.Y'),
                    ])
                    ->all();
            });
        } catch (Throwable $exception) {
            Log::warning('Ratgeber-Dashboard: Portal uebersprungen.', [
                'tenant' => $tenant->getKey(),
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }
}
