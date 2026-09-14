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
        // is_approved und approved_at synchron mit moderation_status halten;
        // Rating und JSON-LD baut der ReviewObserver neu auf.
        $status = $data['moderation_status'] ?? $this->record->moderation_status;

        $data['is_approved'] = $status === Review::STATUS_APPROVED;
        $data['approved_at'] = $status === Review::STATUS_APPROVED
            ? ($this->record->approved_at ?? now())
            : null;

        if ($status !== $this->record->moderation_status) {
            $data['moderated_at'] = now();
            $data['moderated_by'] = auth()->id();
            $data['moderated_by_name'] = auth()->user()?->name;
        }

        return $data;
    }
}
