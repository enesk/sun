<?php

declare(strict_types=1);

namespace App\Guide\Legacy;

use App\Guide\Models\Central\GuideAlert;
use App\Guide\Models\Redirect;
use App\Guide\Models\Topic;
use App\Guide\Support\GuidePageCache;
use App\Models\Portal\Post;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Entscheidung zu einer gemeldeten Ueberschneidung (Thema, Altartikel) aus
 * guide:legacy:overlaps (#19). Gedacht fuer die Knoepfe im Dashboard; der
 * Alarm in guide_alerts wird dabei erledigt.
 *
 * adoptSlug(): Das Thema uebernimmt den Altartikel samt URL. Der Beitrag
 * wird mit dem Thema verknuepft (posts.guide_topic_id, guide_topics.article_id);
 * der Publisher (#12) schreibt die erste Fassung dann in diesen Beitrag,
 * Slug und Erstveroeffentlichung bleiben. Nur solange das Thema noch keinen
 * veroeffentlichten Artikel hat — sonst gaebe es zwei Seiten zu einem Thema.
 *
 * redirect(): Der Altartikel wird archiviert und seine Adresse per 301 auf
 * den veroeffentlichten Artikel des Themas geleitet (guide_redirects,
 * Grund article_merge). Setzt einen veroeffentlichten Artikel voraus, sonst
 * zeigte die Weiterleitung ins Leere.
 *
 * dismiss(): "Keine Ueberschneidung" — nichts am Portal aendert sich, der
 * Alarm wird mit decision = dismissed erledigt und von guide:legacy:overlaps
 * nicht wieder geoeffnet (design/guide-dashboard.md §5.6.6). Fuer ueberholte
 * Paare (Altartikel nicht mehr veroeffentlicht oder schon einem Thema
 * zugeordnet) decision = obsolete: der naechste Abgleich darf das Paar
 * erneut melden, falls es wieder zutrifft.
 */
class LegacyOverlapResolver
{
    public const DECISION_ADOPT_SLUG = 'adopt_slug';

    public const DECISION_REDIRECT = 'redirect';

    public const DECISION_DISMISSED = 'dismissed';

    public const DECISION_OBSOLETE = 'obsolete';

    public static function dedupeKey(int $tenantId, int $topicId, int $postId): string
    {
        return "legacy_overlap:{$tenantId}:{$topicId}:{$postId}";
    }

    /**
     * Veroeffentlichter Artikel des Themas, oder null.
     */
    public static function publishedArticle(Topic $topic): ?Post
    {
        if ($topic->article_id === null) {
            return null;
        }

        return Post::query()->published()->find($topic->article_id);
    }

    public function adoptSlug(Tenant $tenant, int $topicId, int $postId): void
    {
        $tenant->run(function () use ($topicId, $postId): void {
            [$topic, $post] = $this->load($topicId, $postId);

            if (self::publishedArticle($topic) !== null) {
                throw new LogicException(__('Das Thema hat bereits einen veröffentlichten Artikel; die Adresse des Altartikels kann es nicht mehr übernehmen. Stattdessen weiterleiten.'));
            }

            DB::connection($post->getConnectionName())->transaction(function () use ($topic, $post): void {
                // Ein noch nicht veroeffentlichter Beitrag des Themas verliert die Verknuepfung.
                if ($topic->article_id !== null && (int) $topic->article_id !== (int) $post->id) {
                    Post::query()->whereKey($topic->article_id)->toBase()->update(['guide_topic_id' => null]);
                }

                Post::query()->whereKey($post->id)->toBase()->update([
                    'guide_topic_id' => $topic->id,
                    'guide_category_id' => $topic->guide_category_id ?? $post->guide_category_id,
                ]);

                $topic->forceFill(['article_id' => $post->id])->save();
            });

            GuidePageCache::flush();
        });

        $this->resolveAlert($tenant, $topicId, $postId, self::DECISION_ADOPT_SLUG);
    }

    public function redirect(Tenant $tenant, int $topicId, int $postId): void
    {
        $tenant->run(function () use ($topicId, $postId): void {
            [$topic, $post] = $this->load($topicId, $postId);
            $target = self::publishedArticle($topic);

            if ($target === null) {
                throw new LogicException(__('Das Thema hat noch keinen veröffentlichten Artikel, auf den weitergeleitet werden kann.'));
            }

            DB::connection($post->getConnectionName())->transaction(function () use ($post, $target): void {
                Redirect::record(
                    route('guide.show', $post->slug, false),
                    route('guide.show', $target->slug, false),
                    Redirect::REASON_ARTICLE_MERGE,
                );

                // Die Weiterleitung greift erst, wenn die alte Seite 404 liefert.
                $post->forceFill(['status' => Post::STATUS_ARCHIVED])->save();
            });

            GuidePageCache::flush();
        });

        $this->resolveAlert($tenant, $topicId, $postId, self::DECISION_REDIRECT);
    }

    /**
     * Erledigt das Paar ohne Aenderung am Portal: dismissed ("Keine
     * Ueberschneidung", wird nie wieder gemeldet) oder obsolete (ueberholt,
     * darf wieder gemeldet werden).
     */
    public function dismiss(Tenant $tenant, int $topicId, int $postId, string $decision = self::DECISION_DISMISSED): void
    {
        if (! in_array($decision, [self::DECISION_DISMISSED, self::DECISION_OBSOLETE], true)) {
            throw new LogicException("Unbekannte Entscheidung: {$decision}");
        }

        $this->resolveAlert($tenant, $topicId, $postId, $decision);
    }

    public static function isDismissed(?GuideAlert $alert): bool
    {
        return $alert !== null
            && $alert->status === GuideAlert::STATUS_RESOLVED
            && ($alert->context_json['decision'] ?? null) === self::DECISION_DISMISSED;
    }

    /**
     * Hebt "Keine Ueberschneidung" wieder auf: der naechste Abgleich meldet
     * das Paar erneut, sofern es noch zutrifft. false, wenn das Paar nicht
     * als keine Ueberschneidung vermerkt war.
     */
    public function reopenDismissed(int $tenantId, int $topicId, int $postId): bool
    {
        $alert = GuideAlert::query()->where('dedupe_key', self::dedupeKey($tenantId, $topicId, $postId))->first();

        if (! self::isDismissed($alert)) {
            return false;
        }

        $context = $alert->context_json ?? [];
        unset($context['decision']);

        $alert->forceFill(['context_json' => $context])->save();

        return true;
    }

    /**
     * @return array{0: Topic, 1: Post}
     */
    private function load(int $topicId, int $postId): array
    {
        $topic = Topic::query()->findOrFail($topicId);
        $post = Post::query()->findOrFail($postId);

        if ($post->guide_topic_id !== null) {
            throw new LogicException(__('Der Beitrag gehört bereits zu einem Ratgeber-Thema und ist kein Altartikel mehr.'));
        }

        if ($post->status !== Post::STATUS_PUBLISHED) {
            throw new LogicException(__('Der Altartikel ist nicht mehr veröffentlicht.'));
        }

        return [$topic, $post];
    }

    private function resolveAlert(Tenant $tenant, int $topicId, int $postId, string $decision): void
    {
        $alert = GuideAlert::query()->where('dedupe_key', self::dedupeKey((int) $tenant->getKey(), $topicId, $postId))->first();

        if ($alert === null) {
            return;
        }

        $alert->forceFill([
            'status' => GuideAlert::STATUS_RESOLVED,
            'resolved_at' => now(),
            'context_json' => [...($alert->context_json ?? []), 'decision' => $decision],
        ])->save();
    }
}
