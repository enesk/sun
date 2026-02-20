<div>
    {{-- Flash Messages --}}
    @if(session('success'))
        <div class="mb-4 p-4 rounded-xl bg-green-50 border border-green-200 text-green-800 text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm">
            {{ session('error') }}
        </div>
    @endif

    @if($subscriptions->isEmpty())
        {{-- Empty State --}}
        <div class="card-portal p-12 text-center">
            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-base-200/50 flex items-center justify-center">
                <svg class="w-8 h-8 text-base-content/30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
                </svg>
            </div>
            <h3 class="text-lg font-semibold text-base-content mb-1">Keine Abonnements</h3>
            <p class="text-sm text-base-content/60">Es sind noch keine Abonnements für diesen Workspace vorhanden.</p>
        </div>
    @else
        <div class="space-y-4">
            @foreach($subscriptions as $subscription)
                <div class="card-portal p-6">
                    {{-- Header: Plan + Status --}}
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-base-content">
                                {{ $subscription->plan->name }}
                            </h3>
                            @if($subscription->plan->product)
                                <p class="text-sm text-base-content/60">{{ $subscription->plan->product->name }}</p>
                            @endif
                        </div>
                        <span class="badge-portal badge-portal-{{ $subscription->status_color === 'success' ? 'success' : 'warning' }}">
                            {{ $subscription->status_label }}
                        </span>
                    </div>

                    {{-- Past Due Warning --}}
                    @if($subscription->is_past_due)
                        <div class="mb-4 p-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm">
                            <strong>Achtung:</strong> Die Zahlung für dieses Abonnement ist überfällig. Bitte aktualisiere deine Zahlungsdaten.
                        </div>
                    @endif

                    {{-- Verification Required --}}
                    @if($subscription->requires_verification)
                        <div class="mb-4 p-3 rounded-lg bg-blue-50 border border-blue-200 text-blue-800 text-sm">
                            <strong>Verifizierung erforderlich:</strong> Bitte verifiziere deine Telefonnummer, um das Abonnement zu aktivieren.
                            <a href="{{ route('user.phone-verify') }}" class="underline font-medium ml-1">Jetzt verifizieren →</a>
                        </div>
                    @endif

                    {{-- Key Details Grid --}}
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-4">
                        <div>
                            <span class="text-xs text-base-content/50 uppercase tracking-wider">Preis</span>
                            <p class="text-sm font-medium text-base-content mt-0.5">{{ $subscription->formatted_price }}</p>
                        </div>
                        @if(!$subscription->is_canceled_at_end_of_cycle && $subscription->ends_at)
                            <div>
                                <span class="text-xs text-base-content/50 uppercase tracking-wider">Nächste Verlängerung</span>
                                <p class="text-sm font-medium text-base-content mt-0.5">{{ $subscription->ends_at->format('d.m.Y') }}</p>
                            </div>
                        @endif
                        <div>
                            <span class="text-xs text-base-content/50 uppercase tracking-wider">Auto-Verlängerung</span>
                            <p class="text-sm font-medium mt-0.5 {{ $subscription->is_canceled_at_end_of_cycle ? 'text-red-600' : 'text-green-600' }}">
                                {{ $subscription->is_canceled_at_end_of_cycle ? 'Nein (gekündigt)' : 'Ja' }}
                            </p>
                        </div>
                        @if($subscription->paymentProvider)
                            <div>
                                <span class="text-xs text-base-content/50 uppercase tracking-wider">Zahlungsanbieter</span>
                                <p class="text-sm font-medium text-base-content mt-0.5">{{ ucfirst($subscription->paymentProvider->name) }}</p>
                            </div>
                        @endif
                    </div>

                    {{-- Active Discount --}}
                    @if($subscription->active_discount)
                        <div class="mb-4 p-3 rounded-lg bg-green-50 border border-green-100 text-sm">
                            <span class="font-medium text-green-800">Aktiver Rabatt:</span>
                            <span class="text-green-700">
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
                    <div class="flex flex-wrap items-center gap-2 pt-4 border-t border-base-200">
                        <a href="{{ route('verwaltung.subscriptions.show', $subscription->uuid) }}"
                           class="btn-portal btn-portal-ghost text-sm">
                            Details anzeigen
                        </a>

                        @if($subscription->is_incomplete)
                            <a href="{{ route('checkout.convert-local-subscription', ['subscriptionUuid' => $subscription->uuid]) }}"
                               class="btn-portal btn-portal-primary text-sm">
                                Abonnement abschließen
                            </a>
                        @endif

                        @if($subscription->can_change_plan)
                            <a href="{{ route('checkout.subscription.change-plan', ['subscriptionUuid' => $subscription->uuid]) }}"
                               class="btn-portal btn-portal-ghost text-sm">
                                Plan ändern
                            </a>
                        @endif

                        @if($subscription->can_cancel)
                            <a href="{{ route('verwaltung.subscriptions.cancel', $subscription->uuid) }}"
                               class="btn-portal btn-portal-ghost text-sm text-red-600 hover:text-red-700">
                                Kündigen
                            </a>
                        @endif

                        @if($subscription->can_discard_cancellation)
                            <button wire:click="confirmDiscard('{{ $subscription->uuid }}')"
                                    class="btn-portal btn-portal-ghost text-sm text-green-600 hover:text-green-700">
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
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" wire:click.self="cancelDiscard">
            <div class="bg-white rounded-2xl shadow-xl max-w-md w-full mx-4 p-6">
                <h3 class="text-lg font-semibold text-base-content mb-2">Kündigung widerrufen?</h3>
                <p class="text-sm text-base-content/60 mb-6">
                    Das Abonnement wird wieder automatisch verlängert. Die nächste Zahlung erfolgt zum regulären Verlängerungsdatum.
                </p>
                <div class="flex justify-end gap-3">
                    <button wire:click="cancelDiscard" class="btn-portal btn-portal-ghost text-sm">
                        Abbrechen
                    </button>
                    <button wire:click="discardCancellation('{{ $discardingUuid }}')" class="btn-portal btn-portal-primary text-sm">
                        Ja, Kündigung widerrufen
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
