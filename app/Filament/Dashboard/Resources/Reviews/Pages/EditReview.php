<?php

namespace App\Filament\Dashboard\Resources\Reviews\Pages;

use App\Filament\CrudDefaults;
use App\Filament\Dashboard\Resources\Reviews\ReviewResource;
use App\Models\Portal\Review;
use Filament\Resources\Pages\EditRecord;

class EditReview extends EditRecord
{
    use CrudDefaults;

    protected static string $resource = ReviewResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // is_approved und approved_at synchron mit moderation_status halten
        if ($data['moderation_status'] === Review::STATUS_APPROVED) {
            $data['is_approved'] = true;
            $data['approved_at'] = $data['approved_at'] ?? now();
            $data['moderated_by'] = auth()->user()?->name;
        } elseif ($data['moderation_status'] === Review::STATUS_REJECTED) {
            $data['is_approved'] = false;
            $data['approved_at'] = null;
            $data['moderated_by'] = auth()->user()?->name;
        } else {
            $data['is_approved'] = false;
            $data['approved_at'] = null;
        }

        return $data;
    }
}
