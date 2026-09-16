{{--
    Hinweis "Premium ist schon aktiv", Theme sun-v2 (GET /already-subscribed, ohne auth-Middleware).
    Texte: owner.checkout.already.*.
--}}
@extends(auth()->check() ? 'layouts.panel' : 'layouts.sun')

@section('title', __('portal.owner.checkout.already.title').(auth()->check() ? '' : ' | '.($currentTenant->name ?? config('app.name'))))
@section('meta_robots', 'noindex, nofollow')
@section('footer_compact', '1')

@section('content')
  @include('checkout.partials.done', [
      'icon' => 'check',
      'heading' => __('portal.owner.checkout.already.title'),
      'text' => __('portal.owner.checkout.already.text'),
      'ctaUrl' => route('portal.owner.premium'),
      'ctaLabel' => __('portal.owner.checkout.already.cta'),
  ])
@endsection
