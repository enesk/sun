<?php

namespace App\Livewire\Verwaltung;

use App\Constants\ReferralConstants;
use App\Models\Referral;
use App\Models\ReferralReward;
use App\Services\ReferralService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class ReferralDashboard extends Component
{
    use WithPagination;

    public string $referralLink = '';
    public string $referralCode = '';
    public int $totalReferrals = 0;
    public int $rewardedReferrals = 0;
    public int $totalRewards = 0;

    public string $activeTab = 'referrals';

    public bool $copied = false;

    public function mount(): void
    {
        $referralService = app(ReferralService::class);
        $user = Auth::user();

        $this->referralLink = $referralService->getReferralLink($user);
        $referralCode = $referralService->getOrCreateReferralCode($user);
        $this->referralCode = $referralCode->code;

        $stats = $referralService->getReferralStats($user);
        $this->totalReferrals = $stats['total_referrals'];
        $this->rewardedReferrals = $stats['rewarded_referrals'];
        $this->totalRewards = $stats['total_rewards'];
    }

    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function markCopied(): void
    {
        $this->copied = true;
    }

    public function render()
    {
        $user = Auth::user();

        $referrals = Referral::where('referrer_user_id', $user->id)
            ->with(['referredUser'])
            ->orderByDesc('created_at')
            ->paginate(10, pageName: 'referralsPage');

        $rewards = ReferralReward::where('referrer_user_id', $user->id)
            ->with(['referral.referredUser', 'discountCode.discount'])
            ->orderByDesc('created_at')
            ->paginate(10, pageName: 'rewardsPage');

        return view('livewire.verwaltung.referral-dashboard', [
            'referrals' => $referrals,
            'rewards' => $rewards,
        ]);
    }
}
