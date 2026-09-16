{{--
    Erfolgsseite nach dem Umwandeln der Testphase in ein bezahltes Abo, Theme sun-v2
    (SubscriptionCheckoutController::convertLocalSubscriptionCheckoutSuccess). Texte: owner.checkout.success.*.
--}}
@extends('layouts.panel')

@section('title', __('portal.owner.checkout.success.title'))

@section('content')
  @include('checkout.partials.done', [
      'icon' => 'check',
      'heading' => __('portal.owner.checkout.success.title'),
      'text' => __('portal.owner.checkout.success.text_convert'),
      'steps' => true,
      'ctaUrl' => route('portal.owner.edit'),
      'ctaLabel' => __('portal.owner.checkout.success.cta'),
  ])
@endsection
