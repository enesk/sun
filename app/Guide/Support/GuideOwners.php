<?php

declare(strict_types=1);

namespace App\Guide\Support;

use App\Guide\Enums\GuideRole;
use App\Models\User;

/**
 * Empfaenger von Tagesbericht und Alarm-Mail des Ratgebersystems (#38 G1/G2,
 * design/guide-dashboard.md §11.3): alle nicht gesperrten Nutzer mit
 * Mailadresse, deren Panel-Rolle Inhaber ist — dieselbe Regel wie im Panel
 * (User::contentRole(), Administratoren ohne Guide-Rolle gelten als Inhaber).
 */
final class GuideOwners
{
    /**
     * @return list<string>
     */
    public static function emails(): array
    {
        return User::query()
            ->where('is_blocked', false)
            ->where(fn ($query) => $query->where('guide_role', GuideRole::OWNER->value)->orWhere('is_admin', true))
            ->get()
            ->filter(static fn (User $user): bool => $user->contentRole() === GuideRole::OWNER && filled($user->email))
            ->map(static fn (User $user): string => (string) $user->email)
            ->unique()
            ->values()
            ->all();
    }
}
