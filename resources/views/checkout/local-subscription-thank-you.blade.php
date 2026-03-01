@extends('layouts.app')

@section('title', 'Willkommen an Bord! — ' . ($currentTenant->name ?? config('app.name')))

@section('content')

    <div class="checkout-thankyou">
        <div class="checkout-thankyou-card">
            <div class="checkout-thankyou-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                    <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
            </div>
            <h1 class="checkout-thankyou-title">Willkommen an Bord!</h1>
            <p class="checkout-thankyou-text">
                Ihr 30-Tage-Trial ist aktiv. Sie haben jetzt Zugriff auf alle Premium-Funktionen.
                Entdecken Sie Ihr Dashboard und richten Sie Ihr Profil ein.
            </p>
            <a href="{{ route('verwaltung.companies.index') }}" class="checkout-thankyou-btn">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                Zum Firmenprofil
            </a>
        </div>
    </div>

@endsection
