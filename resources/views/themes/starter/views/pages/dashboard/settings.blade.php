@extends('layouts.dashboard')

@section('title', 'Einstellungen')

@section('content')
    <div class="dash-page-header">
        <div>
            <h1 class="dash-page-title">Einstellungen</h1>
            <p class="dash-page-subtitle">Verwalten Sie Ihre Kontoeinstellungen und Benachrichtigungen.</p>
        </div>
    </div>

    <div class="max-w-2xl space-y-6">

        {{-- Konto-Information --}}
        <div class="dash-card dash-card-padded">
            <h2 class="dash-card-header-title mb-4 flex items-center gap-2">
                <svg class="w-5 h-5" style="color: var(--portal-primary)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                Konto
            </h2>

            <div class="dash-dl">
                <div class="dash-dl-item">
                    <dt class="dash-dl-label">Name</dt>
                    <dd class="dash-dl-value">{{ auth()->user()->name }}</dd>
                </div>
                <div class="dash-dl-item">
                    <dt class="dash-dl-label">E-Mail</dt>
                    <dd class="dash-dl-value">{{ auth()->user()->email }}</dd>
                </div>
                <div class="dash-dl-item">
                    <dt class="dash-dl-label">Mitglied seit</dt>
                    <dd class="dash-dl-value">{{ auth()->user()->created_at->format('d.m.Y') }}</dd>
                </div>
            </div>
        </div>

        {{-- Benachrichtigungen --}}
        <div class="dash-card dash-card-padded">
            <h2 class="dash-card-header-title mb-4 flex items-center gap-2">
                <svg class="w-5 h-5" style="color: var(--portal-primary)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
                Benachrichtigungen
            </h2>

            <div class="space-y-3">
                <label class="flex items-center justify-between py-2 cursor-pointer">
                    <div>
                        <span class="text-sm font-medium" style="color: var(--dash-text-primary)">Neue Bewertungen</span>
                        <p class="text-xs" style="color: var(--dash-text-muted)">E-Mail bei neuen Bewertungen erhalten</p>
                    </div>
                    <input type="checkbox" class="toggle toggle-sm" checked disabled>
                </label>

                <label class="flex items-center justify-between py-2 cursor-pointer">
                    <div>
                        <span class="text-sm font-medium" style="color: var(--dash-text-primary)">Monatlicher Bericht</span>
                        <p class="text-xs" style="color: var(--dash-text-muted)">Monatliche Zusammenfassung Ihrer Statistiken</p>
                    </div>
                    <input type="checkbox" class="toggle toggle-sm" disabled>
                </label>
            </div>

            <p class="text-xs mt-4 p-2 rounded" style="color: var(--dash-text-muted); background-color: var(--dash-bg);">
                Benachrichtigungseinstellungen werden in einer zukünftigen Version verfügbar sein.
            </p>
        </div>

        {{-- Eintrag löschen --}}
        <div class="dash-card dash-card-padded dash-card-danger">
            <h2 class="dash-card-danger-title">Gefahrenzone</h2>
            <p class="text-sm mb-4" style="color: var(--dash-text-secondary)">
                Das Löschen Ihres Eintrags ist dauerhaft und kann nicht rückgängig gemacht werden.
                Alle Bewertungen und Daten werden ebenfalls gelöscht.
            </p>
            <button type="button" class="dash-btn dash-btn-sm" style="border-color: var(--dash-danger-border); color: var(--dash-danger);" disabled>
                Eintrag löschen (bald verfügbar)
            </button>
        </div>
    </div>
@endsection
