{{-- Checkout Premium-Abo, Theme sun-v2. Komponente: App\Livewire\Checkout\SubscriptionCheckoutForm --}}
<div>
  @include('livewire.checkout.partials.sun-form', ['withPayment' => true, 'isTrialSkipped' => $isTrialSkipped])
</div>
