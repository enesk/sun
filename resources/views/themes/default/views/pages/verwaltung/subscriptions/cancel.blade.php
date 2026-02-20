@extends('layouts.verwaltung')

@section('title', 'Abonnement kündigen — Verwaltung')

@section('content')
    {{-- Page Header --}}
    <div class="mb-6">
        <h1 class="text-xl sm:text-2xl font-bold text-base-content">Abonnement kündigen</h1>
        <p class="text-sm text-base-content/60 mt-1">{{ $subscription->plan->name ?? '' }} — Kündigung einleiten</p>
    </div>

    {{-- Cancel Form (Livewire) --}}
    @livewire('verwaltung.cancel-subscription-form', ['subscriptionUuid' => $subscription->uuid])
@endsection
