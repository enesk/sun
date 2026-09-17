<?php

namespace App\Constants;

enum CompanyVerificationDocumentType: string
{
    case TRADE_REGISTER = 'trade_register';
    case BUSINESS_LICENSE = 'business_license';
    case MASTER_CERTIFICATE = 'master_certificate';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::TRADE_REGISTER => __('Handelsregisterauszug'),
            self::BUSINESS_LICENSE => __('Gewerbeanmeldung'),
            self::MASTER_CERTIFICATE => __('Meisterbrief'),
            self::OTHER => __('Sonstiger Nachweis'),
        };
    }
}
