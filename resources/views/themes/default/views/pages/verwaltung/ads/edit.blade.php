@extends('layouts.verwaltung')

@section('title', $adSlot->name . ' bearbeiten — Verwaltung')

@section('content')
    {{-- Page Header --}}
    <div class="dash-page-header">
        <div>
            <h1 class="dash-page-title">Ad-Slot bearbeiten</h1>
            <p class="dash-page-subtitle">{{ $adSlot->name }}</p>
        </div>
        <div class="dash-page-actions">
            <a href="{{ route('verwaltung.ads.index') }}" class="dash-btn dash-btn-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/>
                </svg>
                Zurück
            </a>
        </div>
    </div>

    <div class="dash-card p-6">
        <form method="POST" action="{{ route('verwaltung.ads.update', $adSlot->id) }}">
            @csrf
            @method('PUT')
            @include('pages.verwaltung.ads._form', ['adSlot' => $adSlot])
        </form>
    </div>
@endsection
