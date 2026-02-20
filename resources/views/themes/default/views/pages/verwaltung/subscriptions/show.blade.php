@extends('layouts.verwaltung')

@section('title', ($subscription->plan->name ?? 'Abonnement') . ' — Verwaltung')

@section('content')
    {{-- Page Header --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-base-content">{{ $subscription->plan->name ?? 'Abonnement' }}</h1>
            <p class="text-sm text-base-content/60 mt-1">Details zum Abonnement</p>
        </div>
        <a href="{{ route('verwaltung.subscriptions.index') }}"
           class="btn-portal btn-portal-ghost text-sm">
            ← Zurück
        </a>
    </div>

    {{-- Subscription Detail --}}
    @livewire('verwaltung.subscription-detail', ['subscriptionUuid' => $subscription->uuid])
@endsection
