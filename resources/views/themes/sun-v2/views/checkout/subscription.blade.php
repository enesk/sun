{{--
    Checkout Premium-Abo im Theme sun-v2 (GET /checkout/plan/{planSlug}).
    Daten: SubscriptionCheckoutController::subscriptionCheckout(), Formular per
    Livewire checkout.subscription-checkout-form (Logik unveraendert).
    Angemeldet im Layout des Betriebsbereichs, Gaeste im Portal-Layout.
    Texte: lang/de/portal.php (owner.checkout.*).
--}}
@extends(auth()->check() ? 'layouts.panel' : 'layouts.sun')

@section('title', __('portal.owner.checkout.title').(auth()->check() ? '' : ' | '.($currentTenant->name ?? config('app.name'))))
@section('meta_robots', 'noindex, nofollow')
@section('footer_compact', '1')

@section('content')
  @include('checkout.partials.shell', [
      'heading' => __('portal.owner.checkout.title'),
      'intro' => __('portal.owner.checkout.intro'),
      'form' => 'checkout.subscription-checkout-form',
  ])
@endsection
