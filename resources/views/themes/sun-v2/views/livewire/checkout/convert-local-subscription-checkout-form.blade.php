{{-- Testphase in ein bezahltes Abo umwandeln, Theme sun-v2. Komponente: App\Livewire\Checkout\ConvertLocalSubscriptionCheckoutForm --}}
<div>
  @include('livewire.checkout.partials.sun-form', ['withPayment' => true, 'isTrialSkipped' => true])
</div>
