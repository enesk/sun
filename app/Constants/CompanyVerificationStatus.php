<?php

namespace App\Constants;

enum CompanyVerificationStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => __('In Prüfung'),
            self::APPROVED => __('Bestätigt'),
            self::REJECTED => __('Abgelehnt'),
        };
    }
}
