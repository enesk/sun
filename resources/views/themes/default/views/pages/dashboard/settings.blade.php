@extends('layouts.dashboard')

@section('title', 'Einstellungen')

@section('content')
    <div class="mb-6">
        <h1 class="text-xl sm:text-2xl font-bold text-base-content">Einstellungen</h1>
        <p class="text-sm text-base-content/60 mt-1">Verwalten Sie Ihre Kontoeinstellungen und Benachrichtigungen.</p>
    </div>

    <div class="max-w-2xl space-y-6">

        {{-- Konto-Information --}}
        <div class="card-portal">
            <h2 class="text-base font-semibold text-base-content mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-portal-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                Konto
            </h2>

            <dl class="space-y-3 text-sm">
                <div class="flex justify-between items-center py-2 border-b border-base-200">
                    <dt class="text-base-content/60">Name</dt>
                    <dd class="font-medium">{{ auth()->user()->name }}</dd>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-base-200">
                    <dt class="text-base-content/60">E-Mail</dt>
                    <dd class="font-medium">{{ auth()->user()->email }}</dd>
                </div>
                <div class="flex justify-between items-center py-2">
                    <dt class="text-base-content/60">Mitglied seit</dt>
                    <dd class="font-medium">{{ auth()->user()->created_at->format('d.m.Y') }}</dd>
                </div>
            </dl>
        </div>

        {{-- Benachrichtigungen (Platzhalter) --}}
        <div class="card-portal">
            <h2 class="text-base font-semibold text-base-content mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-portal-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
                Benachrichtigungen
            </h2>

            <div class="space-y-3">
                <label class="flex items-center justify-between py-2 cursor-pointer">
                    <div>
                        <span class="text-sm font-medium text-base-content">Neue Bewertungen</span>
                        <p class="text-xs text-base-content/50">E-Mail bei neuen Bewertungen erhalten</p>
                    </div>
                    <input type="checkbox" class="toggle toggle-sm" checked disabled>
                </label>

                <label class="flex items-center justify-between py-2 cursor-pointer">
                    <div>
                        <span class="text-sm font-medium text-base-content">Monatlicher Bericht</span>
                        <p class="text-xs text-base-content/50">Monatliche Zusammenfassung Ihrer Statistiken</p>
                    </div>
                    <input type="checkbox" class="toggle toggle-sm" disabled>
                </label>
            </div>

            <p class="text-xs text-base-content/40 mt-4 p-2 bg-base-200/50 rounded">
                Benachrichtigungseinstellungen werden in einer zukünftigen Version verfügbar sein.
            </p>
        </div>

        {{-- Eintrag löschen --}}
        <div class="card-portal border border-red-200">
            <h2 class="text-base font-semibold text-red-600 mb-2">Gefahrenzone</h2>
            <p class="text-sm text-base-content/60 mb-4">
                Das Löschen Ihres Eintrags ist dauerhaft und kann nicht rückgängig gemacht werden.
                Alle Bewertungen und Daten werden ebenfalls gelöscht.
            </p>
            <button type="button" class="btn btn-sm btn-outline border-red-300 text-red-600 hover:bg-red-50" disabled>
                Eintrag löschen (bald verfügbar)
            </button>
        </div>
    </div>
@endsection
