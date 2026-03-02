<div>
    {{-- Subscription Detail Card --}}
    <div class="dash-card dash-card-padded">
        {{-- Header: Plan + Status --}}
        <div class="flex items-start justify-between mb-6">
            <div>
                <h3 class="text-xl font-semibold" style="color: var(--dash-text-primary);">
                    {{ $subscription->plan->name }}
                </h3>
                @if($subscription->plan->product)
                    <p class="text-sm mt-1" style="color: var(--dash-text-secondary);">{{ $subscription->plan->product->name }}</p>
                @endif
            </div>
            <span class="dash-badge dash-badge-{{ $subscription->status_color === 'success' ? 'success' : 'warning' }}">
                {{ $subscription->status_label }}
            </span>
        </div>

        {{-- Past Due Warning --}}
        @if($subscription->is_past_due)
            <div class="dash-flash dash-flash-warning mb-4" role="alert" style="border-radius: 0.5rem;">
                <strong>Achtung:</strong> Die Zahlung für dieses Abonnement ist überfällig. Bitte aktualisieren Sie Ihre Zahlungsdaten.
            </div>
        @endif

        {{-- Cancellation Warning --}}
        @if($subscription->is_canceled_at_end_of_cycle)
            <div class="dash-flash dash-flash-warning mb-4" role="alert" style="border-radius: 0.5rem;">
                <svg class="dash-flash-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z"/></svg>
                <div>
                    <strong>Gekündigt.</strong>
                    @if($subscription->ends_at)
                        Ihr Abonnement läuft am {{ $subscription->ends_at->format('d.m.Y') }} aus. Bis dahin haben Sie weiterhin Zugriff auf alle Premium-Features.
                    @else
                        Ihr Abonnement wird nicht verlängert.
                    @endif
                </div>
            </div>
        @endif

        {{-- Verification Required --}}
        @if($subscription->requires_verification)
            <div class="dash-flash dash-flash-info mb-4" role="alert" style="border-radius: 0.5rem;">
                <strong>Verifizierung erforderlich:</strong> Bitte verifizieren Sie Ihre Telefonnummer, um das Abonnement zu aktivieren.
                <a href="{{ route('user.phone-verify') }}" style="color: inherit; text-decoration: underline; font-weight: 500; margin-left: 0.25rem;">Jetzt verifizieren →</a>
            </div>
        @endif

        {{-- Details Grid --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            <div class="dash-card" style="padding: 1rem;">
                <span class="dash-stat-label">Preis</span>
                <p class="text-base font-semibold mt-1" style="color: var(--dash-text-primary);">{{ $subscription->formatted_price }}</p>
            </div>

            @if($subscription->ends_at && !$subscription->is_canceled_at_end_of_cycle)
                <div class="dash-card" style="padding: 1rem;">
                    <span class="dash-stat-label">Nächste Verlängerung</span>
                    <p class="text-base font-semibold mt-1" style="color: var(--dash-text-primary);">{{ $subscription->ends_at->format('d.m.Y') }}</p>
                </div>
            @elseif($subscription->ends_at && $subscription->is_canceled_at_end_of_cycle)
                <div class="dash-card" style="padding: 1rem;">
                    <span class="dash-stat-label">Läuft aus am</span>
                    <p class="text-base font-semibold mt-1" style="color: var(--dash-danger);">{{ $subscription->ends_at->format('d.m.Y') }}</p>
                </div>
            @endif

            <div class="dash-card" style="padding: 1rem;">
                <span class="dash-stat-label">Auto-Verlängerung</span>
                <p class="text-base font-semibold mt-1" style="color: {{ $subscription->is_canceled_at_end_of_cycle ? 'var(--dash-danger)' : 'var(--dash-success)' }};">
                    {{ $subscription->is_canceled_at_end_of_cycle ? 'Deaktiviert' : 'Aktiv' }}
                </p>
            </div>

            @if($subscription->paymentProvider)
                <div class="dash-card" style="padding: 1rem;">
                    <span class="dash-stat-label">Zahlungsanbieter</span>
                    <p class="text-base font-semibold mt-1" style="color: var(--dash-text-primary);">{{ ucfirst($subscription->paymentProvider->name) }}</p>
                </div>
            @endif

            <div class="dash-card" style="padding: 1rem;">
                <span class="dash-stat-label">Abonnement-ID</span>
                <p class="text-sm font-mono mt-1" style="color: var(--dash-text-secondary);">{{ $subscription->uuid }}</p>
            </div>

            @if($subscription->created_at)
                <div class="dash-card" style="padding: 1rem;">
                    <span class="dash-stat-label">Erstellt am</span>
                    <p class="text-base font-semibold mt-1" style="color: var(--dash-text-primary);">{{ $subscription->created_at->format('d.m.Y') }}</p>
                </div>
            @endif
        </div>

        {{-- Active Discount --}}
        @if($subscription->active_discount)
            <div class="dash-flash dash-flash-success mb-6" role="status" style="border-radius: 0.5rem;">
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
        <div class="flex flex-wrap items-center gap-3 pt-4" style="border-top: 1px solid var(--dash-border);">
            @if($subscription->is_incomplete)
                <a href="{{ route('tenant.checkout.convert-local-subscription', ['subscriptionUuid' => $subscription->uuid]) }}"
                   class="dash-btn dash-btn-primary dash-btn-sm">
                    Abonnement abschließen
                </a>
            @endif

            {{-- Plan ändern ausgeblendet — nur 1 Bezahl-Plan (Premium) vorhanden --}}

            @if($subscription->can_cancel)
                <a href="{{ route('verwaltung.subscriptions.cancel', $subscription->uuid) }}"
                   class="dash-btn dash-btn-ghost dash-btn-sm" style="color: var(--dash-danger);">
                    Kündigen
                </a>
            @endif

            @if($subscription->can_discard_cancellation)
                <button wire:click="confirmDiscard"
                        class="dash-btn dash-btn-primary dash-btn-sm" style="background: var(--dash-success);">
                    Kündigung widerrufen
                </button>
            @endif
        </div>
    </div>

    {{-- Discard Cancellation Modal --}}
    @if($showDiscardModal)
        <div class="dash-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="discard-title"
             x-data x-trap.noscroll="true"
             @keydown.escape.window="$wire.cancelDiscard()">
            <div class="dash-modal-backdrop" wire:click="cancelDiscard"></div>

            <div class="dash-modal">
                <div class="dash-modal-header">
                    <h3 id="discard-title" class="dash-modal-title">Kündigung widerrufen?</h3>
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
                    <button wire:click="discardCancellation" class="dash-btn dash-btn-primary">
                        Ja, Kündigung widerrufen
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
