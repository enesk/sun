<?php

namespace Database\Seeders;

use App\Models\Portal\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds die deutsche Kategorie-Hierarchie aus config/category-mapping.php
 * mit source_key-Verlinkung für den Import aus der Quelldatenbank.
 *
 * Nutzung:
 *   php artisan tenants:run "db:seed --class=CategoryMappingSeeder"
 */
class CategoryMappingSeeder extends Seeder
{
    public function run(): void
    {
        $config = config('category-mapping');
        $categories = $config['categories'];
        $fallback = $config['fallback'];

        $parentOrder = 0;
        $created = 0;
        $updated = 0;

        foreach ($categories as $parentData) {
            // Parent-Kategorie
            $parent = Category::firstOrCreate(
                ['slug' => Str::slug($parentData['name'])],
                [
                    'name' => $parentData['name'],
                    'icon' => $parentData['icon'],
                    'description' => $parentData['description'] ?? null,
                    'parent_id' => null,
                    'sort_order' => $parentOrder++,
                    'source_key' => '_parent',
                ]
            );

            if ($parent->wasRecentlyCreated) {
                $created++;
            } else {
                // Bestehende Parent aktualisieren (Icon, Description, Sort, source_key)
                $parent->update([
                    'icon' => $parentData['icon'],
                    'description' => $parentData['description'] ?? $parent->description,
                    'sort_order' => $parentOrder - 1,
                    'source_key' => $parent->source_key ?? '_parent',
                ]);
                $updated++;
            }

            // Kinder-Kategorien mit source_keys
            $childOrder = 0;
            foreach ($parentData['children'] as $childData) {
                $sourceKeys = $childData['source_keys'] ?? [];

                // Jede Child-Kategorie bekommt den ersten source_key als primären source_key
                // Alle source_keys werden als komma-separierter String gespeichert
                $sourceKeyValue = !empty($sourceKeys) ? implode(',', $sourceKeys) : null;

                $child = Category::firstOrCreate(
                    ['slug' => Str::slug($childData['name'])],
                    [
                        'name' => $childData['name'],
                        'icon' => $childData['icon'] ?? $parentData['icon'],
                        'description' => "{$childData['name']} — Unterkategorie von {$parentData['name']}",
                        'parent_id' => $parent->id,
                        'sort_order' => $childOrder++,
                        'source_key' => $sourceKeyValue,
                    ]
                );

                if ($child->wasRecentlyCreated) {
                    $created++;
                } else {
                    // Bestehende aktualisieren — source_key, parent_id, sort_order
                    $child->update([
                        'icon' => $childData['icon'] ?? $parentData['icon'],
                        'parent_id' => $parent->id,
                        'sort_order' => $childOrder - 1,
                        'source_key' => $sourceKeyValue,
                    ]);
                    $updated++;
                }
            }
        }

        // Fallback-Kategorie "Sonstiges"
        $sonstiges = Category::firstOrCreate(
            ['slug' => Str::slug($fallback)],
            [
                'name' => $fallback,
                'icon' => 'help-circle',
                'description' => 'Firmen die keiner spezifischen Branche zugeordnet werden konnten',
                'parent_id' => null,
                'sort_order' => $parentOrder,
                'source_key' => '_fallback',
            ]
        );

        if ($sonstiges->wasRecentlyCreated) {
            $created++;
        }

        $this->command->info("Kategorie-Mapping abgeschlossen: {$created} erstellt, {$updated} aktualisiert.");
        $this->command->info("Ignorierte Quell-Kategorien: " . count($config['ignored']));
    }
}
