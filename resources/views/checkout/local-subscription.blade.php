@extends('layouts.app')

@section('title', 'Premium testen — ' . ($currentTenant->name ?? config('app.name')))

@section('content')

    {{-- Mini-Hero --}}
    <section class="checkout-hero" aria-label="Checkout">
        <div class="checkout-hero__badge">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2z"/></svg>
            30 Tage kostenlos
        </div>
        <h1 class="checkout-hero__title">Premium testen — ohne Risiko</h1>
        <p class="checkout-hero__subtitle">Alle Premium-Funktionen 30 Tage lang kostenlos. Keine Kreditkarte nötig.</p>
        <div class="checkout-hero__trust-row">
            <span class="checkout-hero__trust-item">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                SSL-verschlüsselt
            </span>
            <span class="checkout-hero__trust-item">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                DSGVO-konform
            </span>
            <span class="checkout-hero__trust-item">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                Sofort aktiv
            </span>
        </div>
    </section>

    {{-- Checkout Content --}}
    <section class="checkout-content">
        <livewire:checkout.local-subscription-checkout-form />
    </section>

@endsection
