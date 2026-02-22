<div>
    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="mb-4" role="alert">
            <div class="dash-flash dash-flash-success">
                <svg class="dash-flash-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>{{ session('success') }}</span>
            </div>
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4" role="alert">
            <div class="dash-flash dash-flash-error">
                <svg class="dash-flash-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                <span>{{ session('error') }}</span>
            </div>
        </div>
    @endif

    @if($subscriptions->isEmpty())
        {{-- Empty State --}}
        <div class="dash-card">
            <div class="dash-empty">
                <svg class="dash-empty-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
                </svg>
                <p class="dash-empty-title">Keine Abonnements</p>
                <p class="dash-empty-description">Es sind noch keine Abonnements für diesen Workspace vorhanden.</p>
            </div>
        </div>
    @else
        <div class="space-y-4">
            @foreach($subscriptions as $subscription)
                <div class="dash-card dash-card-padded">
                    {{-- Header: Plan + Status --}}
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <h3 class="text-lg font-semibold" style="color: var(--dash-text-primary);">
                                {{ $subscription->plan->name }}
                            </h3>
                            @if($subscription->plan->product)
                                <p class="text-sm" style="color: var(--dash-text-secondary);">{{ $subscription->plan->product->name }}</p>
                            @endif
                        </div>
                        <span class="dash-badge dash-badge-{{ $subscription->status_color === 'success' ? 'success' : 'warning' }}">
                            {{ $subscription->status_label }}
                        </span>
                    </div>

                    {{-- Past Due Warning --}}
                    @if($subscription->is_past_due)
                        <div class="dash-flash dash-flash-warning mb-4" role="alert" style="border-radius: 0.5rem;">
                            <strong>Achtung:</strong> Die Zahlung für dieses Abonnement ist überfällig. Bitte aktualisiere deine Zahlungsdaten.
                        </div>
                    @endif

                    {{-- Verification Required --}}
                    @if($subscription->requires_verification)
                        <div class="dash-flash dash-flash-info mb-4" role="alert" style="border-radius: 0.5rem;">
                            <strong>Verifizierung erforderlich:</strong> Bitte verifiziere deine Telefonnummer, um das Abonnement zu aktivieren.
                            <a href="{{ route('user.phone-verify') }}" style="color: inherit; text-decoration: underline; font-weight: 500; margin-left: 0.25rem;">Jetzt verifizieren →</a>
                        </div>
                    @endif

                    {{-- Key Details Grid --}}
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-4">
                        <div>
                            <span class="dash-stat-label">Preis</span>
                            <p class="text-sm font-medium mt-0.5" style="color: var(--dash-text-primary);">{{ $subscription->formatted_price }}</p>
                        </div>
                        @if(!$subscription->is_canceled_at_end_of_cycle && $subscription->ends_at)
                            <div>
                                <span class="dash-stat-label">Nächste Verlängerung</span>
                                <p class="text-sm font-medium mt-0.5" style="color: var(--dash-text-primary);">{{ $subscription->ends_at->format('d.m.Y') }}</p>
                            </div>
                        @endif
                        <div>
                            <span class="dash-stat-label">Auto-Verlängerung</span>
                            <p class="text-sm font-medium mt-0.5" style="color: {{ $subscription->is_canceled_at_end_of_cycle ? 'var(--dash-danger)' : 'var(--dash-success)' }};">
                                {{ $subscription->is_canceled_at_end_of_cycle ? 'Nein (gekündigt)' : 'Ja' }}
                            </p>
                        </div>
                        @if($subscription->paymentProvider)
                            <div>
                                <span class="dash-stat-label">Zahlungsanbieter</span>
                                <p class="text-sm font-medium mt-0.5" style="color: var(--dash-text-primary);">{{ ucfirst($subscription->paymentProvider->name) }}</p>
                            </div>
                        @endif
                    </div>

                    {{-- Active Discount --}}
                    @if($subscription->active_discount)
                        <div class="dash-flash dash-flash-success mb-4" role="status" style="border-radius: 0.5rem;">
                            <span class="font-medium">Aktiver Rabatt:</span>
                            <span>
                                @if($subscription->active_discount->type === \App\Constants\DiscountConstants::TYPE_PERCENTAGE)
                                    {{ $subscription->active_discount->amount }}%
                                @else
                                    {{ money($subscription->active_discount->amount, $subscription->currency->code) }}
                                @endif
                                @if($subscription->active_discount->valid_until)
                                    — gültig bis {{ $subscription->active_discount->valid_until->format('d.m.Y') }}
                                @endif
                            </span>
                        </div>
                    @endif

                    {{-- Actions --}}
                    <div class="flex flex-wrap items-center gap-2 pt-4" style="border-top: 1px solid var(--dash-border);">
                        <a href="{{ route('verwaltung.subscriptions.show', $subscription->uuid) }}"
                           class="dash-btn dash-btn-ghost dash-btn-sm">
                            Details anzeigen
                        </a>

                        @if($subscription->is_incomplete)
                            <a href="{{ route('checkout.convert-local-subscription', ['subscriptionUuid' => $subscription->uuid]) }}"
                               class="dash-btn dash-btn-primary dash-btn-sm">
                                Abonnement abschließen
                            </a>
                        @endif

                        @if($subscription->can_change_plan)
                            <a href="{{ route('checkout.subscription.change-plan', ['subscriptionUuid' => $subscription->uuid]) }}"
                               class="dash-btn dash-btn-ghost dash-btn-sm">
                                Plan ändern
                            </a>
                        @endif

                        @if($subscription->can_cancel)
                            <a href="{{ route('verwaltung.subscriptions.cancel', $subscription->uuid) }}"
                               class="dash-btn dash-btn-ghost dash-btn-sm" style="color: var(--dash-danger);">
                                Kündigen
                            </a>
                        @endif

                        @if($subscription->can_discard_cancellation)
                            <button wire:click="confirmDiscard('{{ $subscription->uuid }}')"
                                    class="dash-btn dash-btn-ghost dash-btn-sm" style="color: var(--dash-success);">
                                Kündigung widerrufen
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Discard Cancellation Modal --}}
    @if($showDiscardModal)
        <div class="dash-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="discard-cancel-title"
             x-data x-trap.noscroll="true"
             @keydown.escape.window="$wire.cancelDiscard()"
            <div class="dash-modal-backdrop" wire:click="cancelDiscard"></div>

            <div class="dash-modal">
                <div class="dash-modal-header">
                    <h3 id="discard-cancel-title" class="dash-modal-title">Kündigung widerrufen?</h3>
                </div>
                <div class="dash-modal-body">
                    <p class="text-sm" style="color: var(--dash-text-secondary);">
                        Das Abonnement wird wieder automatisch verlängert. Die nächste Zahlung erfolgt zum regulären Verlängerungsdatum.
                    </p>
                </div>
                <div class="dash-modal-footer">
                    <button wire:click="cancelDiscard" class="dash-btn dash-btn-secondary">
                        Abbrechen
                    </button>
                    <button wire:click="discardCancellation('{{ $discardingUuid }}')" class="dash-btn dash-btn-primary">
                        Ja, Kündigung widerrufen
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
