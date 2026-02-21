@extends('layouts.verwaltung')

@section('title', ($subscription->plan->name ?? 'Abonnement') . ' — Verwaltung')

@section('content')
    {{-- Page Header --}}
    <div class="dash-page-header">
        <div>
            <h1 class="dash-page-title">{{ $subscription->plan->name ?? 'Abonnement' }}</h1>
            <p class="dash-page-subtitle">Details zum Abonnement</p>
        </div>
        <a href="{{ route('verwaltung.subscriptions.index') }}" class="dash-btn dash-btn-ghost dash-btn-sm">
            ← Zurück
        </a>
    </div>

    {{-- Subscription Detail --}}
    @livewire('verwaltung.subscription-detail', ['subscriptionUuid' => $subscription->uuid])
@endsection
