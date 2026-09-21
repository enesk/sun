<?php

declare(strict_types=1);

namespace App\Guide\Legacy;

use App\Guide\Llm\Exceptions\BudgetExceededException;
use App\Guide\Llm\Exceptions\LlmSchemaException;
use App\Guide\Llm\LlmCallContext;
use App\Guide\Llm\LlmClient;
use App\Guide\Models\Category;
use App\Guide\Models\Central\PromptTemplate;
use App\Guide\Support\GuidePageCache;
use App\Guide\Support\TenantPromptVars;
use App\Models\Portal\Post;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Ordnet die Altartikel eines Portals einer Ratgeber-Kategorie zu (#19).
 *
 * Ohne --smart landet jeder Altartikel ohne Kategorie in "Allgemein" (wird
 * bei Bedarf angelegt). Mit --smart waehlt das Modell ueber structured()
 * und das Template guide.legacy_category die passendste vorhandene
 * Kategorie; unbekannte Antworten, Schemafehler und ein erschoepftes Budget
 * fallen auf "Allgemein" zurueck, der Lauf bricht nie ab.
 *
 * Nur `posts.guide_category_id` wird gesetzt. Slug, Status und
 * `posts.category_id` (Blog-Kategorie) bleiben unveraendert — die Adresse
 * /ratgeber/{slug} haengt nicht an der Kategorie, kein Altartikel verliert
 * dadurch seine Seite. Erwartet einen initialisierten Tenant-Kontext.
 */
class LegacyCategorizer
{
    public const FALLBACK_NAME = 'Allgemein';

    public const FALLBACK_SLUG = 'allgemein';

    public const TEMPLATE_KEY = 'guide.legacy_category';

    private const EXCERPT_LENGTH = 600;

    public function __construct(private readonly LlmClient $llm) {}

    /**
     * @param  (callable(string): void)|null  $warn
     * @return array{assigned: int, fallback: int, by_category: array<string, int>}
     */
    public function categorize(bool $smart, ?callable $warn = null): array
    {
        $warn ??= static fn (string $message): null => null;
        $result = ['assigned' => 0, 'fallback' => 0, 'by_category' => []];

        $posts = LegacyArticleMatcher::legacyPosts()
            ->whereNull('guide_category_id')
            ->orderBy('id')
            ->get(['id', 'title', 'slug', 'excerpt', 'body']);

        if ($posts->isEmpty()) {
            return $result;
        }

        $fallback = $this->fallbackCategory();
        $choices = $smart ? $this->choices() : [];
        $template = $choices !== [] ? $this->template() : null;
        $vars = $template !== null ? TenantPromptVars::current() : [];

        foreach ($posts as $post) {
            $category = $fallback;

            if ($template !== null) {
                try {
                    $category = $this->choose($template, $vars, $post, $choices) ?? $fallback;
                } catch (BudgetExceededException $e) {
                    $warn("Budget erschöpft, restliche Altartikel gehen nach \"{$fallback->name}\": {$e->getMessage()}");
                    $template = null;
                } catch (LlmSchemaException $e) {
                    $warn("Beitrag {$post->id}: keine gültige Antwort, \"{$fallback->name}\" ({$e->getMessage()})");
                }
            }

            // Ohne updated_at: die Zuordnung ist keine inhaltliche Aenderung des Beitrags.
            Post::query()->whereKey($post->id)->toBase()->update(['guide_category_id' => $category->id]);

            $result['assigned']++;
            $result['fallback'] += (int) ($category->is($fallback));
            $result['by_category'][$category->name] = ($result['by_category'][$category->name] ?? 0) + 1;
        }

        GuidePageCache::flush();

        return $result;
    }

    /**
     * "Allgemein" des Portals; eine versteckte bleibt versteckt, eine fehlende
     * wird sichtbar am Ende der Reihenfolge angelegt.
     */
    public function fallbackCategory(): Category
    {
        return Category::query()->firstOrCreate(
            ['slug' => self::FALLBACK_SLUG],
            [
                'name' => self::FALLBACK_NAME,
                'position' => ((int) Category::query()->max('position')) + 1,
                'is_visible' => true,
            ],
        );
    }

    /**
     * Vorhandene Kategorien ausser "Allgemein", Slug => Kategorie. Leer
     * heisst: es gibt nichts zu waehlen, das Modell wird nicht gefragt.
     *
     * @return array<string, Category>
     */
    private function choices(): array
    {
        return Category::query()
            ->where('slug', '!=', self::FALLBACK_SLUG)
            ->ordered()
            ->get()
            ->keyBy('slug')
            ->all();
    }

    /**
     * @param  array<string, string>  $vars
     * @param  array<string, Category>  $choices
     */
    private function choose(PromptTemplate $template, array $vars, Post $post, array $choices): ?Category
    {
        $answer = $this->llm->structured($template, [
            ...$vars,
            'question' => (string) $post->title,
            'notes' => Str::limit(trim(strip_tags((string) ($post->excerpt ?: Str::markdown((string) $post->body)))), self::EXCERPT_LENGTH),
            'category' => json_encode(
                array_values(array_map(fn (Category $category): array => [
                    'slug' => (string) $category->slug,
                    'name' => (string) $category->name,
                    'description' => (string) $category->description,
                ], $choices)),
                JSON_UNESCAPED_UNICODE,
            ),
        ], [], LlmCallContext::current());

        $slug = (string) ($answer['category_slug'] ?? '');

        return $choices[$slug] ?? null;
    }

    private function template(): PromptTemplate
    {
        $tenantId = tenancy()->initialized ? (int) tenant()?->getKey() : null;
        $template = PromptTemplate::query()->resolve(self::TEMPLATE_KEY, $tenantId)->first();

        if ($template === null) {
            throw new RuntimeException("Prompt-Template '".self::TEMPLATE_KEY."' fehlt. php artisan db:seed --class=GuidePromptTemplateSeeder ausfuehren.");
        }

        return $template;
    }
}
