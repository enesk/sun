{{--
    Premium testen ohne Zahlungsdaten im Theme sun-v2 (GET /checkout/plan/{planSlug},
    wenn app.trial_without_payment aktiv ist). Formular per Livewire
    checkout.local-subscription-checkout-form. Texte: owner.checkout.*.
--}}
@extends(auth()->check() ? 'layouts.panel' : 'layouts.sun')

@section('title', __('portal.owner.checkout.trial_title').(auth()->check() ? '' : ' | '.($currentTenant->name ?? config('app.name'))))
@section('meta_robots', 'noindex, nofollow')
@section('footer_compact', '1')

@section('content')
  @include('checkout.partials.shell', [
      'heading' => __('portal.owner.checkout.trial_title'),
      'intro' => __('portal.owner.checkout.trial_intro'),
      'form' => 'checkout.local-subscription-checkout-form',
  ])
@endsection
