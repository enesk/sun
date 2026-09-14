<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Portal\City;
use App\Models\Portal\CityContent;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Stadtteile der groessten Staedte fuer den Local Hub (#11) aus
 * database/data/city-districts.json.
 *
 * Idempotent: fuellt nur city_contents.districts, die noch leer sind; fehlt
 * der Datensatz, wird er ohne Einleitung und FAQ angelegt (die Stadt zeigt
 * dann weiter die Tenant-Vorlage). Staedte, deren Slug im Portal fehlt oder
 * mehrdeutig ist, werden gemeldet und uebersprungen.
 *
 * php artisan db:seed --class=CityDistrictsSeeder --force
 */
class CityDistrictsSeeder extends Seeder
{
    /**
     * Portale (Domain-Stichwort), fuer die die Datei gepflegt ist.
     *
     * @var list<string>
     */
    private const DOMAIN_NEEDLES = ['elektriker'];

    public function run(): void
    {
        $cities = $this->cities();

        Tenant::query()->each(function (Tenant $tenant) use ($cities): void {
            if (! Str::contains(Str::lower((string) $tenant->domain), self::DOMAIN_NEEDLES)) {
                return;
            }

            $tenant->run(function () use ($tenant, $cities): void {
                if (! Schema::hasColumn('city_contents', 'districts')) {
                    $this->command?->warn("{$tenant->domain}: city_contents.districts fehlt, Tenant-Migrationen ausstehend.");

                    return;
                }

                $written = 0;
                $kept = 0;
                $missing = [];

                foreach ($cities as $row) {
                    $matches = City::query()->where('slug', $row['slug'])->get();

                    if ($matches->count() !== 1) {
                        $missing[] = "{$row['slug']} ({$matches->count()} Treffer)";

                        continue;
                    }

                    $content = CityContent::query()->firstOrNew(['city_id' => $matches->first()->id]);

                    if (filled($content->districts)) {
                        $kept++;

                        continue;
                    }

                    $content->districts = $row['districts'];
                    $content->save();
                    $written++;
                }

                $this->command?->info("{$tenant->domain}: {$written} Stadt(e) mit Stadtteilen befuellt, {$kept} bereits gepflegt.");

                if ($missing !== []) {
                    $this->command?->warn('Nicht zugeordnet: '.implode(', ', $missing));
                }
            });
        });
    }

    /**
     * @return list<array{slug: string, name: string, districts: list<string>}>
     */
    private function cities(): array
    {
        $path = database_path('data/city-districts.json');
        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data) || ! is_array($data['cities'] ?? null)) {
            throw new RuntimeException("{$path} ist kein gueltiges Stadtteil-JSON.");
        }

        return $data['cities'];
    }
}
