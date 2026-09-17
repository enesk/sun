<?php

namespace App\Policies;

use App\Models\Portal\Company;
use App\Models\Portal\CompanyInquiry;
use App\Models\User;

/**
 * Anfragen sieht und aendert nur der Inhaber des Betriebs (#32). Kontaktdaten
 * gehoeren dazu, deshalb gibt es bewusst keine Ausnahme fuer Admins.
 */
class CompanyInquiryPolicy
{
    public function viewAny(User $user): bool
    {
        return Company::ownedBy($user->id)->exists();
    }

    public function view(User $user, CompanyInquiry $inquiry): bool
    {
        $ownerId = $inquiry->company?->user_id;

        return $ownerId !== null && (int) $ownerId === (int) $user->id;
    }

    public function update(User $user, CompanyInquiry $inquiry): bool
    {
        return $this->view($user, $inquiry);
    }
}
