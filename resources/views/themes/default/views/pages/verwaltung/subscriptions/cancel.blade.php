@extends('layouts.verwaltung')

@section('title', 'Abonnement kündigen — Verwaltung')

@section('content')
    {{-- Page Header --}}
    <div class="dash-page-header">
        <div>
            <h1 class="dash-page-title">Abonnement kündigen</h1>
            <p class="dash-page-subtitle">{{ $subscription->plan->name ?? '' }} — Kündigung einleiten</p>
        </div>
    </div>

    {{-- Cancel Form (Livewire) --}}
    @livewire('verwaltung.cancel-subscription-form', ['subscriptionUuid' => $subscription->uuid])
@endsection
