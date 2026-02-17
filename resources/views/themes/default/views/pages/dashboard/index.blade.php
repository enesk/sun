@extends('layouts.dashboard')

@section('title', 'Dashboard')

@section('content')
    {{-- Willkommen + Firmeninfo --}}
    <div class="mb-6">
        <h1 class="text-xl sm:text-2xl font-bold text-base-content">
            Willkommen zurück{{ auth()->user()->name ? ', ' . auth()->user()->name : '' }}!
        </h1>
        <p class="text-sm text-base-content/60 mt-1">
            {{ $company->name }}
            @if($company->updated_at)
                · Letzte Änderung: {{ $company->updated_at->diffForHumans() }}
            @endif
        </p>
    </div>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-8">
        {{-- Aufrufe (Platzhalter — noch keine View-Tracking-Daten) --}}
        <div class="card-portal flex flex-col">
            <div class="flex items-center gap-2 text-base-content/50 mb-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                <span class="text-xs font-medium uppercase tracking-wider">Aufrufe</span>
            </div>
            <span class="text-2xl font-bold text-base-content">—</span>
            <span class="text-xs text-base-content/40 mt-1">Bald verfügbar</span>
        </div>

        {{-- Durchschnittliche Bewertung --}}
        <div class="card-portal flex flex-col">
            <div class="flex items-center gap-2 text-base-content/50 mb-1">
                <svg class="w-4 h-4 text-portal-accent" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                </svg>
                <span class="text-xs font-medium uppercase tracking-wider">Rating</span>
            </div>
            <span class="text-2xl font-bold text-base-content">
                {{ $stats['rating'] > 0 ? number_format($stats['rating'], 1) : '—' }}
            </span>
            <span class="text-xs text-base-content/50 mt-1">
                @if($stats['rating_count'] > 0)
                    {{ $stats['rating_count'] }} {{ $stats['rating_count'] === 1 ? 'Bewertung' : 'Bewertungen' }}
                @else
                    Noch keine Bewertungen
                @endif
            </span>
        </div>

        {{-- Bewertungen --}}
        <div class="card-portal flex flex-col">
            <div class="flex items-center gap-2 text-base-content/50 mb-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                </svg>
                <span class="text-xs font-medium uppercase tracking-wider">Reviews</span>
            </div>
            <span class="text-2xl font-bold text-base-content">{{ $stats['reviews_total'] }}</span>
            @if($stats['reviews_pending'] > 0)
                <span class="text-xs text-portal-accent font-medium mt-1">{{ $stats['reviews_pending'] }} wartend</span>
            @else
                <span class="text-xs text-base-content/50 mt-1">{{ $stats['reviews_approved'] }} freigegeben</span>
            @endif
        </div>

        {{-- Kontaktanfragen (Platzhalter) --}}
        <div class="card-portal flex flex-col">
            <div class="flex items-center gap-2 text-base-content/50 mb-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                </svg>
                <span class="text-xs font-medium uppercase tracking-wider">Kontakte</span>
            </div>
            <span class="text-2xl font-bold text-base-content">—</span>
            <span class="text-xs text-base-content/40 mt-1">Bald verfügbar</span>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Linke Spalte: Profil-Vollständigkeit + Aktionen --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Profil-Vollständigkeit --}}
            <div class="card-portal">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-base font-semibold text-base-content">Profil-Vollständigkeit</h2>
                    <span class="text-sm font-bold {{ $profileCompletion['percentage'] === 100 ? 'text-green-600' : 'text-portal-primary' }}">
                        {{ $profileCompletion['percentage'] }}%
                    </span>
                </div>

                {{-- Progress Bar --}}
                <div class="w-full bg-base-200 rounded-full h-2.5 mb-4" role="progressbar" aria-valuenow="{{ $profileCompletion['percentage'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="Profil-Vollständigkeit">
                    <div class="h-2.5 rounded-full transition-all duration-500 {{ $profileCompletion['percentage'] === 100 ? 'bg-green-500' : 'bg-portal-primary' }}"
                         style="width: {{ $profileCompletion['percentage'] }}%"></div>
                </div>

                {{-- Fehlende Felder --}}
                @php $missingFields = collect($profileCompletion['fields'])->where('filled', false); @endphp
                @if($missingFields->count() > 0)
                    <div class="space-y-2">
                        <p class="text-sm text-base-content/60">Noch ausfüllen:</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach($missingFields as $key => $field)
                                <a href="{{ route('portal.owner.edit') }}"
                                   class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-base-200 text-xs text-base-content/70 hover:bg-portal-primary-light hover:text-portal-primary-dark transition-colors">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    {{ $field['label'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @else
                    <p class="text-sm text-green-600 flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Ihr Profil ist vollständig!
                    </p>
                @endif
            </div>

            {{-- Schnell-Aktionen --}}
            <div class="card-portal">
                <h2 class="text-base font-semibold text-base-content mb-3">Schnell-Aktionen</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <a href="{{ route('portal.owner.edit') }}"
                       class="flex items-center gap-3 p-3 rounded-lg border border-base-200 hover:border-portal-primary/30 hover:bg-portal-primary-light/50 transition-all group">
                        <div class="w-10 h-10 rounded-lg bg-portal-primary-light flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-portal-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </div>
                        <div>
                            <span class="text-sm font-medium text-base-content group-hover:text-portal-primary-dark">Profil bearbeiten</span>
                            <p class="text-xs text-base-content/50">Daten, Bilder, Beschreibung</p>
                        </div>
                    </a>

                    <a href="{{ route('portal.companies.show', $company->slug) }}" target="_blank"
                       class="flex items-center gap-3 p-3 rounded-lg border border-base-200 hover:border-portal-primary/30 hover:bg-portal-primary-light/50 transition-all group">
                        <div class="w-10 h-10 rounded-lg bg-portal-primary-light flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-portal-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                            </svg>
                        </div>
                        <div>
                            <span class="text-sm font-medium text-base-content group-hover:text-portal-primary-dark">Firmenseite ansehen</span>
                            <p class="text-xs text-base-content/50">So sehen Kunden Ihren Eintrag</p>
                        </div>
                    </a>

                    <a href="{{ route('portal.owner.reviews') }}"
                       class="flex items-center gap-3 p-3 rounded-lg border border-base-200 hover:border-portal-primary/30 hover:bg-portal-primary-light/50 transition-all group">
                        <div class="w-10 h-10 rounded-lg bg-portal-primary-light flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-portal-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                            </svg>
                        </div>
                        <div>
                            <span class="text-sm font-medium text-base-content group-hover:text-portal-primary-dark">Bewertungen</span>
                            <p class="text-xs text-base-content/50">{{ $stats['reviews_total'] }} gesamt, {{ $stats['reviews_pending'] }} wartend</p>
                        </div>
                    </a>

                    @if(!$company->is_premium)
                        <a href="{{ route('portal.owner.premium') }}"
                           class="flex items-center gap-3 p-3 rounded-lg border border-portal-accent/30 bg-portal-accent-light/30 hover:bg-portal-accent-light transition-all group">
                            <div class="w-10 h-10 rounded-lg bg-portal-accent-light flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5 text-portal-accent-dark" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                                </svg>
                            </div>
                            <div>
                                <span class="text-sm font-medium text-portal-accent-dark">Premium werden</span>
                                <p class="text-xs text-portal-accent-dark/60">Mehr Sichtbarkeit & Features</p>
                            </div>
                        </a>
                    @endif
                </div>
            </div>
        </div>

        {{-- Rechte Spalte: Neueste Bewertungen --}}
        <div class="space-y-6">
            <div class="card-portal">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-base font-semibold text-base-content">Neueste Bewertungen</h2>
                    @if($stats['reviews_total'] > 0)
                        <a href="{{ route('portal.owner.reviews') }}" class="text-xs link-portal">Alle ansehen</a>
                    @endif
                </div>

                @if($recentReviews->isEmpty())
                    <div class="text-center py-6">
                        <svg class="w-10 h-10 mx-auto text-base-content/20 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                        <p class="text-sm text-base-content/40">Noch keine Bewertungen</p>
                        <p class="text-xs text-base-content/30 mt-1">Bewertungen erscheinen hier, sobald Kunden sie abgeben.</p>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach($recentReviews as $review)
                            <div class="p-3 rounded-lg bg-base-100 border border-base-200">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-sm font-medium text-base-content">{{ $review->author_name ?: 'Anonym' }}</span>
                                    <span class="text-xs text-base-content/40">{{ $review->created_at->diffForHumans() }}</span>
                                </div>
                                {{-- Star Rating --}}
                                <div class="flex items-center gap-0.5 mb-1" aria-label="{{ $review->rating }} von 5 Sternen">
                                    @for($i = 1; $i <= 5; $i++)
                                        <svg class="w-3.5 h-3.5 {{ $i <= $review->rating ? 'text-portal-accent' : 'text-base-content/20' }}" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                                        </svg>
                                    @endfor
                                </div>
                                @if($review->title)
                                    <p class="text-sm font-medium text-base-content">{{ $review->title }}</p>
                                @endif
                                @if($review->body)
                                    <p class="text-xs text-base-content/60 line-clamp-2 mt-0.5">{{ $review->body }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Status-Info Card --}}
            <div class="card-portal">
                <h2 class="text-base font-semibold text-base-content mb-3">Ihr Eintrag</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-base-content/60">Status</dt>
                        <dd>
                            @if($company->is_active)
                                <span class="badge badge-sm bg-green-100 text-green-700 border-0">Aktiv</span>
                            @else
                                <span class="badge badge-sm bg-red-100 text-red-700 border-0">Inaktiv</span>
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-base-content/60">Plan</dt>
                        <dd>
                            @if($company->is_premium)
                                <span class="badge badge-sm badge-portal-accent">Premium</span>
                            @else
                                <span class="badge badge-sm bg-base-200 text-base-content/60 border-0">Kostenlos</span>
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-base-content/60">Verifiziert</dt>
                        <dd>
                            @if($company->is_verified)
                                <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-label="Verifiziert">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                            @else
                                <span class="text-xs text-base-content/40">Noch nicht</span>
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-base-content/60">Kategorien</dt>
                        <dd class="text-right">
                            {{ $company->categories->pluck('name')->take(2)->join(', ') }}
                            @if($company->categories->count() > 2)
                                <span class="text-base-content/40">+{{ $company->categories->count() - 2 }}</span>
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-base-content/60">Eingetragen</dt>
                        <dd>{{ $company->created_at->format('d.m.Y') }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
@endsection
