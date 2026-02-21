<div class="space-y-6">
    {{-- Referral Link Card --}}
    <div class="dash-card dash-card-padded">
        <div class="flex items-start gap-4">
            <div class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0" style="background-color: rgba(var(--portal-primary-rgb, 59,130,246), 0.1);">
                <svg class="w-5 h-5" style="color: var(--portal-primary, #3b82f6);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <h3 class="text-lg font-semibold" style="color: var(--dash-text-primary);">Ihr Empfehlungslink</h3>
                <p class="text-sm mt-0.5" style="color: var(--dash-text-secondary);">Teilen Sie diesen Link mit Freunden und Kollegen</p>

                <div class="mt-3 flex items-center gap-2" x-data="{ copied: false }">
                    <div class="flex-1 min-w-0 px-3 py-2.5 rounded-lg text-sm font-mono truncate select-all"
                         style="border: 1px solid var(--dash-border); background-color: var(--dash-bg); color: var(--dash-text-secondary);">
                        {{ $referralLink }}
                    </div>
                    <button type="button"
                            @click="navigator.clipboard.writeText('{{ $referralLink }}'); copied = true; setTimeout(() => copied = false, 2000)"
                            class="dash-btn dash-btn-primary shrink-0">
                        <template x-if="!copied">
                            <span class="flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                Kopieren
                            </span>
                        </template>
                        <template x-if="copied">
                            <span class="flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                Kopiert!
                            </span>
                        </template>
                    </button>
                </div>

                <p class="text-xs mt-2" style="color: var(--dash-text-muted);">Code: <span class="font-mono">{{ $referralCode }}</span></p>
            </div>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="dash-stat-card">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg flex items-center justify-center" style="background-color: var(--dash-info-light);">
                    <svg class="w-5 h-5" style="color: var(--dash-info);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="dash-stat-value" style="font-size: 1.5rem;">{{ $totalReferrals }}</p>
                    <p class="dash-stat-label">Empfehlungen gesamt</p>
                </div>
            </div>
        </div>

        <div class="dash-stat-card">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg flex items-center justify-center" style="background-color: var(--dash-success-light);">
                    <svg class="w-5 h-5" style="color: var(--dash-success);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="dash-stat-value" style="font-size: 1.5rem;">{{ $rewardedReferrals }}</p>
                    <p class="dash-stat-label">Erfolgreich</p>
                </div>
            </div>
        </div>

        <div class="dash-stat-card">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg flex items-center justify-center" style="background-color: var(--dash-warning-light);">
                    <svg class="w-5 h-5" style="color: var(--dash-warning);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/>
                    </svg>
                </div>
                <div>
                    <p class="dash-stat-value" style="font-size: 1.5rem;">{{ $totalRewards }}</p>
                    <p class="dash-stat-label">Belohnungen erhalten</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Tab Navigation --}}
    <div class="dash-tab-bar">
        <button type="button" wire:click="switchTab('referrals')"
                class="dash-tab {{ $activeTab === 'referrals' ? 'dash-tab-active' : '' }}">
            Empfehlungen
            <span class="dash-tab-count">{{ $totalReferrals }}</span>
        </button>
        <button type="button" wire:click="switchTab('rewards')"
                class="dash-tab {{ $activeTab === 'rewards' ? 'dash-tab-active' : '' }}">
            Belohnungen
            <span class="dash-tab-count">{{ $totalRewards }}</span>
        </button>
    </div>

    {{-- Referrals Tab --}}
    @if($activeTab === 'referrals')
        <div class="dash-card overflow-hidden">
            @if($referrals->isEmpty())
                <div class="dash-empty">
                    <svg class="dash-empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <p class="dash-empty-title">Noch keine Empfehlungen</p>
                    <p class="dash-empty-description">Teilen Sie Ihren Empfehlungslink, um loszulegen</p>
                </div>
            @else
                {{-- Desktop Table --}}
                <div class="hidden sm:block dash-table-wrap">
                    <table class="dash-table">
                        <thead>
                            <tr>
                                <th>Benutzer</th>
                                <th>E-Mail</th>
                                <th>Status</th>
                                <th>Datum</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($referrals as $referral)
                                <tr>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <div class="dash-header-avatar shrink-0" style="width: 1.75rem; height: 1.75rem; font-size: 0.625rem;">
                                                {{ strtoupper(substr($referral->referredUser->name ?? '?', 0, 1)) }}
                                            </div>
                                            <span class="font-medium" style="color: var(--dash-text-primary);">{{ $referral->referredUser->name ?? 'Unbekannt' }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span style="color: var(--dash-text-secondary);">{{ $referral->referredUser->email ?? '—' }}</span>
                                    </td>
                                    <td>
                                        @php
                                            $statusBadge = match($referral->status) {
                                                'pending' => 'neutral',
                                                'verified' => 'info',
                                                'paid' => 'warning',
                                                'rewarded' => 'success',
                                                default => 'neutral',
                                            };
                                            $statusLabel = match($referral->status) {
                                                'pending' => 'Ausstehend',
                                                'verified' => 'Verifiziert',
                                                'paid' => 'Bezahlt',
                                                'rewarded' => 'Belohnt',
                                                default => ucfirst($referral->status),
                                            };
                                        @endphp
                                        <span class="dash-badge dash-badge-{{ $statusBadge }}">{{ $statusLabel }}</span>
                                    </td>
                                    <td>
                                        <span style="color: var(--dash-text-secondary);">{{ $referral->created_at->format('d.m.Y') }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Mobile Cards --}}
                <div class="sm:hidden">
                    @foreach($referrals as $referral)
                        <div class="p-4" style="border-bottom: 1px solid var(--dash-border);">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div class="dash-header-avatar" style="width: 2rem; height: 2rem; font-size: 0.625rem;">
                                        {{ strtoupper(substr($referral->referredUser->name ?? '?', 0, 1)) }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium" style="color: var(--dash-text-primary);">{{ $referral->referredUser->name ?? 'Unbekannt' }}</p>
                                        <p class="text-xs" style="color: var(--dash-text-muted);">{{ $referral->created_at->format('d.m.Y') }}</p>
                                    </div>
                                </div>
                                @php
                                    $statusBadge = match($referral->status) {
                                        'pending' => 'neutral',
                                        'verified' => 'info',
                                        'paid' => 'warning',
                                        'rewarded' => 'success',
                                        default => 'neutral',
                                    };
                                    $statusLabel = match($referral->status) {
                                        'pending' => 'Ausstehend',
                                        'verified' => 'Verifiziert',
                                        'paid' => 'Bezahlt',
                                        'rewarded' => 'Belohnt',
                                        default => ucfirst($referral->status),
                                    };
                                @endphp
                                <span class="dash-badge dash-badge-{{ $statusBadge }}">{{ $statusLabel }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Pagination --}}
                @if($referrals->hasPages())
                    <div class="dash-pagination">
                        {{ $referrals->links() }}
                    </div>
                @endif
            @endif
        </div>
    @endif

    {{-- Rewards Tab --}}
    @if($activeTab === 'rewards')
        <div class="dash-card overflow-hidden">
            @if($rewards->isEmpty())
                <div class="dash-empty">
                    <svg class="dash-empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/>
                    </svg>
                    <p class="dash-empty-title">Noch keine Belohnungen</p>
                    <p class="dash-empty-description">Belohnungen werden gutgeschrieben, sobald Ihre Empfehlungen erfolgreich sind</p>
                </div>
            @else
                {{-- Desktop Table --}}
                <div class="hidden sm:block dash-table-wrap">
                    <table class="dash-table">
                        <thead>
                            <tr>
                                <th>Art</th>
                                <th>Code</th>
                                <th>Details</th>
                                <th>Empfohlener Benutzer</th>
                                <th>Datum</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rewards as $reward)
                                <tr>
                                    <td>
                                        @if($reward->reward_type === 'coupon')
                                            <span class="dash-badge dash-badge-premium">Gutschein</span>
                                        @else
                                            <span class="dash-badge dash-badge-info">Belohnung</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($reward->reward_type === 'coupon' && $reward->discountCode)
                                            <span class="font-mono text-sm" x-data="{ copied: false }">
                                                {{ $reward->discountCode->code }}
                                                <button type="button"
                                                        @click="navigator.clipboard.writeText('{{ $reward->discountCode->code }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                                        class="dash-btn-icon" style="display: inline; padding: 0.125rem;">
                                                    <template x-if="!copied">
                                                        <svg class="w-3.5 h-3.5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                                    </template>
                                                    <template x-if="copied">
                                                        <svg class="w-3.5 h-3.5 inline" style="color: var(--dash-success);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                    </template>
                                                </button>
                                            </span>
                                        @else
                                            <span style="color: var(--dash-text-muted);">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span style="color: var(--dash-text-secondary);">
                                            @if($reward->reward_type === 'coupon' && $reward->discountCode?->discount)
                                                @php $discount = $reward->discountCode->discount; @endphp
                                                @if($discount->type === 'percentage')
                                                    {{ $discount->amount }}% Rabatt
                                                @else
                                                    {{ number_format($discount->amount / 100, 2, ',', '.') }} € Rabatt
                                                @endif
                                            @else
                                                —
                                            @endif
                                        </span>
                                    </td>
                                    <td>
                                        <span style="color: var(--dash-text-secondary);">{{ $reward->referral?->referredUser?->name ?? '—' }}</span>
                                    </td>
                                    <td>
                                        <span style="color: var(--dash-text-secondary);">{{ $reward->created_at->format('d.m.Y') }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Mobile Cards --}}
                <div class="sm:hidden">
                    @foreach($rewards as $reward)
                        <div class="p-4 space-y-2" style="border-bottom: 1px solid var(--dash-border);">
                            <div class="flex items-center justify-between">
                                @if($reward->reward_type === 'coupon')
                                    <span class="dash-badge dash-badge-premium">Gutschein</span>
                                @else
                                    <span class="dash-badge dash-badge-info">Belohnung</span>
                                @endif
                                <span class="text-xs" style="color: var(--dash-text-muted);">{{ $reward->created_at->format('d.m.Y') }}</span>
                            </div>
                            @if($reward->reward_type === 'coupon' && $reward->discountCode)
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-sm px-2 py-1 rounded" style="background-color: var(--dash-bg);">{{ $reward->discountCode->code }}</span>
                                    @if($reward->discountCode->discount)
                                        <span class="text-xs" style="color: var(--dash-text-secondary);">
                                            @if($reward->discountCode->discount->type === 'percentage')
                                                {{ $reward->discountCode->discount->amount }}% Rabatt
                                            @else
                                                {{ number_format($reward->discountCode->discount->amount / 100, 2, ',', '.') }} €
                                            @endif
                                        </span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                {{-- Pagination --}}
                @if($rewards->hasPages())
                    <div class="dash-pagination">
                        {{ $rewards->links() }}
                    </div>
                @endif
            @endif
        </div>
    @endif

    {{-- How It Works --}}
    <div class="dash-card dash-card-padded" style="background-color: var(--dash-bg);">
        <h3 class="text-sm font-semibold mb-4" style="color: var(--dash-text-primary);">So funktioniert's</h3>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="flex items-start gap-3">
                <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold shrink-0"
                     style="background-color: var(--dash-surface); border: 1px solid var(--dash-border); color: var(--portal-primary, #3b82f6);">1</div>
                <div>
                    <p class="text-sm font-medium" style="color: var(--dash-text-primary);">Link teilen</p>
                    <p class="text-xs mt-0.5" style="color: var(--dash-text-secondary);">Kopieren Sie Ihren Empfehlungslink und teilen Sie ihn</p>
                </div>
            </div>
            <div class="flex items-start gap-3">
                <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold shrink-0"
                     style="background-color: var(--dash-surface); border: 1px solid var(--dash-border); color: var(--portal-primary, #3b82f6);">2</div>
                <div>
                    <p class="text-sm font-medium" style="color: var(--dash-text-primary);">Freund registriert sich</p>
                    <p class="text-xs mt-0.5" style="color: var(--dash-text-secondary);">Ihr Freund erstellt ein Konto über Ihren Link</p>
                </div>
            </div>
            <div class="flex items-start gap-3">
                <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold shrink-0"
                     style="background-color: var(--dash-surface); border: 1px solid var(--dash-border); color: var(--portal-primary, #3b82f6);">3</div>
                <div>
                    <p class="text-sm font-medium" style="color: var(--dash-text-primary);">Belohnung erhalten</p>
                    <p class="text-xs mt-0.5" style="color: var(--dash-text-secondary);">Sie erhalten automatisch Ihre Belohnung</p>
                </div>
            </div>
        </div>
    </div>
</div>
