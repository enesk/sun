{{--
    Erfolgsseite nach dem bezahlten Premium-Checkout, Theme sun-v2
    (SubscriptionCheckoutController::subscriptionCheckoutSuccess). Texte: owner.checkout.success.*.
--}}
@extends('layouts.panel')

@section('title', __('portal.owner.checkout.success.title'))

@section('content')
  @include('checkout.partials.done', [
      'icon' => 'sparkles',
      'heading' => __('portal.owner.checkout.success.title'),
      'text' => __('portal.owner.checkout.success.text_paid'),
      'steps' => true,
      'ctaUrl' => route('portal.owner.edit'),
      'ctaLabel' => __('portal.owner.checkout.success.cta'),
  ])
@endsection
