@extends('layouts.app')

@section('title', 'Bestellung abschließen — ' . ($currentTenant->name ?? config('app.name')))

@section('content')

    {{-- Mini-Hero --}}
    <section class="checkout-hero" aria-label="Checkout">
        <h1 class="checkout-hero__title">Bestellung abschließen</h1>
        <p class="checkout-hero__subtitle">Sicher bezahlen, schnell und unkompliziert.</p>
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
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg>
                Sichere Zahlung via Stripe
            </span>
        </div>
    </section>

    {{-- Checkout Content --}}
    <section class="checkout-content">
        <livewire:checkout.product-checkout-form />
    </section>

@endsection
