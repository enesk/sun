<?php

declare(strict_types=1);

namespace App\Companies\Deletion\Steps;

use App\Companies\Deletion\DeletionContext;
use App\Companies\Deletion\DeletionStep;
use App\Companies\Deletion\StepResult;
use App\Models\Portal\Review;

/**
 * Bewertungen und Inhaberantworten loeschen (#8).
 *
 * Endgueltig, nicht anonymisiert: `author_name`, `body` und vor allem
 * `owner_response` sind personenbezogene Freitexte, und ohne das Profil
 * sagt eine Bewertung nichts mehr aus. Die Zaehler am Profil
 * (`rating`, `rating_count`) werden auf 0 zurueckgesetzt, damit ein
 * Zurueckholen nicht mit Bewertungsschnitt ohne Bewertungen dasteht.
 */
class RemoveReviews implements DeletionStep
{
    public function key(): string
    {
        return 'remove_reviews';
    }

    public function label(): string
    {
        return 'Bewertungen löschen';
    }

    public function plan(DeletionContext $context): string
    {
        return sprintf(
            '%d Bewertung(en) samt Inhaberantworten löschen, rating und rating_count am Profil zurücksetzen.',
            $this->query($context)->count(),
        );
    }

    public function run(DeletionContext $context): StepResult
    {
        $deleted = (int) $this->query($context)->delete();

        // 0, nicht null: `companies.rating` ist NOT NULL mit Default 0.0 —
        // dieselbe Form, die recalculateRating() ohne Bewertungen schreibt.
        $context->company->forceFill(['rating' => 0, 'rating_count' => 0])->save();

        return StepResult::ok(sprintf('%d Bewertung(en) gelöscht.', $deleted), [
            'reviews_deleted' => $deleted,
        ]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Review>
     */
    private function query(DeletionContext $context): \Illuminate\Database\Eloquent\Builder
    {
        return Review::query()->where('company_id', $context->company->getKey());
    }
}
