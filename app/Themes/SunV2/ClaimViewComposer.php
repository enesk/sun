<?php

declare(strict_types=1);

namespace App\Themes\SunV2;

use App\Constants\TenantConfigConstants;
use App\Models\Portal\Company;
use App\Support\TenantCache;
use App\Themes\ThemeManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Seite "Ist das dein Betrieb?" (/firma/{slug}/aenderung-vorschlagen) im
 * Theme sun-v2, Vorlage elektrikerportal-uebernehmen.html: Zahl der bereits
 * uebernommenen Eintraege und die Kontaktadresse fuer Rueckfragen.
 */
final class ClaimViewComposer
{
    public function __construct(private readonly ThemeManager $themes) {}

    public function compose(View $view): void
    {
        if ($this->themes->active()?->slug !== HomeViewComposer::THEME) {
            return;
        }

        $claimed = (int) Cache::remember(TenantCache::key('sun-v2.claim.claimed_count'), 3600, fn (): int => Company::query()
            ->whereNotNull('user_id')
            ->count());

        $view->with('claim', [
            'claimedCount' => $claimed,
            'contactEmail' => (string) (tenant()?->getAttribute(TenantConfigConstants::CONTACT_EMAIL) ?: config('mail.from.address')),
        ]);
    }
}
