<?php

declare(strict_types=1);

namespace App\View\Directives;

use App\Enums\PremiumFeature;
use App\Models\Portal\Company;
use App\Services\Premium\CompanyEntitlementService;
use Illuminate\Support\Facades\Blade;

/**
 * Blade-Directive premiumFeature (#4), Aufruf in Views:
 *
 *   (at)premiumFeature($company, \App\Enums\PremiumFeature::AdFree) ... (at)else ... (at)endpremiumFeature
 *   (at)unlesspremiumFeature(...) ... (at)endpremiumFeature
 */
class PremiumFeatureDirective
{
    public const NAME = 'premiumFeature';

    public static function register(): void
    {
        Blade::if(self::NAME, static function (?Company $company, PremiumFeature $feature): bool {
            return app(CompanyEntitlementService::class)->can($company, $feature);
        });
    }
}
