<?php

namespace Database\Factories\Portal;

use App\Models\Portal\City;
use Illuminate\Database\Eloquent\Factories\Factory;

class CityFactory extends Factory
{
    protected $model = City::class;

    private static array $germanCities = [
        ['name' => 'Berlin', 'zipcode' => '10115', 'state' => 'Berlin'],
        ['name' => 'Hamburg', 'zipcode' => '20095', 'state' => 'Hamburg'],
        ['name' => 'München', 'zipcode' => '80331', 'state' => 'Bayern'],
        ['name' => 'Köln', 'zipcode' => '50667', 'state' => 'Nordrhein-Westfalen'],
        ['name' => 'Frankfurt am Main', 'zipcode' => '60311', 'state' => 'Hessen'],
        ['name' => 'Stuttgart', 'zipcode' => '70173', 'state' => 'Baden-Württemberg'],
        ['name' => 'Düsseldorf', 'zipcode' => '40213', 'state' => 'Nordrhein-Westfalen'],
        ['name' => 'Leipzig', 'zipcode' => '04109', 'state' => 'Sachsen'],
        ['name' => 'Dortmund', 'zipcode' => '44135', 'state' => 'Nordrhein-Westfalen'],
        ['name' => 'Essen', 'zipcode' => '45127', 'state' => 'Nordrhein-Westfalen'],
        ['name' => 'Bremen', 'zipcode' => '28195', 'state' => 'Bremen'],
        ['name' => 'Dresden', 'zipcode' => '01067', 'state' => 'Sachsen'],
        ['name' => 'Hannover', 'zipcode' => '30159', 'state' => 'Niedersachsen'],
        ['name' => 'Nürnberg', 'zipcode' => '90402', 'state' => 'Bayern'],
        ['name' => 'Duisburg', 'zipcode' => '47051', 'state' => 'Nordrhein-Westfalen'],
        ['name' => 'Bochum', 'zipcode' => '44787', 'state' => 'Nordrhein-Westfalen'],
        ['name' => 'Wuppertal', 'zipcode' => '42103', 'state' => 'Nordrhein-Westfalen'],
        ['name' => 'Bielefeld', 'zipcode' => '33602', 'state' => 'Nordrhein-Westfalen'],
        ['name' => 'Bonn', 'zipcode' => '53111', 'state' => 'Nordrhein-Westfalen'],
        ['name' => 'Mannheim', 'zipcode' => '68159', 'state' => 'Baden-Württemberg'],
    ];

    private static int $cityIndex = 0;

    public function definition(): array
    {
        $city = self::$germanCities[self::$cityIndex % count(self::$germanCities)];
        self::$cityIndex++;

        return [
            'name' => $city['name'],
            'zipcode' => $city['zipcode'],
            'administrative_area_level_1' => $city['state'],
        ];
    }
}
