<?php

namespace Database\Factories\Portal;

use App\Models\Portal\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    private static array $categories = [
        ['name' => 'Handwerker', 'icon' => 'wrench', 'children' => ['Elektriker', 'Klempner', 'Maler', 'Tischler', 'Dachdecker']],
        ['name' => 'Gastronomie', 'icon' => 'utensils', 'children' => ['Restaurant', 'Café', 'Bar', 'Imbiss', 'Lieferservice']],
        ['name' => 'Gesundheit', 'icon' => 'heart-pulse', 'children' => ['Arztpraxis', 'Zahnarzt', 'Physiotherapie', 'Apotheke']],
        ['name' => 'Dienstleistungen', 'icon' => 'briefcase', 'children' => ['Rechtsanwalt', 'Steuerberater', 'Versicherung', 'Immobilienmakler']],
        ['name' => 'Einzelhandel', 'icon' => 'shopping-bag', 'children' => ['Bekleidung', 'Elektronik', 'Lebensmittel', 'Möbel']],
        ['name' => 'Automobile', 'icon' => 'car', 'children' => ['Autowerkstatt', 'Autohaus', 'Reifenservice', 'Lackiererei']],
        ['name' => 'Bildung', 'icon' => 'graduation-cap', 'children' => ['Nachhilfe', 'Sprachschule', 'Musikschule', 'Fahrschule']],
        ['name' => 'Beauty & Wellness', 'icon' => 'spa', 'children' => ['Friseur', 'Kosmetikstudio', 'Nagelstudio', 'Massage']],
        ['name' => 'IT & Technik', 'icon' => 'laptop', 'children' => ['Webdesign', 'IT-Service', 'Softwareentwicklung']],
        ['name' => 'Sport & Freizeit', 'icon' => 'dumbbell', 'children' => ['Fitnessstudio', 'Sportverein', 'Tanzschule']],
    ];

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(),
            'description' => $this->faker->sentence(),
            'icon' => 'folder',
            'parent_id' => null,
            'sort_order' => 0,
        ];
    }

    public static function getCategories(): array
    {
        return self::$categories;
    }
}
