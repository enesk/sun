<?php

declare(strict_types=1);

namespace App\Services\TenantImport;

use App\Models\Portal\Company;
use App\Models\Portal\Review;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReviewImporter
{
    /**
     * Importiert Reviews eines Places in die Company.
     *
     * @return int Anzahl importierter Reviews
     */
    public function import(int $oldPlaceId, int $newCompanyId, string $tempConnection): int
    {
        $rows = DB::connection($tempConnection)
            ->table('place_reviews')
            ->where('place_id', $oldPlaceId)
            ->get();

        if ($rows->isEmpty()) {
            return 0;
        }

        $imported = 0;

        foreach ($rows as $row) {
            $rating = $this->clampRating((float) ($row->rating ?? 0));
            $moderationStatus = $this->mapStatus($row->status ?? 'approved');
            $createdAt = $row->created_at ?? now();
            $authorName = $row->author_name ?? 'Unbekannt';

            // Duplikat-Prüfung
            $exists = Review::where('company_id', $newCompanyId)
                ->where('author_name', $authorName)
                ->where('created_at', $createdAt)
                ->exists();

            if ($exists) {
                continue;
            }

            // Events unterdrücken (verhindert recalculateRating pro Review)
            Review::withoutEvents(function () use ($newCompanyId, $row, $rating, $moderationStatus, $authorName, $createdAt) {
                Review::create([
                    'company_id' => $newCompanyId,
                    'user_id' => null,
                    'author_name' => $authorName,
                    'rating' => $rating,
                    'title' => null,
                    'body' => $row->text ?? $row->body ?? null,
                    'is_approved' => $moderationStatus === Review::STATUS_APPROVED,
                    'approved_at' => $moderationStatus === Review::STATUS_APPROVED ? $createdAt : null,
                    'moderation_status' => $moderationStatus,
                    'moderation_note' => $row->rejection_reason ?? $row->moderation_note ?? null,
                    'created_at' => $createdAt,
                    'updated_at' => $row->updated_at ?? $createdAt,
                ]);
            });

            $imported++;
        }

        // Rating einmal am Ende neu berechnen
        if ($imported > 0) {
            Company::find($newCompanyId)?->recalculateRating();
        }

        return $imported;
    }

    /**
     * Clampt Rating auf 1.0-5.0, gerundet auf 1 Dezimalstelle.
     */
    private function clampRating(float $rating): float
    {
        if ($rating <= 0) {
            return 1.0;
        }
        return round(max(1.0, min(5.0, $rating)), 1);
    }

    private function mapStatus(string $status): string
    {
        return match ($status) {
            'approved' => Review::STATUS_APPROVED,
            'pending' => Review::STATUS_PENDING,
            'rejected' => Review::STATUS_REJECTED,
            default => Review::STATUS_PENDING,
        };
    }
}
