<?php

namespace Database\Factories\Portal;

use App\Models\Portal\Company;
use App\Models\Portal\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReviewFactory extends Factory
{
    protected $model = Review::class;

    private static array $reviewTitles = [
        'Sehr zufrieden!', 'Top Service', 'Kann ich empfehlen', 'Gute Arbeit',
        'Nicht schlecht', 'Könnte besser sein', 'Enttäuschend', 'Super Laden!',
        'Professionell und freundlich', 'Preis-Leistung stimmt', 'Immer wieder gerne',
        'Schnell und zuverlässig', 'Sehr kompetent', 'Netter Kontakt', 'Tolle Beratung',
        'Faire Preise', 'Absolut empfehlenswert', 'Hatte bessere Erfahrungen',
        'Erstklassiger Service', 'Bin begeistert',
    ];

    private static array $germanFirstNames = [
        'Thomas', 'Michael', 'Andreas', 'Stefan', 'Christian', 'Markus', 'Daniel',
        'Sandra', 'Julia', 'Sabine', 'Claudia', 'Petra', 'Monika', 'Nicole',
        'Frank', 'Peter', 'Martin', 'Wolfgang', 'Jürgen', 'Klaus',
    ];

    private static array $germanLastNames = [
        'Müller', 'Schmidt', 'Schneider', 'Fischer', 'Weber', 'Meyer', 'Wagner',
        'Becker', 'Schulz', 'Hoffmann', 'Koch', 'Richter', 'Klein', 'Wolf',
        'Schröder', 'Neumann', 'Schwarz', 'Zimmermann', 'Braun', 'Krüger',
    ];

    public function definition(): array
    {
        $rating = $this->faker->randomElement([1.0, 2.0, 2.5, 3.0, 3.5, 4.0, 4.0, 4.5, 4.5, 5.0]);
        $firstName = $this->faker->randomElement(self::$germanFirstNames);
        $lastName = $this->faker->randomElement(self::$germanLastNames);
        $isApproved = $this->faker->boolean(75);

        return [
            'company_id' => Company::inRandomOrder()->first()?->id ?? 1,
            'user_id' => null,
            'author_name' => "{$firstName} {$lastName}",
            'rating' => $rating,
            'title' => $this->faker->randomElement(self::$reviewTitles),
            'body' => $this->faker->realText(300),
            'is_approved' => $isApproved,
            'moderation_status' => $isApproved ? Review::STATUS_APPROVED : Review::STATUS_PENDING,
            'approved_at' => $isApproved ? $this->faker->dateTimeBetween('-6 months') : null,
        ];
    }

    public function approved(): static
    {
        return $this->state([
            'is_approved' => true,
            'moderation_status' => Review::STATUS_APPROVED,
            'approved_at' => now(),
        ]);
    }

    public function pending(): static
    {
        return $this->state([
            'is_approved' => false,
            'moderation_status' => Review::STATUS_PENDING,
            'approved_at' => null,
        ]);
    }

    public function rejected(): static
    {
        return $this->state([
            'is_approved' => false,
            'moderation_status' => Review::STATUS_REJECTED,
            'approved_at' => null,
            'moderation_note' => $this->faker->sentence(),
        ]);
    }
}
