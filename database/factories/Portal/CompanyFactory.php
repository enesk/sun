<?php

namespace Database\Factories\Portal;

use App\Models\Portal\Company;
use App\Models\Portal\City;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyFactory extends Factory
{
    protected $model = Company::class;

    private static array $germanStreets = [
        'Hauptstraße', 'Bahnhofstraße', 'Gartenstraße', 'Schulstraße', 'Kirchstraße',
        'Berliner Straße', 'Mühlweg', 'Lindenstraße', 'Bergstraße', 'Ringstraße',
        'Friedhofstraße', 'Waldstraße', 'Industriestraße', 'Am Markt', 'Mozartstraße',
        'Schillerstraße', 'Goethestraße', 'Bismarckstraße', 'Parkstraße', 'Rosenweg',
    ];

    private static array $companySuffixes = [
        'GmbH', 'e.K.', 'OHG', 'KG', 'UG', 'AG', 'GbR', '',
    ];

    private static array $companyTypes = [
        'Elektrotechnik', 'Sanitär', 'Malerbetrieb', 'Schreinerei', 'Bäckerei',
        'Metzgerei', 'Autohaus', 'Reisebüro', 'Friseur', 'Fotostudio',
        'Druckerei', 'Blumenladen', 'Buchhandlung', 'Optiker', 'Goldschmiede',
        'Textilreinigung', 'Fahrradladen', 'Baumarkt', 'Schlüsseldienst', 'Umzugsunternehmen',
    ];

    public function definition(): array
    {
        $lastName = $this->faker->lastName();
        $type = $this->faker->randomElement(self::$companyTypes);
        $suffix = $this->faker->randomElement(self::$companySuffixes);
        $name = trim("{$lastName} {$type} {$suffix}");

        return [
            'user_id' => null,
            'name' => $name,
            'description' => $this->faker->realText(200),
            'street' => $this->faker->randomElement(self::$germanStreets),
            'house_no' => $this->faker->numberBetween(1, 150) . $this->faker->optional(0.3)->randomElement(['a', 'b', 'c', '']),
            'zipcode' => $this->faker->numerify('#####'),
            'city_id' => City::inRandomOrder()->first()?->id ?? 1,
            'tel' => $this->faker->numerify('0###-#######'),
            'email' => $this->faker->companyEmail(),
            'website' => $this->faker->optional(0.7)->url(),
            'google_places_id' => $this->faker->optional(0.3)->regexify('ChIJ[a-zA-Z0-9_-]{27}'),
            'rating' => 0,
            'rating_count' => 0,
            'is_premium' => $this->faker->boolean(15),
            'is_verified' => $this->faker->boolean(40),
            'is_active' => $this->faker->boolean(90),
        ];
    }

    public function premium(): static
    {
        return $this->state(['is_premium' => true, 'is_verified' => true, 'is_active' => true]);
    }

    public function verified(): static
    {
        return $this->state(['is_verified' => true, 'is_active' => true]);
    }
}
