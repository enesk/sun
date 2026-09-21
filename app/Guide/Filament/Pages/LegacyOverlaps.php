<?php

declare(strict_types=1);

namespace App\Guide\Filament\Pages;

use App\Guide\Filament\Concerns\HasTopicTabs;
use App\Guide\Filament\Resources\TopicResource;
use App\Guide\Legacy\LegacyOverlapResolver;
use App\Guide\Models\Central\GuideAlert;
use App\Guide\Models\Topic;
use App\Guide\Services\GuidePageData;
use App\Guide\Services\TopicDirectory;
use App\Models\Portal\Post;
use App\Models\Tenant;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Url;
use LogicException;
use Throwable;

/**
 * Reiter "Altartikel" unter Themen (#25, design/guide-dashboard.md §5.6):
 * offene Alarme legacy_overlap der gewaehlten Portale, gruppiert nach Thema.
 *
 * Welche Aktion eine Zeile anbietet, entscheidet der aktuelle Zustand im
 * Portal (LegacyOverlapResolver::publishedArticle(), hier gebuendelt je
 * Portal), nie context_json.can_* vom Meldezeitpunkt. Alle Entscheidungen
 * laufen ueber LegacyOverlapResolver; der erledigt den Alarm selbst.
 *
 * Eigene Liste statt Filament-Tabelle wie "Gliederung bestaetigen": die
 * Gruppenkoepfe sind echte Ueberschriften (h3) und der Fokus laesst sich nach
 * dem Verschwinden einer Zeile gezielt setzen (§5.6.9).
 */
class LegacyOverlaps extends Page
{
    use HasTopicTabs;

    public const STATE_ADOPT = 'adopt';

    public const STATE_REDIRECT = 'redirect';

    public const STATE_OBSOLETE = 'obsolete';

    private const FOCUS_FIRST_GROUP = 'first-group';

    /** Unter diesem Wert steht "schwach" unter der Aehnlichkeit (§5.6.2). */
    public const WEAK_SIMILARITY = 70;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-duplicate';

    protected static ?string $slug = 'themen/altartikel';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'content.guide.legacy-overlaps';

    /**
     * Sortierung der Gruppen und Zeilen: similarity oder reported.
     */
    #[Url(as: 'sortierung')]
    public string $sort = 'similarity';

    /**
     * Ausgewaehlte Alarme fuer "Als keine Ueberschneidung markieren".
     *
     * @var list<int>
     */
    public array $selected = [];

    /**
     * @var array<string, array<string, mixed>>|null
     */
    protected ?array $groups = null;

