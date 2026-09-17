<?php

namespace App\Events\Company;

use App\Models\Portal\CompanyInquiry;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Eine Anfrage wurde erstmals am Betrieb gespeichert (#32). Doppelte
 * Zustellungen loesen das Event nicht erneut aus.
 */
class CompanyInquiryReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public CompanyInquiry $inquiry,
    ) {}
}
