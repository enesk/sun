<?php

declare(strict_types=1);

namespace App\Guide\Services;

use App\Guide\Models\Category;
use App\Guide\Support\GuidePageCache;
use App\Models\Tenant;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

/**
 * Ratgeber-Kategorien aus dem Dashboard pflegen (#15).
 *
 * Eine Kategorie wird ueber "<tenant-id>-<id>" adressiert; bei "Alle Portale"
 * uebergibt die Oberflaeche alle Mitglieder eines Slugs, die Aenderung wirkt
 * dann in jedem dieser Portale. Nach jeder Aenderung wird der Seiten-Cache
 * des Portals und der netzwerkweite Stand verworfen.
 *
 * Der Slug ist Teil der oeffentlichen Adresse /ratgeber/kategorie/{slug}.
 * Umbenennen aendert ihn nie; changeSlug() ist der einzige Weg, und
 * Category::booted() legt dabei die 301-Weiterleitung von der alten Adresse
 * an (guide_redirects, #18).
 */
class CategoryAdminService
{
    /**
     * Nie Kategorie-Slug: feste Unterseiten von /ratgeber (docs/guide-system.md §5).
     */
    public const RESERVED_SLUGS = ['feed', 'suche', 'redaktion', 'vorschau', 'kategorie', 'tag'];

    public function __construct(private readonly TopicDirectory $directory) {}

    public static function slugify(string $value): string
    {
        return Str::slug($value, '-', 'de');
    }

    /**
     * Legt die Kategorie in jedem Portal an, das sie noch nicht hat (Abgleich
     * ueber den Slug), am Ende der Reihenfolge.
     *
     * @param  iterable<int, Tenant>  $tenants
     * @param  array{name: string, slug?: string|null, description?: string|null, meta_title?: string|null, meta_description?: string|null}  $data
     * @return array{done: int, skipped: int}
     */
    public function create(iterable $tenants, array $data): array
    {
        $slug = $this->validSlug(filled($data['slug'] ?? null) ? (string) $data['slug'] : $data['name']);
        $done = 0;
        $skipped = 0;

        foreach ($tenants as $tenant) {
            $created = $tenant->run(function () use ($slug, $data): bool {
                if (Category::query()->where('slug', $slug)->exists()) {
                    return false;
                }

                Category::query()->create([
                    'name' => trim($data['name']),
                    'slug' => $slug,
                    'description' => $data['description'] ?? null,
                    'meta_title' => $data['meta_title'] ?? null,
                    'meta_description' => $data['meta_description'] ?? null,
                    'position' => ((int) Category::query()->max('position')) + 1,
                ]);

                GuidePageCache::flush();

                return true;
            });

            $done += (int) $created;
            $skipped += (int) ! $created;
        }

        $this->directory->forget();

        return ['done' => $done, 'skipped' => $skipped];
    }

    /**
     * Name, Beschreibung und Meta; der Slug bleibt.
     *
     * @param  iterable<int, string>  $keys
     * @param  array{name: string, description?: string|null, meta_title?: string|null, meta_description?: string|null}  $data
     */
    public function update(iterable $keys, array $data): int
    {
        return $this->each($keys, function (Category $category) use ($data): void {
            $category->update([
                'name' => trim($data['name']),
                'description' => $data['description'] ?? null,
                'meta_title' => $data['meta_title'] ?? null,
                'meta_description' => $data['meta_description'] ?? null,
            ]);
        });
    }

    /**
     * Neue Adresse; nur nach ausdruecklicher Bestaetigung im Dashboard.
     *
     * @param  iterable<int, string>  $keys
     *
     * @throws InvalidArgumentException bei ungueltigem, reserviertem oder in einem Portal vergebenem Slug
     */
    public function changeSlug(iterable $keys, string $newSlug): int
    {
        $slug = $this->validSlug($newSlug);
        $keys = collect($keys)->all();

        // Erst in allen Portalen pruefen, dann aendern — sonst stuende die
        // Kategorie nach einem Konflikt in einem Portal halb umbenannt da.
        foreach (TopicDirectory::groupKeys($keys) as $tenantId => $ids) {
            $taken = $this->directory->tenant($tenantId)?->run(
                fn (): bool => Category::query()->where('slug', $slug)->whereKeyNot($ids)->exists()
            );

            if ($taken) {
                throw new InvalidArgumentException(__('Die Adresse /:slug ist in einem Portal schon vergeben.', ['slug' => $slug]));
            }
        }

        return $this->each($keys, function (Category $category) use ($slug): void {
            if ($category->slug === $slug) {
                return;
            }

            // Category::booted() legt die 301 von der alten Adresse an.
            $category->update(['slug' => $slug]);
        });
    }

    /**
     * @param  iterable<int, string>  $keys
     *
     * @throws LogicException wenn noch Themen in der Kategorie stehen
     */
    public function delete(iterable $keys): int
    {
        return $this->each($keys, function (Category $category): void {
            if ($category->topics()->exists()) {
                throw new LogicException(__('Die Kategorie „:name“ enthält noch Themen.', ['name' => $category->name]));
            }

            $category->delete();
        });
    }

    /**
     * Reihenfolge eines Portals, erste id = Position 1.
     *
     * @param  list<int>  $categoryIds
     */
    public function reorder(Tenant $tenant, array $categoryIds): void
    {
        $tenant->run(function () use ($categoryIds): void {
            foreach (array_values($categoryIds) as $index => $id) {
                Category::query()->whereKey($id)->update(['position' => $index + 1]);
            }

            GuidePageCache::flush();
        });

        $this->directory->forget();
    }

    private function validSlug(string $value): string
    {
        $slug = self::slugify($value);

        if ($slug === '') {
            throw new InvalidArgumentException(__('Aus der Angabe lässt sich keine Adresse bilden.'));
        }

        if (in_array($slug, self::RESERVED_SLUGS, true)) {
            throw new InvalidArgumentException(__('Die Adresse /:slug ist für feste Ratgeber-Seiten reserviert.', ['slug' => $slug]));
        }

        return $slug;
    }

    /**
     * @param  iterable<int, string>  $keys
     * @param  callable(Category): void  $callback
     */
    private function each(iterable $keys, callable $callback): int
    {
        $count = 0;

        foreach (TopicDirectory::groupKeys($keys) as $tenantId => $ids) {
            $tenant = $this->directory->tenant($tenantId);

            if ($tenant === null) {
                continue;
            }

            $count += $tenant->run(function () use ($ids, $callback): int {
                $categories = Category::query()->whereKey($ids)->get();

                foreach ($categories as $category) {
                    $callback($category);
                }

                GuidePageCache::flush();

                return $categories->count();
            });
        }

        $this->directory->forget();

        return $count;
    }
}