    /**
     * Jede GuideRole (Inhaber, Redaktion) und Administratoren ohne Rolle
     * (§5.6.1); ohne Rolle 403.
     */
    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->canAccessContentPanel();
    }

    public function getTitle(): string|Htmlable
    {
        return __('Altartikel');
    }

    public function getSubheading(): ?string
    {
        return __('Diese Themen behandeln dieselbe Frage wie ein bereits veröffentlichter Beitrag. Entscheiden Sie je Paar, damit es nicht zwei Seiten zum selben Thema gibt.');
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [TopicResource::getUrl() => __('Themen'), __('Altartikel')];
    }

    /**
     * Offene Paare der gewaehlten Portale (Zaehlmarke am Reiter).
     */
    public static function openCount(): int
    {
        return static::openAlerts()->count();
    }

    /**
     * Themen mit mindestens einem offenen Paar (Hinweiszeile auf Heute).
     */
    public static function openTopicCount(): int
    {
        return static::openAlerts()
            ->map(fn (GuideAlert $alert): string => $alert->tenant_id.'-'.self::topicId($alert))
            ->unique()
            ->count();
    }

    /**
     * @return Collection<int, GuideAlert>
     */
    public static function openAlerts(): Collection
    {
        $tenantIds = app(TopicDirectory::class)->tenants()->keys()->all();

        if ($tenantIds === []) {
            return collect();
        }

        return GuideAlert::query()
            ->open()
            ->where('key', GuideAlert::KEY_LEGACY_OVERLAP)
            ->whereIn('tenant_id', $tenantIds)
            ->get()
            ->toBase();
    }

    /**
     * Gruppen je Thema und Portal, sortiert nach $sort.
     *
     * @return array<string, array<string, mixed>>
     */
    public function groups(): array
    {
        if ($this->groups !== null) {
            return $this->groups;
        }

        $directory = app(TopicDirectory::class);
        $network = $directory->isNetworkWide();
        $groups = [];

        foreach (static::openAlerts()->groupBy('tenant_id') as $tenantId => $alerts) {
            $tenant = $directory->tenant((int) $tenantId);

            if ($tenant === null) {
                continue;
            }

            foreach ($this->tenantRows($tenant, $alerts) as $row) {
                $groupKey = TopicDirectory::key($tenant->getKey(), $row['topic_id']);

                $groups[$groupKey] ??= [
                    'key' => $groupKey,
                    'heading_id' => "overlap-group-{$groupKey}",
                    'question' => $row['question'],
                    'topic_exists' => $row['topic_exists'],
                    'detail_url' => $row['topic_exists'] ? TopicResource::detailUrl($groupKey) : null,
                    'tenant_name' => (string) $tenant->name,
                    'show_portal' => $network,
                    'target_path' => $row['target_path'],
                    'target_url' => $row['target_url'],
                    'rows' => [],
                ];
                $groups[$groupKey]['rows'][] = $row;
            }
        }

        $value = $this->sort === 'reported'
            ? fn (array $row): string => (string) $row['last_seen_sort']
            : fn (array $row): float => (float) $row['similarity'];

        foreach ($groups as &$group) {
            $group['rows'] = collect($group['rows'])->sortByDesc($value)->values()->all();
        }
        unset($group);

        uasort($groups, fn (array $a, array $b): int => $value($b['rows'][0]) <=> $value($a['rows'][0]));

        return $this->groups = $groups;
    }

    /**
     * @return list<int>
     */
    public function alertIds(): array
    {
        return collect($this->groups())
            ->flatMap(fn (array $group): array => array_column($group['rows'], 'id'))
            ->values()
            ->all();
    }

    public function sortBy(string $sort): void
    {
        $this->sort = in_array($sort, ['similarity', 'reported'], true) ? $sort : 'similarity';
        $this->groups = null;
    }

    public function toggleAll(): void
    {
        $ids = $this->alertIds();

        $this->selected = array_diff($ids, $this->selected) === [] ? [] : $ids;
    }

    public function adoptAction(): Action
    {
        return $this->decisionAction('adopt')
            ->label(__('Adresse übernehmen …'))
            ->color('content')
            ->extraAttributes(fn (array $arguments): array => [
                'aria-label' => __('Adresse von „:title“ für Thema „:question“ übernehmen', [
                    'title' => $this->row($arguments)['post_title'] ?? '',
                    'question' => $this->row($arguments)['question'] ?? '',
                ]),
            ])
            ->modalHeading(__('Adresse des Altartikels übernehmen?'))
            ->modalContent(fn (array $arguments): View => $this->dialog('adopt', $arguments))
            ->modalSubmitActionLabel(__('Adresse übernehmen'))
            ->action(fn (array $arguments) => $this->decide($arguments, LegacyOverlapResolver::DECISION_ADOPT_SLUG));
    }

    public function forwardAction(): Action
    {
        return $this->decisionAction('forward')
            ->label(__('Weiterleiten (301) …'))
            ->color('content')
            ->extraAttributes(fn (array $arguments): array => [
                'aria-label' => __('„:title“ per 301 auf Thema „:question“ weiterleiten', [
                    'title' => $this->row($arguments)['post_title'] ?? '',
                    'question' => $this->row($arguments)['question'] ?? '',
                ]),
            ])
            ->modalHeading(__('Altartikel dauerhaft weiterleiten?'))
            ->modalContent(fn (array $arguments): View => $this->dialog('redirect', $arguments))
            ->modalSubmitActionLabel(__('Weiterleiten'))
            // Archiviert eine oeffentliche Seite (§5.6.4).
            ->modalSubmitAction(fn (Action $action): Action => $action->color('status-failed'))
            ->action(fn (array $arguments) => $this->decide($arguments, LegacyOverlapResolver::DECISION_REDIRECT));
    }

    public function dismissAction(): Action
    {
        return $this->decisionAction('dismiss')
            ->label(__('Keine Überschneidung'))
            ->link()
            ->color('gray')
            ->extraAttributes(fn (array $arguments): array => [
                'aria-label' => __('„:title“ und Thema „:question“: keine Überschneidung', [
                    'title' => $this->row($arguments)['post_title'] ?? '',
                    'question' => $this->row($arguments)['question'] ?? '',
                ]),
            ])
            ->modalHeading(__('Keine Überschneidung?'))
            ->modalDescription(__('Das Paar verschwindet aus der Liste und wird bei künftigen Abgleichen nicht erneut gemeldet. Beide Seiten bleiben unverändert.'))
            ->modalSubmitActionLabel(__('Bestätigen'))
            ->modalSubmitAction(fn (Action $action): Action => $action->color('gray'))
            ->action(fn (array $arguments) => $this->decide($arguments, LegacyOverlapResolver::DECISION_DISMISSED));
    }

    /**
     * "Erledigen" bei ueberholten Paaren: ohne Rueckfrage, decision =
     * obsolete — der naechste Abgleich darf das Paar wieder melden.
     */
    public function obsoleteAction(): Action
    {
        return Action::make('obsolete')
            ->label(__('Erledigen'))
            ->link()
            ->color('gray')
            ->size('sm')
            ->extraAttributes(fn (array $arguments): array => [
                'aria-label' => __('Überholtes Paar „:title“ erledigen', ['title' => $this->row($arguments)['post_title'] ?? '']),
            ])
            ->action(fn (array $arguments) => $this->decide($arguments, LegacyOverlapResolver::DECISION_OBSOLETE));
    }

    public function dismissSelectedAction(): Action
    {
        return Action::make('dismissSelected')
            ->label(fn (): string => trans_choice('{0} Als keine Überschneidung markieren|[1,*] Als keine Überschneidung markieren (:count)', count($this->selected), ['count' => count($this->selected)]))
            ->color('gray')
            ->size('sm')
            ->disabled(fn (): bool => $this->selected === [])
            ->requiresConfirmation()
            ->modalHeading(fn (): string => trans_choice('{1} Ein Paar als keine Überschneidung markieren?|[2,*] :count Paare als keine Überschneidung markieren?', count($this->selected), ['count' => count($this->selected)]))
            ->modalDescription(__('Die Paare verschwinden aus der Liste und werden bei künftigen Abgleichen nicht erneut gemeldet. Beide Seiten bleiben jeweils unverändert.'))
            ->modalSubmitActionLabel(__('Bestätigen'))
            ->modalSubmitAction(fn (Action $action): Action => $action->color('gray'))
            ->modalCancelAction(fn (Action $action): Action => $action->extraAttributes(['autofocus' => 'autofocus']))
            ->action(fn () => $this->dismissSelected());
    }

    /**
     * Gemeinsame Bauform der Aktionen mit Rueckfrage (§5.6.4): Modal lg,
     * Fokus beim Oeffnen auf "Abbrechen".
     */
    private function decisionAction(string $name): Action
    {
        return Action::make($name)
            ->size('sm')
            ->requiresConfirmation()
            ->modalWidth(Width::Large)
            ->modalAlignment(Alignment::Start)
            ->modalCancelAction(fn (Action $action): Action => $action->extraAttributes(['autofocus' => 'autofocus']));
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function decide(array $arguments, string $decision): void
    {
        $this->authorizeDecision();

        $alert = GuideAlert::query()->find((int) ($arguments['alert'] ?? 0));
        $row = $this->row($arguments);
        $next = $this->focusTargetAfter((int) ($arguments['alert'] ?? 0));

        // Doppelklick oder schon anderswo erledigt: still neu rendern (§5.6.5).
        if ($alert === null || $alert->key !== GuideAlert::KEY_LEGACY_OVERLAP || $alert->status !== GuideAlert::STATUS_OPEN) {
            $this->refreshList($next);

            return;
        }

        $tenant = app(TopicDirectory::class)->tenant((int) $alert->tenant_id);

        if ($tenant === null) {
            abort(403);
        }

        $topicId = self::topicId($alert);
        $postId = (int) ($alert->context_json['post_id'] ?? 0);
        $resolver = app(LegacyOverlapResolver::class);

        try {
            match ($decision) {
                LegacyOverlapResolver::DECISION_ADOPT_SLUG => $resolver->adoptSlug($tenant, $topicId, $postId),
                LegacyOverlapResolver::DECISION_REDIRECT => $resolver->redirect($tenant, $topicId, $postId),
                default => $resolver->dismiss($tenant, $topicId, $postId, $decision),
            };
        } catch (LogicException $exception) {
            // Meldung unveraendert; die Zeile zeigt danach die jetzt passende Aktion.
            Notification::make()->title(__('Nicht möglich'))->body($exception->getMessage())->danger()->send();
            $this->refreshList($row['row_id'] ?? $next);

            return;
        } catch (ModelNotFoundException) {
            Notification::make()->title(__('Nicht möglich'))->body(__('Thema oder Beitrag gibt es im Portal nicht mehr.'))->danger()->send();
            $this->refreshList($row['row_id'] ?? $next);

            return;
        } catch (Throwable $exception) {
            Log::warning('Ratgeber-Dashboard: Altartikel-Entscheidung fehlgeschlagen.', [
                'alert' => $alert->getKey(),
                'decision' => $decision,
                'message' => $exception->getMessage(),
            ]);
            Notification::make()
                ->title(__('Nicht möglich'))
                ->body(__('Portal „:name“ ist gerade nicht erreichbar. Bitte später erneut versuchen.', ['name' => $tenant->name]))
                ->danger()
                ->send();
            $this->refreshList($row['row_id'] ?? $next);

            return;
        }

        Notification::make()->title($this->successMessage($decision, $row))->success()->send();
        $this->refreshList($next);
    }

    private function dismissSelected(): void
    {
        $this->authorizeDecision();

        $directory = app(TopicDirectory::class);
        $resolver = app(LegacyOverlapResolver::class);
        $states = collect($this->groups())
            ->flatMap(fn (array $group): array => $group['rows'])
            ->mapWithKeys(fn (array $row): array => [$row['id'] => $row['state']]);
        $done = 0;
        $failed = [];

        $alerts = GuideAlert::query()
            ->open()
            ->where('key', GuideAlert::KEY_LEGACY_OVERLAP)
            ->whereKey(array_map('intval', $this->selected))
            ->get();

        foreach ($alerts as $alert) {
            // Rechte je Datensatz: nur Portale aus der Zuordnung des Kontos.
            $tenant = $directory->tenant((int) $alert->tenant_id);

            if ($tenant === null) {
                $failed[] = __('Portal :id', ['id' => $alert->tenant_id]);

                continue;
            }

            $decision = $states->get($alert->getKey()) === self::STATE_OBSOLETE
                ? LegacyOverlapResolver::DECISION_OBSOLETE
                : LegacyOverlapResolver::DECISION_DISMISSED;

            try {
                $resolver->dismiss($tenant, self::topicId($alert), (int) ($alert->context_json['post_id'] ?? 0), $decision);
                $done++;
            } catch (Throwable $exception) {
                Log::warning('Ratgeber-Dashboard: Altartikel-Paar nicht vermerkt.', [
                    'alert' => $alert->getKey(),
                    'message' => $exception->getMessage(),
                ]);
                $failed[] = (string) $tenant->name;
            }
        }

        if ($done > 0) {
            Notification::make()
                ->title(trans_choice('{1} Ein Paar als keine Überschneidung vermerkt.|[2,*] :count Paare als keine Überschneidung vermerkt.', $done, ['count' => $done]))
                ->success()
                ->send();
        }

        if ($failed !== []) {
            Notification::make()
                ->title(trans_choice('{1} Ein Paar konnte nicht vermerkt werden|[2,*] :count Paare konnten nicht vermerkt werden', count($failed), ['count' => count($failed)]))
                ->body(implode(', ', array_unique($failed)))
                ->danger()
                ->send();
        }

        $this->selected = [];
        $this->refreshList(self::FOCUS_FIRST_GROUP);
    }

    /**
     * Serverseitig bei jedem Klick (§5.6.1), nicht nur ueber die Sichtbarkeit.
     */
    private function authorizeDecision(): void
    {
        if (! static::canAccess()) {
            abort(403);
        }
    }

    /**
     * Liste neu lesen, Auswahl bereinigen, Fokus setzen (§5.6.9).
     */
    private function refreshList(string $focus): void
    {
        $this->groups = null;
        app(TopicDirectory::class)->forget();
        $this->selected = array_values(array_intersect($this->selected, $this->alertIds()));

        $groups = $this->groups();

        $focus = match (true) {
            $groups === [] => 'overlap-empty',
            $focus === self::FOCUS_FIRST_GROUP => reset($groups)['heading_id'],
            default => $focus,
        };

        // Fehlt das Ziel im neuen Stand, faellt die Seite auf den ersten
        // Gruppenkopf bzw. die Leer-Meldung zurueck (legacy-overlaps.blade.php).
        $this->dispatch('guide-overlap-focus', target: $focus);
    }

    /**
     * Fokusziel, falls die Zeile verschwindet: naechste Zeile der Gruppe,
     * sonst naechster Gruppenkopf, sonst vorheriger, sonst Leer-Meldung.
     */
    private function focusTargetAfter(int $alertId): string
    {
        $groups = array_values($this->groups());

        foreach ($groups as $index => $group) {
            $ids = array_column($group['rows'], 'id');
            $position = array_search($alertId, $ids, true);

            if ($position === false) {
                continue;
            }

            return match (true) {
                isset($ids[$position + 1]) => "overlap-row-{$ids[$position + 1]}",
                isset($ids[$position - 1]) => "overlap-row-{$ids[$position - 1]}",
                isset($groups[$index + 1]) => $groups[$index + 1]['heading_id'],
                isset($groups[$index - 1]) => $groups[$index - 1]['heading_id'],
                default => 'overlap-empty',
            };
        }

        return 'overlap-empty';
    }

    /**
     * @param  array<string, mixed>|null  $row
     */
    private function successMessage(string $decision, ?array $row): string
    {
        return match ($decision) {
            LegacyOverlapResolver::DECISION_ADOPT_SLUG => __('Adresse übernommen. „:question“ erscheint künftig unter :path.', [
                'question' => $row['question'] ?? '',
                'path' => $row['post_path'] ?? '',
            ]),
            LegacyOverlapResolver::DECISION_REDIRECT => __('Weitergeleitet: :from → :to.', [
                'from' => $row['post_path'] ?? '',
                'to' => $row['target_path'] ?? '',
            ]),
            LegacyOverlapResolver::DECISION_OBSOLETE => __('Erledigt.'),
            default => __('Als keine Überschneidung vermerkt.'),
        };
    }

    /**
     * Inhalt der Rueckfrage zu Uebernehmen bzw. Weiterleiten (§5.6.4).
     *
     * @param  array<string, mixed>  $arguments
     */
    private function dialog(string $kind, array $arguments): View
    {
        return view('content.guide.partials.legacy-overlap-dialog', [
            'kind' => $kind,
            'row' => $this->row($arguments),
        ]);
    }

    /**
     * Zeile zu den Argumenten einer Aktion ({alert: id}), aktueller Stand.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>|null
     */
    private function row(array $arguments): ?array
    {
        $id = (int) ($arguments['alert'] ?? 0);

        foreach ($this->groups() as $group) {
            foreach ($group['rows'] as $row) {
                if ($row['id'] === $id) {
                    return $row;
                }
            }
        }

        return null;
    }

    private static function topicId(GuideAlert $alert): int
    {
        return (int) ($alert->guide_topic_id ?? $alert->context_json['topic_id'] ?? 0);
    }

    /**
     * Zeilen eines Portals mit dem Zustand von jetzt: ein Satz Abfragen je
     * Portal, gleiche Regel wie LegacyOverlapResolver::publishedArticle().
     *
     * @param  Collection<int, GuideAlert>  $alerts
     * @return list<array<string, mixed>>
     */
    private function tenantRows(Tenant $tenant, Collection $alerts): array
    {
        try {
            return $tenant->run(function () use ($tenant, $alerts): array {
                $topicIds = $alerts->map(fn (GuideAlert $alert): int => self::topicId($alert))->unique()->values()->all();
                $postIds = $alerts->map(fn (GuideAlert $alert): int => (int) ($alert->context_json['post_id'] ?? 0))->unique()->values()->all();

                $topics = Topic::query()->whereKey($topicIds)->get(['id', 'question', 'article_id'])->keyBy('id');
                $published = Post::query()
                    ->published()
                    ->whereKey($topics->pluck('article_id')->filter()->all())
                    ->get(['id', 'slug'])
                    ->keyBy('id');
                $posts = Post::query()->whereKey($postIds)->get(['id', 'title', 'slug', 'status', 'guide_topic_id'])->keyBy('id');
                $owners = Topic::query()
                    ->whereKey($posts->pluck('guide_topic_id')->filter()->all())
                    ->pluck('question', 'id');

                return $alerts->map(function (GuideAlert $alert) use ($tenant, $topics, $published, $posts, $owners): array {
                    $context = $alert->context_json ?? [];
                    $topicId = self::topicId($alert);
                    $topic = $topics->get($topicId);
                    $post = $posts->get((int) ($context['post_id'] ?? 0));
                    $target = $topic?->article_id !== null ? $published->get($topic->article_id) : null;
                    $postPath = $post !== null ? route('guide.show', $post->slug, false) : (string) ($context['post_url'] ?? '');
                    $targetPath = $target !== null ? route('guide.show', $target->slug, false) : null;

                    [$state, $reason] = match (true) {
                        $topic === null => [self::STATE_OBSOLETE, __('Überholt — das Thema gibt es nicht mehr.')],
                        $post === null || $post->status !== Post::STATUS_PUBLISHED => [self::STATE_OBSOLETE, __('Überholt — der Beitrag ist nicht mehr veröffentlicht.')],
                        $post->guide_topic_id !== null => [self::STATE_OBSOLETE, __('Überholt — gehört inzwischen zum Thema „:question“.', [
                            'question' => GuidePageData::replaceYear((string) $owners->get($post->guide_topic_id, '')),
                        ])],
                        $target !== null => [self::STATE_REDIRECT, null],
                        default => [self::STATE_ADOPT, null],
                    };

                    return [
                        'id' => (int) $alert->getKey(),
                        'row_id' => "overlap-row-{$alert->getKey()}",
                        'tenant_name' => (string) $tenant->name,
                        'topic_id' => $topicId,
                        'topic_exists' => $topic !== null,
                        'question' => $topic !== null
                            ? GuidePageData::replaceYear((string) $topic->question)
                            : (string) ($context['topic_question'] ?? ''),
                        'post_title' => (string) ($post->title ?? $context['post_title'] ?? ''),
                        'post_path' => $postPath,
                        'post_url' => $this->portalUrl($tenant, $postPath),
                        'similarity' => (float) ($context['similarity'] ?? 0),
                        'last_seen' => $alert->last_seen_at?->timezone(config('guide.timezone'))->format('d.m.Y'),
                        'last_seen_sort' => $alert->last_seen_at?->toIso8601String() ?? '',
                        'occurrences' => (int) $alert->occurrences,
                        'state' => $state,
                        'obsolete_reason' => $reason,
                        'target_path' => $targetPath,
                        'target_url' => $targetPath !== null ? $this->portalUrl($tenant, $targetPath) : null,
                        // Unveroeffentlichter Entwurf des Themas, den "Adresse uebernehmen" loest.
                        'has_draft' => $topic?->article_id !== null && $target === null && (int) $topic->article_id !== (int) ($post->id ?? 0),
                    ];
                })->values()->all();
            });
        } catch (Throwable $exception) {
            Log::warning('Ratgeber-Dashboard: Portal uebersprungen.', [
                'tenant' => $tenant->getKey(),
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    private function portalUrl(Tenant $tenant, string $path): ?string
    {
        if (blank($tenant->domain) || $path === '') {
            return null;
        }

        $scheme = app()->environment('production') ? 'https' : 'http';

        return "{$scheme}://{$tenant->domain}{$path}";
    }
}
