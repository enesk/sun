@extends('layouts.app')

@section('title', 'Bereits abonniert — ' . ($currentTenant->name ?? config('app.name')))

@section('content')

    <div class="checkout-thankyou">
        <div class="checkout-thankyou-card">
            <div class="checkout-thankyou-icon" style="background: rgba(var(--portal-primary-rgb, 59, 130, 246), 0.1); color: var(--portal-primary, #3b82f6);">
                <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                    <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
            </div>
            <h1 class="checkout-thankyou-title">Sie haben bereits ein aktives Abo</h1>
            <p class="checkout-thankyou-text">
                Ihr Premium-Abonnement ist bereits aktiv. Sie haben vollen Zugriff auf alle Premium-Funktionen.
                Falls Sie Ihr Abo verwalten oder den Plan wechseln möchten, nutzen Sie die Abo-Verwaltung in Ihrem Dashboard.
            </p>
            <a href="{{ route('portal.owner.premium') }}" class="checkout-thankyou-btn">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
                Abo-Verwaltung öffnen
            </a>
        </div>
    </div>

@endsection
