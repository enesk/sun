{{-- Premium testen ohne Zahlungsdaten, Theme sun-v2. Komponente: App\Livewire\Checkout\LocalSubscriptionCheckoutForm --}}
<div>
  @include('livewire.checkout.partials.sun-form', ['withPayment' => false, 'canAddDiscount' => false])
</div>
