<?php

declare(strict_types=1);

namespace App\Guide\Import;

use App\Guide\Enums\TopicStatus;
use App\Guide\Events\TopicCreated;
use App\Guide\Models\Category;
use App\Guide\Models\Central\TopicList;
use App\Guide\Models\Central\TopicListItem;
use App\Guide\Models\TenantGuideSetting;
use App\Guide\Models\Topic;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Weist eine Themenliste einem Portal zu und legt daraus guide_topics an.
 *
 * Je Listen-Item:
 * - Platzhalter {{branch}}/{{branch_plural}} werden aus tenant_guide_settings
 *   ersetzt, {{year}} bleibt fuer die Renderzeit stehen.
 * - Fehlende Kategorien werden angelegt (Abgleich ueber den Slug).
 * - Mit vorgegebenen Ueberschriften wird die Gliederung sofort gesperrt und
 *   das Thema `active`, sonst `outline_pending` (Gliederungsvorschlag ueber
 *   das Event TopicCreated).
 *
 * Idempotent: Ein Thema, das schon aus diesem Item entstanden ist
 * (guide_topics.list_item_id), wird nicht neu angelegt; notes, priority und
 * Pruefintervall werden uebernommen. Slug und gesperrte Gliederung bleiben
 * unangetastet. Eine Frage, die das Portal schon aus anderer Quelle hat
 * (gleiche normalisierte Frage), wird uebersprungen.
 */
class TopicListAssigner
{
    public function __construct(
        private readonly PlaceholderResolver $placeholders,
        private readonly QuestionNormalizer $normalizer,
    ) {}

    public function assign(TopicList $list, Tenant $tenant): ImportReport
    {
        $items = $list->items()->get();

        /** @var ImportReport $report */
        $report = $tenant->run(fn (): ImportReport => $this->assignItems($items, $tenant));

        $list->tenants()->syncWithoutDetaching([
            $tenant->getKey() => ['last_assigned_at' => now()],
        ]);

        return $report;
    }

    /**
     * @param  Collection<int, TopicListItem>  $items
     */
    private function assignItems(Collection $items, Tenant $tenant): ImportReport
    {
        $report = new ImportReport;
        $settings = TenantGuideSetting::current();

        $topics = Topic::query()->get();
        $byListItem = $topics->whereNotNull('list_item_id')->keyBy('list_item_id');
        $byQuestion = $topics->keyBy(fn (Topic $topic): string => $this->normalizer->normalize($topic->question));
        $usedSlugs = $topics->pluck('slug')->flip()->all();

        /** @var Collection<string, Category> $categories */
        $categories = Category::query()->get()->keyBy('slug');

        foreach ($items as $item) {
            try {
                $existing = $byListItem->get($item->getKey());

                if ($existing !== null) {
                    $this->refresh($existing, $item, $settings)
                        ? $report->updated++
                        : $report->skippedDuplicates++;

                    continue;
                }

                $question = $this->placeholders->resolveForTenant($item->question, $settings);
                $normalized = $this->normalizer->normalize($question);

                if ($byQuestion->has($normalized)) {
                    $report->skippedDuplicates++;

                    continue;
                }

                $outline = $this->resolveOutline($item->outline_json, $settings);
                $status = $outline !== [] ? TopicStatus::ACTIVE : TopicStatus::OUTLINE_PENDING;

                $topic = Topic::query()->create([
                    'list_item_id' => $item->getKey(),
                    'guide_category_id' => $this->category($item->category_name, $settings, $categories)?->getKey(),
                    'question' => $question,
                    'slug' => $slug = $this->uniqueSlug($question, $usedSlugs),
                    'notes' => $item->notes,
                    'outline_json' => $outline !== [] ? $outline : null,
                    'outline_locked_at' => $outline !== [] ? now() : null,
                    'status' => $status,
                    'priority' => $item->priority,
                    'refresh_interval_days' => $item->refresh_interval_days,
                ]);

                $usedSlugs[$slug] = true;
                $byQuestion->put($normalized, $topic);
                $report->imported++;

                TopicCreated::dispatch($tenant->getKey(), (int) $topic->getKey(), $status);
            } catch (MissingPlaceholderValue|InvalidTopicRow $e) {
                $report->addError((int) $item->position, $e->getMessage());
            }
        }

        return $report;
    }

    /**
     * Uebernimmt geaenderte Angaben des Items in ein vorhandenes Thema.
     * Frage und Slug bleiben, eine gesperrte Gliederung ebenso. Eine erst
     * nachtraeglich vorgegebene Gliederung sperrt ein noch offenes Thema.
     */
    private function refresh(Topic $topic, TopicListItem $item, TenantGuideSetting $settings): bool
    {
        $topic->fill([
            'notes' => $item->notes,
            'priority' => $item->priority,
            'refresh_interval_days' => $item->refresh_interval_days,
        ]);

        $outline = $topic->isOutlineLocked() ? [] : $this->resolveOutline($item->outline_json, $settings);

        if ($outline !== [] && $topic->status->canTransitionTo(TopicStatus::ACTIVE)) {
            $topic->fill([
                'outline_json' => $outline,
                'outline_locked_at' => now(),
                'status' => TopicStatus::ACTIVE,
            ]);
        }

        if (! $topic->isDirty()) {
            return false;
        }

        $topic->save();

        return true;
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $outline
     * @return array<int, array<string, mixed>>
     */
    private function resolveOutline(?array $outline, TenantGuideSetting $settings): array
    {
        return array_map(function (array $section) use ($settings): array {
            $section['heading'] = $this->placeholders->resolveForTenant((string) $section['heading'], $settings);

            if (isset($section['children'])) {
                $section['children'] = $this->resolveOutline((array) $section['children'], $settings);
            }

            return $section;
        }, $outline ?? []);
    }

    /**
     * @param  Collection<string, Category>  $categories  Slug => Kategorie, wird ergaenzt
     */
    private function category(?string $name, TenantGuideSetting $settings, Collection $categories): ?Category
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        $name = $this->placeholders->resolveForTenant(trim($name), $settings);
        $slug = Str::slug($name, '-', 'de');

        if ($slug === '') {
            throw new InvalidTopicRow("Kategorie \"{$name}\" ergibt keinen Slug.");
        }

        if (! $categories->has($slug)) {
            $categories->put($slug, Category::query()->create([
                'name' => $name,
                'slug' => $slug,
                'position' => ((int) $categories->max('position')) + 1,
            ]));
        }

        return $categories->get($slug);
    }

    /**
     * @param  array<string, mixed>  $usedSlugs
     */
    private function uniqueSlug(string $question, array $usedSlugs): string
    {
        $base = $this->normalizer->slug($question);

        if ($base === '') {
            throw new InvalidTopicRow('Aus der Frage lässt sich kein Slug bilden.');
        }

        $slug = $base;
        $counter = 2;

        while (isset($usedSlugs[$slug])) {
            $slug = $this->normalizer->withSuffix($base, $counter++);
        }

        return $slug;
    }
}
