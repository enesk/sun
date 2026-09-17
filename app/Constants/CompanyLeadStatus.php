<?php

namespace App\Constants;

/**
 * Bearbeitungsstand einer exklusiven Anfrage (#9); setzt nur der Betrieb.
 */
enum CompanyLeadStatus: string
{
    case NEW = 'new';
    case CONTACTED = 'contacted';
    case CLOSED = 'closed';

    public function label(): string
    {
        return __("portal.owner.leads.statuses.{$this->value}");
    }
}
