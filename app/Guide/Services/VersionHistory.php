<?php

declare(strict_types=1);

namespace App\Guide\Services;

use App\Guide\Models\ArticleDetail;
use App\Guide\Models\ArticleVersion;
use App\Guide\Models\Topic;
use App\Guide\Models\TopicRun;
use App\Guide\Publishing\GuidePublisher;
use App\Guide\Support\GuidePreviewLink;
use App\Guide\Support\OutlineAnchors;
use App\Guide\Writing\HtmlAssembler;
use App\Models\Portal\Post;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Versionshistorie der Ratgeber-Artikel (#16, design/guide-dashboard.md §5.5,
 * §7.2, §8.3): Liste der Fassungen, Vergleich zweier Fassungen abschnittsweise
 * und Rollback ueber GuidePublisher::rollback().
 */
class VersionHistory
{
    public const RECENT_DAYS = 30;

    private const RECENT_LIMIT = 200;

    public function __construct(
        private readonly HtmlAssembler $html,
        private readonly ArticleDiffRenderer $diff,
        private readonly GuidePreviewLink $previewLink,
        private readonly GuidePublisher $publisher,
    ) {}

    /**
     * Veroeffentlichte Fassungen der letzten Tage ueber alle Portale, neueste
     * zuerst (§7.2: "Was hat sich diese Woche geaendert?").
     *
     * @param  Collection<int, Tenant>  $tenants
     * @return list<array<string, mixed>>
     */
    public function recent(Collection $tenants, int $days = self::RECENT_DAYS): array
    {
        $since = Carbon::now()->subDays($days);

        return $tenants
            ->flatMap(fn (Tenant $tenant): array => $this->read($tenant, fn (): array => ArticleVersion::query()
                ->whereNotNull('published_at')
                ->where('published_at', '>=', $since)
                ->with('topic:id,question')
                ->latest('published_at')
                ->limit(self::RECENT_LIMIT)
                ->get(['id', 'guide_topic_id', 'guide_topic_run_id', 'version', 'title', 'change_summary', 'published_at'])
                ->map(fn (ArticleVersion $version): array => [
                    'tenant_id' => (int) $tenant->getKey(),
                    'tenant_name' => (string) $tenant->name,
                    'topic_key' => TopicDirectory::key($tenant->getKey(), (int) $version->guide_topic_id),
                    'question' => (string) ($version->topic?->getAttribute('question') ?? $version->title),
                    'version' => (int) $version->version,
                    'summary' => $version->change_summary,
                    'is_rollback' => $version->guide_topic_run_id === null,
                    'published_at' => $version->published_at?->toIso8601String(),
                ])
                ->all()) ?? [])
            ->sortByDesc('published_at')
            ->take(self::RECENT_LIMIT)
            ->values()
            ->all();
    }

    /**
     * Alle Fassungen eines Themas, neueste zuerst, mit der gerade
     * veroeffentlichten markiert.
     *
     * @return array<string, mixed>|null
     */
    public function forTopic(Tenant $tenant, int $topicId): ?array
    {
        return $this->read($tenant, function () use ($tenant, $topicId): ?array {
            /** @var Topic|null $topic */
            $topic = Topic::query()->with('article')->find($topicId);

            if ($topic === null) {
                return null;
            }

            $currentId = $topic->article_id !== null
                ? ArticleDetail::query()->where('article_id', $topic->article_id)->value('published_version_id')
                : null;

            $versions = ArticleVersion::query()
                ->where(fn ($query) => $query
                    ->where('guide_topic_id', $topic->getKey())
                    ->when($topic->article_id !== null, fn ($inner) => $inner->orWhere('article_id', $topic->article_id)))
                ->with('run:id,mode,status')
                ->orderByDesc('version')
                ->orderByDesc('id')
                ->get(['id', 'article_id', 'guide_topic_run_id', 'version', 'title', 'change_summary', 'published_at', 'created_at']);

            return [
                'tenant_id' => (int) $tenant->getKey(),
                'topic_id' => (int) $topic->getKey(),
                'question' => (string) $topic->question,
                'has_article' => $topic->article !== null,
                'article_url' => $topic->article !== null ? $this->portalUrl($tenant, route('guide.show', (string) $topic->article->getAttribute('slug'), false)) : null,
                'current_id' => $currentId !== null ? (int) $currentId : null,
                'versions' => $versions->map(fn (ArticleVersion $version): array => [
                    'id' => (int) $version->getKey(),
                    'version' => (int) $version->version,
                    'title' => (string) $version->title,
                    'summary' => $version->change_summary,
                    'published_at' => $version->published_at?->toIso8601String(),
                    'created_at' => $version->created_at?->toIso8601String(),
                    'is_current' => $currentId !== null && (int) $currentId === (int) $version->getKey(),
                    'is_rollback' => $version->guide_topic_run_id === null,
                    'run_status' => $version->run instanceof TopicRun ? $version->run->status->value : null,
                    // Zurueckholen nur auf eine Fassung, die schon einmal online war —
                    // ein Entwurf aus der Pruef-Queue darf die Pruefung nicht umgehen.
                    'can_rollback' => $version->article_id !== null && $version->published_at !== null && $topic->article !== null && (int) $currentId !== (int) $version->getKey(),
                ])->values()->all(),
            ];
        });
    }

