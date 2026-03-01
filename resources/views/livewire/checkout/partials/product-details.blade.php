<div class="md:sticky md:top-20">

    <h2 class="checkout-card__title">Produktdetails</h2>

    <div class="checkout-plan-card">
        @php
            $cartItem = $cartDto->items[0];
        @endphp

        {{-- Product Header --}}
        <div class="checkout-plan-header">
            <div class="checkout-plan-icon">
                {{ substr($product->name, 0, 1) }}
            </div>
            <div>
                <div class="checkout-plan-name">{{ $product->name }}</div>
                @if ($product->description)
                    <div class="checkout-plan-interval">{{ $product->description }}</div>
                @endif
                @if ($product->max_quantity == 1)
                    <div class="text-xs" style="color: #64748B;">Anzahl: {{ $cartItem->quantity }}</div>
                @endif
            </div>
        </div>

        {{-- Quantity Picker --}}
        @if ($product->max_quantity == 0 || $product->max_quantity > 1)
            <div class="flex gap-4">
                <livewire:checkout.product-quantity :product="$product" />
            </div>
        @endif

        {{-- Tenant Picker --}}
        <div class="flex gap-4">
            @inject('tenantCreationService', 'App\Services\TenantCreationService')

            @if ($tenantCreationService->findUserTenantsForNewOrder(auth()->user())->count() > 0)
                <livewire:checkout.product-tenant-picker />
            @endif
        </div>

        {{-- Features --}}
        @if ($product->features && count($product->features) > 0)
            <div style="font-size: 0.875rem; font-weight: 600; color: var(--portal-primary-dark, #1E3A5F); margin: 1.25rem 0 0.75rem;">
                Das ist enthalten:
            </div>
            <ul class="checkout-features">
                @foreach($product->features as $feature)
                    <li>
                        <span class="checkout-check-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        </span>
                        {{ $feature['feature'] }}
                    </li>
                @endforeach
            </ul>
        @endif

        {{-- Totals --}}
        <livewire:checkout.product-totals :totals="$totals" :product="$product" page="{{ request()->fullUrl() }}"/>

    </div>
</div>
