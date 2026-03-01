<div>
    @php $isDiscountCodeAdded = !empty($addedCode); @endphp
    <div x-data="{ discountFormVisible: @js($isDiscountCodeAdded) }">
        <div class="text-end">
            <a href="#" class="text-sm mt-4 mb-2 inline-block" style="color: var(--portal-primary, #3B82F6);" x-on:click.prevent="discountFormVisible = !discountFormVisible"
               x-show="!discountFormVisible">Gutscheincode eingeben</a>
        </div>

        <div class="my-6" x-show="discountFormVisible">
            <hr class="my-4" style="border-color: #E2E8F0;" />

            @if (session('success'))
                <div class="text-xs flex flex-row gap-2 my-2">
                    @svg('check', 'h-4 w-4 stroke-primary-500')
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if (session('error'))
                <div class="text-xs flex flex-row gap-2 my-2">
                    @svg('error', 'h-4 w-4 stroke-primary-500')
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            @if ($isDiscountCodeAdded)
                <div class="flex flex-row items-center gap-3 justify-end">
                    <div class="rounded py-1 px-2 text-xs border border-dashed" style="border-color: var(--portal-primary, #3B82F6);">
                        {{ $addedCode }}
                    </div>

                    <a wire:click.prevent="remove" class="text-xs cursor-pointer" style="color: var(--portal-primary, #3B82F6);">
                        Rabatt entfernen
                    </a>
                </div>
            @else
                <div class="flex flex-row items-center gap-3 mt-6">
                    <x-input.field wire:model="code" placeholder="Gutscheincode" type="text" class="input-sm mx-0! px-0!"
                           value="{{ $addedCode ?? '' }}" disabled="{{ $isDiscountCodeAdded }}"/>

                    <x-button-link.primary-outline wire:click.prevent="add"
                                                   class="!text-primary-500 !border-primary-500 text-xs! py-1! whitespace-nowrap">
                        Einlösen
                    </x-button-link.primary-outline>
                </div>
            @endif
        </div>
    </div>

    <hr class="mb-6 mt-2" style="border-color: #E2E8F0;">
    <div class="checkout-price-row">
        <div style="color: var(--portal-primary-dark, #1E3A5F);">
            Preis
        </div>
        <div style="color: var(--portal-primary-dark, #1E3A5F);">
            @money($subtotal, $currencyCode)
        </div>
    </div>

    @if($discountAmount > 0)
        <div class="checkout-price-row">
            <div style="color: var(--portal-primary-dark, #1E3A5F);">
                Rabatt
            </div>
            <div style="color: var(--portal-primary-dark, #1E3A5F);">
                @money($discountAmount, $currencyCode)
            </div>
        </div>

        <hr class="my-6" style="border-color: #E2E8F0;">

        <div class="checkout-price-row">
            <div style="color: var(--portal-primary-dark, #1E3A5F);">
                Gesamt
            </div>
            <div style="color: var(--portal-primary-dark, #1E3A5F);">
                @money($amountDue, $currencyCode)
            </div>
        </div>
    @endif

    <hr class="my-6" style="border-color: #E2E8F0;">
    <div class="checkout-price-row">
        <div class="checkout-price-total">
            Jetzt fällig
        </div>
        <div class="checkout-price-total">
            @money($amountDue, $currencyCode)
        </div>
    </div>

</div>
