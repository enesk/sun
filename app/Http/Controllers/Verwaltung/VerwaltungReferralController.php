<?php

namespace App\Http\Controllers\Verwaltung;

use App\Services\ReferralService;
use Illuminate\Support\Facades\Auth;

class VerwaltungReferralController extends VerwaltungBaseController
{
    /**
     * Referral dashboard — link, stats, referrals, rewards.
     */
    public function index(ReferralService $referralService)
    {
        if (! $referralService->isEnabled()) {
            abort(404);
        }

        $this->setBreadcrumbs([
            ['label' => 'Übersicht', 'url' => route('verwaltung.index')],
            ['label' => 'Empfehlungen'],
        ]);

        $navigationItems = $this->getNavigationItems();

        return view('pages.verwaltung.referrals.index', compact('navigationItems'));
    }
}
