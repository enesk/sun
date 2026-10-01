<?php

declare(strict_types=1);

namespace App\Events\Company;

use App\Models\Portal\Company;
use App\Models\Tenant;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Ein Eintrag wurde erstmals freigeschaltet (is_active = true). Feuert genau
 * einmal je Eintrag, der Merker steht in companies.activation_notified_at.
 * Der Tenant reist mit, damit Branding und URLs der Mail auch dann stimmen,
 * wenn der Listener ausserhalb des Portal-Kontexts laeuft.
 */
class CompanyActivated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Company $company,
        public ?Tenant $tenant = null,
    ) {}
}
