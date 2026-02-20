<div class="space-y-6">
    {{-- Referral Link Card --}}
    <div class="bg-white rounded-xl border border-base-200 p-6">
        <div class="flex items-start gap-4">
            <div class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0" style="background-color: rgba(var(--portal-primary-rgb, 59,130,246), 0.1);">
                <svg class="w-5 h-5" style="color: var(--portal-primary, #3b82f6);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <h3 class="text-lg font-semibold text-base-content">Ihr Empfehlungslink</h3>
                <p class="text-sm text-base-content/60 mt-0.5">Teilen Sie diesen Link mit Freunden und Kollegen</p>

                <div class="mt-3 flex items-center gap-2" x-data="{ copied: false }">
                    <div class="flex-1 min-w-0 px-3 py-2.5 rounded-lg border border-base-300 bg-base-50 text-sm font-mono text-base-content/80 truncate select-all">
                        {{ $referralLink }}
                    </div>
                    <button type="button"
                            @click="navigator.clipboard.writeText('{{ $referralLink }}'); copied = true; setTimeout(() => copied = false, 2000)"
                            class="shrink-0 inline-flex items-center gap-1.5 px-4 py-2.5 rounded-lg text-white text-sm font-medium transition-all hover:opacity-90"
                            style="background-color: var(--portal-primary, #3b82f6);">
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

                <p class="text-xs text-base-content/50 mt-2">Code: <span class="font-mono">{{ $referralCode }}</span></p>
            </div>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl border border-base-200 p-5">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-blue-50 flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-base-content">{{ $totalReferrals }}</p>
                    <p class="text-xs text-base-content/60">Empfehlungen gesamt</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-base-200 p-5">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-green-50 flex items-center justify-center">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-base-content">{{ $rewardedReferrals }}</p>
                    <p class="text-xs text-base-content/60">Erfolgreich</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-base-200 p-5">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-amber-50 flex items-center justify-center">
                    <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-base-content">{{ $totalRewards }}</p>
                    <p class="text-xs text-base-content/60">Belohnungen erhalten</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Tab Navigation --}}
    <div class="flex gap-1 border-b border-base-200 overflow-x-auto">
        <button type="button" wire:click="switchTab('referrals')"
                class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors whitespace-nowrap {{ $activeTab === 'referrals' ? '' : 'border-transparent text-base-content/60 hover:text-base-content' }}"
                @if($activeTab === 'referrals') style="border-color: var(--portal-primary, #3b82f6); color: var(--portal-primary, #3b82f6);" @endif>
            Empfehlungen ({{ $totalReferrals }})
        </button>
        <button type="button" wire:click="switchTab('rewards')"
                class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors whitespace-nowrap {{ $activeTab === 'rewards' ? '' : 'border-transparent text-base-content/60 hover:text-base-content' }}"
                @if($activeTab === 'rewards') style="border-color: var(--portal-primary, #3b82f6); color: var(--portal-primary, #3b82f6);" @endif>
            Belohnungen ({{ $totalRewards }})
        </button>
    </div>

    {{-- Referrals Tab --}}
    @if($activeTab === 'referrals')
        <div class="bg-white rounded-xl border border-base-200 overflow-hidden">
            @if($referrals->isEmpty())
                <div class="p-12 text-center">
                    <div class="w-12 h-12 rounded-full bg-base-100 flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6 text-base-content/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                    <p class="text-sm font-medium text-base-content/60">Noch keine Empfehlungen</p>
                    <p class="text-xs text-base-content/40 mt-1">Teilen Sie Ihren Empfehlungslink, um loszulegen</p>
                </div>
            @else
                {{-- Desktop Table --}}
                <div class="hidden sm:block overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-base-200 bg-base-50">
                                <th class="text-left px-4 py-3 font-medium text-base-content/60">Benutzer</th>
                                <th class="text-left px-4 py-3 font-medium text-base-content/60">E-Mail</th>
                                <th class="text-left px-4 py-3 font-medium text-base-content/60">Status</th>
                                <th class="text-left px-4 py-3 font-medium text-base-content/60">Datum</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-base-200">
                            @foreach($referrals as $referral)
                                <tr class="hover:bg-base-50 transition-colors">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold text-white shrink-0" style="background-color: var(--portal-primary, #3b82f6);">
                                                {{ strtoupper(substr($referral->referredUser->name ?? '?', 0, 1)) }}
                                            </div>
                                            <span class="font-medium text-base-content">{{ $referral->referredUser->name ?? 'Unbekannt' }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-base-content/60">{{ $referral->referredUser->email ?? '—' }}</td>
                                    <td class="px-4 py-3">
                                        @php
                                            $statusConfig = match($referral->status) {
                                                'pending' => ['label' => 'Ausstehend', 'class' => 'bg-gray-100 text-gray-700'],
                                                'verified' => ['label' => 'Verifiziert', 'class' => 'bg-blue-100 text-blue-700'],
                                                'paid' => ['label' => 'Bezahlt', 'class' => 'bg-amber-100 text-amber-700'],
                                                'rewarded' => ['label' => 'Belohnt', 'class' => 'bg-green-100 text-green-700'],
                                                default => ['label' => ucfirst($referral->status), 'class' => 'bg-gray-100 text-gray-700'],
                                            };
                                        @endphp
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusConfig['class'] }}">
                                            {{ $statusConfig['label'] }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-base-content/60">{{ $referral->created_at->format('d.m.Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Mobile Cards --}}
                <div class="sm:hidden divide-y divide-base-200">
                    @foreach($referrals as $referral)
                        <div class="p-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold text-white" style="background-color: var(--portal-primary, #3b82f6);">
                                        {{ strtoupper(substr($referral->referredUser->name ?? '?', 0, 1)) }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-base-content">{{ $referral->referredUser->name ?? 'Unbekannt' }}</p>
                                        <p class="text-xs text-base-content/50">{{ $referral->created_at->format('d.m.Y') }}</p>
                                    </div>
                                </div>
                                @php
                                    $statusConfig = match($referral->status) {
                                        'pending' => ['label' => 'Ausstehend', 'class' => 'bg-gray-100 text-gray-700'],
                                        'verified' => ['label' => 'Verifiziert', 'class' => 'bg-blue-100 text-blue-700'],
                                        'paid' => ['label' => 'Bezahlt', 'class' => 'bg-amber-100 text-amber-700'],
                                        'rewarded' => ['label' => 'Belohnt', 'class' => 'bg-green-100 text-green-700'],
                                        default => ['label' => ucfirst($referral->status), 'class' => 'bg-gray-100 text-gray-700'],
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusConfig['class'] }}">
                                    {{ $statusConfig['label'] }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Pagination --}}
                @if($referrals->hasPages())
                    <div class="px-4 py-3 border-t border-base-200">
                        {{ $referrals->links() }}
                    </div>
                @endif
            @endif
        </div>
    @endif

    {{-- Rewards Tab --}}
    @if($activeTab === 'rewards')
        <div class="bg-white rounded-xl border border-base-200 overflow-hidden">
            @if($rewards->isEmpty())
                <div class="p-12 text-center">
                    <div class="w-12 h-12 rounded-full bg-base-100 flex items-center justify-center mx-auto mb-3">
                        <svg class="w-6 h-6 text-base-content/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/>
                        </svg>
                    </div>
                    <p class="text-sm font-medium text-base-content/60">Noch keine Belohnungen</p>
                    <p class="text-xs text-base-content/40 mt-1">Belohnungen werden gutgeschrieben, sobald Ihre Empfehlungen erfolgreich sind</p>
                </div>
            @else
                {{-- Desktop Table --}}
                <div class="hidden sm:block overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-base-200 bg-base-50">
                                <th class="text-left px-4 py-3 font-medium text-base-content/60">Art</th>
                                <th class="text-left px-4 py-3 font-medium text-base-content/60">Code</th>
                                <th class="text-left px-4 py-3 font-medium text-base-content/60">Details</th>
                                <th class="text-left px-4 py-3 font-medium text-base-content/60">Empfohlener Benutzer</th>
                                <th class="text-left px-4 py-3 font-medium text-base-content/60">Datum</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-base-200">
                            @foreach($rewards as $reward)
                                <tr class="hover:bg-base-50 transition-colors">
                                    <td class="px-4 py-3">
                                        @if($reward->reward_type === 'coupon')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-700">Gutschein</span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">Belohnung</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($reward->reward_type === 'coupon' && $reward->discountCode)
                                            <span class="font-mono text-sm" x-data="{ copied: false }">
                                                {{ $reward->discountCode->code }}
                                                <button type="button"
                                                        @click="navigator.clipboard.writeText('{{ $reward->discountCode->code }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                                        class="ml-1 text-base-content/40 hover:text-base-content/60 transition-colors">
                                                    <template x-if="!copied">
                                                        <svg class="w-3.5 h-3.5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                                    </template>
                                                    <template x-if="copied">
                                                        <svg class="w-3.5 h-3.5 inline text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                    </template>
                                                </button>
                                            </span>
                                        @else
                                            <span class="text-base-content/40">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-base-content/60">
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
                                    </td>
                                    <td class="px-4 py-3 text-base-content/60">{{ $reward->referral?->referredUser?->name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-base-content/60">{{ $reward->created_at->format('d.m.Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Mobile Cards --}}
                <div class="sm:hidden divide-y divide-base-200">
                    @foreach($rewards as $reward)
                        <div class="p-4 space-y-2">
                            <div class="flex items-center justify-between">
                                @if($reward->reward_type === 'coupon')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-700">Gutschein</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">Belohnung</span>
                                @endif
                                <span class="text-xs text-base-content/50">{{ $reward->created_at->format('d.m.Y') }}</span>
                            </div>
                            @if($reward->reward_type === 'coupon' && $reward->discountCode)
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-sm bg-base-50 px-2 py-1 rounded">{{ $reward->discountCode->code }}</span>
                                    @if($reward->discountCode->discount)
                                        <span class="text-xs text-base-content/60">
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
                    <div class="px-4 py-3 border-t border-base-200">
                        {{ $rewards->links() }}
                    </div>
                @endif
            @endif
        </div>
    @endif

    {{-- How It Works --}}
    <div class="bg-base-50 rounded-xl border border-base-200 p-6">
        <h3 class="text-sm font-semibold text-base-content mb-4">So funktioniert's</h3>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="flex items-start gap-3">
                <div class="w-8 h-8 rounded-full bg-white border border-base-200 flex items-center justify-center text-sm font-bold shrink-0" style="color: var(--portal-primary, #3b82f6);">1</div>
                <div>
                    <p class="text-sm font-medium text-base-content">Link teilen</p>
                    <p class="text-xs text-base-content/60 mt-0.5">Kopieren Sie Ihren Empfehlungslink und teilen Sie ihn</p>
                </div>
            </div>
            <div class="flex items-start gap-3">
                <div class="w-8 h-8 rounded-full bg-white border border-base-200 flex items-center justify-center text-sm font-bold shrink-0" style="color: var(--portal-primary, #3b82f6);">2</div>
                <div>
                    <p class="text-sm font-medium text-base-content">Freund registriert sich</p>
                    <p class="text-xs text-base-content/60 mt-0.5">Ihr Freund erstellt ein Konto über Ihren Link</p>
                </div>
            </div>
            <div class="flex items-start gap-3">
                <div class="w-8 h-8 rounded-full bg-white border border-base-200 flex items-center justify-center text-sm font-bold shrink-0" style="color: var(--portal-primary, #3b82f6);">3</div>
                <div>
                    <p class="text-sm font-medium text-base-content">Belohnung erhalten</p>
                    <p class="text-xs text-base-content/60 mt-0.5">Sie erhalten automatisch Ihre Belohnung</p>
                </div>
            </div>
        </div>
    </div>
</div>