    /**
     * Abschnittsweiser Vergleich zweier Fassungen (§8.3).
     *
     * @return list<array{heading: string, level: int, diff: array<string, mixed>}>|null
     */
    public function compare(Tenant $tenant, int $topicId, int $fromId, int $toId): ?array
    {
        return $this->read($tenant, function () use ($topicId, $fromId, $toId): ?array {
            $topic = Topic::query()->find($topicId);
            $from = ArticleVersion::query()->where('guide_topic_id', $topicId)->find($fromId);
            $to = ArticleVersion::query()->where('guide_topic_id', $topicId)->find($toId);

            if ($topic === null || $from === null || $to === null) {
                return null;
            }

            $before = $this->html->split((string) $from->body_html);
            $after = $this->html->split((string) $to->body_html);
            $rows = [];

            foreach (OutlineAnchors::flatten($topic->outline_json) as $entry) {
                $id = (string) $entry['id'];
                $old = isset($before[$id]) ? $this->html->body($before[$id]) : '';
                $new = isset($after[$id]) ? $this->html->body($after[$id]) : '';

                if (trim($old) === trim($new)) {
                    continue;
                }

                $rows[] = ['heading' => (string) $entry['text'], 'level' => (int) $entry['level'], 'diff' => $this->diff->diff($old, $new)];
            }

            foreach (['short_answer' => __('Kurzantwort'), 'meta_description' => __('Beschreibung')] as $field => $label) {
                if (trim((string) $from->{$field}) !== trim((string) $to->{$field})) {
                    $rows[] = ['heading' => $label, 'level' => 2, 'diff' => $this->diff->diff('<p>'.e((string) $from->{$field}).'</p>', '<p>'.e((string) $to->{$field}).'</p>')];
                }
            }

            return $rows;
        });
    }

    public function previewUrl(Tenant $tenant, int $versionId): ?string
    {
        return $this->read($tenant, function () use ($tenant, $versionId): ?string {
            $version = ArticleVersion::query()->find($versionId);

            return $version !== null ? $this->previewLink->for($tenant, $version) : null;
        });
    }

    /**
     * Rollback auf eine fruehere Fassung ueber den Publisher; laeuft im
     * Tenant-Kontext (Seiten-Cache, IndexNow). Liefert die neue Fassungsnummer.
     */
    public function rollback(Tenant $tenant, int $topicId, int $versionId, ?string $note = null): int
    {
        return $tenant->run(function () use ($topicId, $versionId, $note): int {
            /** @var Topic|null $topic */
            $topic = Topic::query()->with('article')->find($topicId);
            /** @var Post|null $article */
            $article = $topic?->article;
            $version = ArticleVersion::query()->find($versionId);

            if ($topic === null || $article === null || $version === null || (int) $version->article_id !== (int) $article->getKey()) {
                throw new RuntimeException(__('Diese Fassung gehört nicht zum veröffentlichten Artikel.'));
            }

            if ($version->published_at === null) {
                throw new RuntimeException(__('Diese Fassung war nie veröffentlicht. Sie kann nur über die Prüfung freigegeben werden.'));
            }

            $copy = $this->publisher->rollback($article, $version, $note);

            Log::info('Ratgeber: Rollback im Dashboard.', [
                'topic_id' => $topicId,
                'from_version' => $version->version,
                'user_id' => filament()->auth()->id(),
            ]);

            return (int) $copy->version;
        });
    }

    private function portalUrl(Tenant $tenant, string $path): ?string
    {
        if (blank($tenant->domain)) {
            return null;
        }

        return (app()->environment('production') ? 'https' : 'http')."://{$tenant->domain}{$path}";
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T|null
     */
    private function read(Tenant $tenant, callable $callback): mixed
    {
        try {
            return $tenant->run($callback);
        } catch (Throwable $exception) {
            Log::warning('Ratgeber-Versionen: Portal uebersprungen.', [
                'tenant' => $tenant->getKey(),
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
