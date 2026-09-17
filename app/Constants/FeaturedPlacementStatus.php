<?php

namespace App\Constants;

enum FeaturedPlacementStatus: string
{
    case ACTIVE = 'active';
    case ENDED = 'ended';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => __('Aktiv'),
            self::ENDED => __('Beendet'),
            self::CANCELLED => __('Storniert'),
        };
    }
}
