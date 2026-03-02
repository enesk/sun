<?php

namespace App\Models;

use Stancl\Tenancy\Database\Concerns\CentralConnection;

use App\Notifications\Auth\QueuedVerifyEmail;
use App\Services\OrderService;
use App\Services\SubscriptionService;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laragear\TwoFactor\Contracts\TwoFactorAuthenticatable;
use Laragear\TwoFactor\TwoFactorAuthentication;
use Laravel\Sanctum\HasApiTokens;
use Spatie\OneTimePasswords\Models\Concerns\HasOneTimePasswords;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasTenants, MustVerifyEmail, TwoFactorAuthenticatable
{
    use CentralConnection;
    use HasApiTokens, HasFactory, HasOneTimePasswords, HasRoles, Notifiable, TwoFactorAuthentication;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'public_name',
        'is_blocked',
        'notes',
        'phone_number',
        'phone_number_verified_at',
        'last_seen_at',
        'first_claim_at',
        'onboarding_dismissed_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_number_verified_at' => 'datetime',
        'password' => 'hashed',
        'last_seen_at' => 'datetime',
        'first_claim_at' => 'datetime',
        'onboarding_dismissed_at' => 'datetime',
    ];

    public function roadmapItems(): HasMany
    {
        return $this->hasMany(RoadmapItem::class);
    }

    public function roadmapItemUpvotes(): BelongsToMany
    {
        return $this->belongsToMany(RoadmapItem::class, 'roadmap_item_user_upvotes');
    }

    public function userParameters(): HasMany
    {
        return $this->hasMany(UserParameter::class);
    }

    public function stripeData(): HasMany
    {
        return $this->hasMany(UserStripeData::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function subscriptionTrials(): HasMany
    {
        return $this->hasMany(UserSubscriptionTrial::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() == 'admin' && ! $this->is_admin) {
            return false;
        }

        return true;
    }

    public function getPublicName()
    {
        return $this->public_name ?? $this->name;
    }

    public function scopeAdmin($query)
    {
        return $query->where('is_admin', true);
    }

    public function isAdmin()
    {
        return $this->is_admin;
    }

    public function isPhoneNumberVerified()
    {
        return $this->phone_number_verified_at !== null;
    }

    public function canImpersonate()
    {
        return $this->hasPermissionTo('impersonate users') && $this->isAdmin();
    }

    public function isSubscribed(?string $productSlug = null, ?Tenant $tenant = null): bool
    {
        /** @var SubscriptionService $subscriptionService */
        $subscriptionService = app(SubscriptionService::class);

        return $subscriptionService->isUserSubscribed($this, $productSlug, $tenant);
    }

    public function isTrialing(?string $productSlug = null, ?Tenant $tenant = null): bool
    {
        /** @var SubscriptionService $subscriptionService */
        $subscriptionService = app(SubscriptionService::class);

        return $subscriptionService->isUserTrialing($this, $productSlug, $tenant);
    }

    public function hasPurchased(?string $productSlug = null, ?Tenant $tenant = null): bool
    {
        /** @var OrderService $orderService */
        $orderService = app(OrderService::class);

        return $orderService->hasUserOrdered($this, $productSlug, $tenant);
    }

    public function sendEmailVerificationNotification()
    {
        $this->notify(new QueuedVerifyEmail);
    }

    public function address(): HasOne
    {
        return $this->hasOne(Address::class);
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class)->using(TenantUser::class)->withPivot('id')->withTimestamps();
    }

    public function getTenants(Panel $panel): Collection
    {
        return $this->tenants;
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->tenants()->whereKey($tenant)->exists();
    }

    public function referralCode(): HasOne
    {
        return $this->hasOne(ReferralCode::class);
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class, 'referrer_user_id');
    }

    public function referredBy(): HasOne
    {
        return $this->hasOne(Referral::class, 'referred_user_id');
    }

    public function referralRewards(): HasMany
    {
        return $this->hasMany(ReferralReward::class, 'referrer_user_id');
    }

    // ── Company & Claim Helpers (Cross-DB: User=Central, Company/ClaimRequest=Tenant) ──

    /**
     * Gibt die Firma zurück, die diesem User gehört (user_id Match in Tenant-DB).
     * Cached pro Request um Mehrfach-Queries im Header zu vermeiden.
     */
    public function getOwnedCompany(): ?\App\Models\Portal\Company
    {
        if (! property_exists($this, '_cachedOwnedCompany') || ! isset($this->_cachedOwnedCompany)) {
            $this->_cachedOwnedCompany = \App\Models\Portal\Company::where('user_id', $this->id)->first();
        }

        return $this->_cachedOwnedCompany;
    }

    /**
     * Prüft ob der User einen ausstehenden Claim-Antrag hat.
     * Gibt den ClaimRequest mit Company zurück (für Redirect-URL).
     */
    public function getPendingClaimRequest(): ?\App\Models\Portal\ClaimRequest
    {
        return \App\Models\Portal\ClaimRequest::where('user_id', $this->id)
            ->where('status', \App\Models\Portal\ClaimRequest::STATUS_PENDING)
            ->with('company')
            ->first();
    }

    // ── Claim & Onboarding ──

    public function hasClaimedCompany(): bool
    {
        return $this->first_claim_at !== null;
    }

    public function markFirstClaim(): void
    {
        try {
            if ($this->first_claim_at === null) {
                $this->update(['first_claim_at' => now()]);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('markFirstClaim failed — column may not exist yet', [
                'user_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function hasOnboardingDismissed(): bool
    {
        return $this->onboarding_dismissed_at !== null;
    }

    public function dismissOnboarding(): void
    {
        $this->update(['onboarding_dismissed_at' => now()]);
    }

    public function shouldShowOnboarding(): bool
    {
        return $this->hasClaimedCompany() && !$this->hasOnboardingDismissed();
    }
}
