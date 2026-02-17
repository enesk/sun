<?php

namespace Database\Seeders;

use App\Models\Portal\Category;
use App\Models\Portal\City;
use App\Models\Portal\Company;
use App\Models\Portal\Review;
use Illuminate\Database\Seeder;

class TenantPortalSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding cities...');
        $cities = $this->seedCities();

        $this->command->info('Seeding categories...');
        $categories = $this->seedCategories();

        $this->command->info('Seeding companies...');
        $companies = $this->seedCompanies($cities, $categories);

        $this->command->info('Seeding reviews...');
        $this->seedReviews($companies);

        $this->command->info("Portal seeding complete: {$cities->count()} cities, {$categories->count()} categories, {$companies->count()} companies.");
    }

    private function seedCities(): \Illuminate\Support\Collection
    {
        $cities = [
            ['name' => 'Berlin', 'zipcode' => '10115', 'administrative_area_level_1' => 'Berlin'],
            ['name' => 'Hamburg', 'zipcode' => '20095', 'administrative_area_level_1' => 'Hamburg'],
            ['name' => 'München', 'zipcode' => '80331', 'administrative_area_level_1' => 'Bayern'],
            ['name' => 'Köln', 'zipcode' => '50667', 'administrative_area_level_1' => 'Nordrhein-Westfalen'],
            ['name' => 'Frankfurt am Main', 'zipcode' => '60311', 'administrative_area_level_1' => 'Hessen'],
            ['name' => 'Stuttgart', 'zipcode' => '70173', 'administrative_area_level_1' => 'Baden-Württemberg'],
            ['name' => 'Düsseldorf', 'zipcode' => '40213', 'administrative_area_level_1' => 'Nordrhein-Westfalen'],
            ['name' => 'Leipzig', 'zipcode' => '04109', 'administrative_area_level_1' => 'Sachsen'],
            ['name' => 'Dortmund', 'zipcode' => '44135', 'administrative_area_level_1' => 'Nordrhein-Westfalen'],
            ['name' => 'Essen', 'zipcode' => '45127', 'administrative_area_level_1' => 'Nordrhein-Westfalen'],
            ['name' => 'Bremen', 'zipcode' => '28195', 'administrative_area_level_1' => 'Bremen'],
            ['name' => 'Dresden', 'zipcode' => '01067', 'administrative_area_level_1' => 'Sachsen'],
            ['name' => 'Hannover', 'zipcode' => '30159', 'administrative_area_level_1' => 'Niedersachsen'],
            ['name' => 'Nürnberg', 'zipcode' => '90402', 'administrative_area_level_1' => 'Bayern'],
            ['name' => 'Duisburg', 'zipcode' => '47051', 'administrative_area_level_1' => 'Nordrhein-Westfalen'],
            ['name' => 'Bochum', 'zipcode' => '44787', 'administrative_area_level_1' => 'Nordrhein-Westfalen'],
            ['name' => 'Wuppertal', 'zipcode' => '42103', 'administrative_area_level_1' => 'Nordrhein-Westfalen'],
            ['name' => 'Bielefeld', 'zipcode' => '33602', 'administrative_area_level_1' => 'Nordrhein-Westfalen'],
            ['name' => 'Bonn', 'zipcode' => '53111', 'administrative_area_level_1' => 'Nordrhein-Westfalen'],
            ['name' => 'Mannheim', 'zipcode' => '68159', 'administrative_area_level_1' => 'Baden-Württemberg'],
        ];

        foreach ($cities as $city) {
            City::firstOrCreate(
                ['name' => $city['name'], 'administrative_area_level_1' => $city['administrative_area_level_1']],
                $city
            );
        }

        return City::all();
    }

    private function seedCategories(): \Illuminate\Support\Collection
    {
        // Nutzt den CategoryMappingSeeder für die deutsche Kategorie-Hierarchie
        // mit source_key-Verlinkung (benötigt für Import aus Quelldatenbank)
        $this->call(CategoryMappingSeeder::class);

        return Category::all();
    }

    private function seedCompanies(\Illuminate\Support\Collection $cities, \Illuminate\Support\Collection $categories): \Illuminate\Support\Collection
    {
        $subCategories = $categories->whereNotNull('parent_id');

        // 50 Companies mit zufälligen Städten und Kategorien
        Company::factory()
            ->count(50)
            ->create()
            ->each(function (Company $company) use ($cities, $subCategories) {
                // Zufällige Stadt zuweisen
                $city = $cities->random();
                $company->update([
                    'city_id' => $city->id,
                    'zipcode' => $city->zipcode,
                ]);

                // 1-3 Kategorien zuweisen
                $company->categories()->attach(
                    $subCategories->random(rand(1, 3))->pluck('id')
                );
            });

        return Company::all();
    }

    private function seedReviews(\Illuminate\Support\Collection $companies): void
    {
        // Jede Company bekommt 0-8 Reviews
        $companies->each(function (Company $company) {
            $count = rand(0, 8);
            if ($count === 0) {
                return;
            }

            Review::unsetEventDispatcher();

            Review::factory()
                ->count($count)
                ->create(['company_id' => $company->id]);

            Review::setEventDispatcher(app('events'));

            // Rating manuell berechnen (Events waren deaktiviert für Performance)
            $company->recalculateRating();
        });
    }
}
