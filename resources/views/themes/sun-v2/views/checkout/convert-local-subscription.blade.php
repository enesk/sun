{{--
    Testphase in ein bezahltes Abo umwandeln, Theme sun-v2
    (GET /checkout/convert-subscription/{subscriptionUuid}).
    Formular per Livewire checkout.convert-local-subscription-checkout-form. Texte: owner.checkout.*.
--}}
@extends(auth()->check() ? 'layouts.panel' : 'layouts.sun')

@section('title', __('portal.owner.checkout.title').(auth()->check() ? '' : ' | '.($currentTenant->name ?? config('app.name'))))
@section('meta_robots', 'noindex, nofollow')
@section('footer_compact', '1')

@section('content')
  @include('checkout.partials.shell', [
      'heading' => __('portal.owner.checkout.title'),
      'intro' => __('portal.owner.checkout.intro_convert'),
      'form' => 'checkout.convert-local-subscription-checkout-form',
  ])
@endsection
