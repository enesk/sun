<?php

declare(strict_types=1);

namespace App\Content\Services;

use App\Content\Llm\EmbeddingClient;
use App\Content\Llm\LlmContext;
use App\Content\Models\ArticleDraft;
use App\Content\Models\Central\ContentFingerprint;
use App\Models\Portal\Post;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Fingerprint eines veroeffentlichten Ratgebers (#21).
 *
 * Der Eintrag in `content_fingerprints` (Central) ist die Grundlage der
 * tenantuebergreifenden Duplikatspruefung (#12) und der internen Verlinkung
 * (#14). Er entsteht genau einmal je Artikel, bei der Veroeffentlichung, und
 * wird bei einer Aktualisierung fortgeschrieben.
 *
 * Vergleichstext ist bewusst nicht der ganze Artikel: Titel, Kurzantwort und
 * die ersten 500 Woerter des Fliesstexts. Sie tragen das Thema; der Rest
 * verwaessert die Aehnlichkeit und kostet beim Embedding nur Tokens.
 *
 * Ist das Budget erschoepft oder Voyage nicht erreichbar, entsteht der
 * Eintrag trotzdem — mit SimHash, ohne Vektor. Eine Veroeffentlichung darf an
 * einem Embedding nicht scheitern; der SimHash-Vorfilter greift auch allein.
 */
class FingerprintService
{
    /** Woerter des Fliesstexts, die in den Vergleichstext eingehen. */
    public const BODY_WORDS = 500;

    public const OPERATION = 'fingerprint';

    public function __construct(
        private readonly EmbeddingClient $embeddings,
    ) {}

    /**
     * Legt den Fingerprint an oder schreibt ihn fort.
     */
    public function register(Tenant $tenant, Post $post, ?ArticleDraft $draft, string $url): ContentFingerprint
    {
        $text = $this->text($post, $draft);

        return ContentFingerprint::query()->updateOrCreate(
            [
                'tenant_id' => (int) $tenant->getKey(),
                'article_id' => (int) $post->getKey(),
            ],
            [
                'url' => $url,
                'simhash' => DuplicateChecker::simhash($text),
                'embedding_json' => $this->embedding($text, $draft),
                'primary_keyword' => $this->primaryKeyword($post, $draft),
                'published_at' => $post->published_at,
            ],
        );
    }

    /**
     * Entfernt den Fingerprint eines Artikels (Zuruecknahme, #21).
     */
    public function forget(Tenant $tenant, int $articleId): int
    {
        return ContentFingerprint::query()
            ->forTenant((int) $tenant->getKey())
            ->where('article_id', $articleId)
            ->delete();
    }

    /**
     * Vergleichstext: Titel, Kurzantwort, erste 500 Woerter des Fliesstexts.
     */
    public function text(Post $post, ?ArticleDraft $draft): string
    {
        $body = strip_tags((string) ($draft?->body_html ?: $post->body));
        $body = trim(preg_replace('/\s+/u', ' ', html_entity_decode($body)) ?? '');

        $words = $body === '' ? [] : explode(' ', $body);

        return trim(implode("\n", array_filter([
            (string) $post->title,
            trim((string) ($draft?->short_answer ?? '')),
            implode(' ', array_slice($words, 0, self::BODY_WORDS)),
        ])));
    }

    /**
     * @return array<int, float>|null
     */
    private function embedding(string $text, ?ArticleDraft $draft): ?array
    {
        if (trim($text) === '') {
            return null;
        }

        $context = $draft !== null
            ? LlmContext::forDraft($draft, self::OPERATION)
            : LlmContext::current(self::OPERATION);

        try {
            return $this->embeddings->embed($text, EmbeddingClient::INPUT_DOCUMENT, $context);
        } catch (Throwable $exception) {
            Log::warning('Fingerprint ohne Embedding angelegt.', [
                'draft_id' => $draft?->getKey(),
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Hauptsuchbegriff des Artikels. Ohne Thema (von Hand gepflegter Beitrag)
     * traegt der Titel; leer bleibt die Spalte nie, sie ist indiziert und
     * wird in der Duplikatspruefung verglichen.
     */
    private function primaryKeyword(Post $post, ?ArticleDraft $draft): string
    {
        $keyword = trim((string) ($draft?->topicCandidate?->primary_keyword ?? ''));

        return Str::limit($keyword !== '' ? $keyword : (string) $post->title, 250, '');
    }
}
