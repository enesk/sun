@extends('layouts.verwaltung')

@section('title', 'Abonnements — Verwaltung')

@section('content')
    {{-- Page Header --}}
    <div class="dash-page-header">
        <div>
            <h1 class="dash-page-title">Abonnements</h1>
            <p class="dash-page-subtitle">Aktive Abonnements, Pläne und Verlängerungen verwalten</p>
        </div>
    </div>

    {{-- Subscription List (Livewire) --}}
    @livewire('verwaltung.subscription-list')
@endsection
