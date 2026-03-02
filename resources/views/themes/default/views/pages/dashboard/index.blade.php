@extends('layouts.dashboard')

@section('title', 'Dashboard')

@section('content')
    {{-- Willkommen + Firmeninfo --}}
    <div class="dash-page-header">
        <div>
            <h1 class="dash-page-title">
                Willkommen zurück{{ auth()->user()->name ? ', ' . auth()->user()->name : '' }}!
            </h1>
            <p class="dash-page-subtitle">
                {{ $company->name }}
                @if($company->updated_at)
                    · Letzte Änderung: {{ $company->updated_at->diffForHumans() }}
                @endif
            </p>
        </div>
    </div>

    {{-- KPI Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-8">
        {{-- Aufrufe --}}
        <div class="dash-stat-card">
            <div class="flex items-center gap-2 mb-1">
                <svg class="w-4 h-4" style="color: var(--portal-primary)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                <span class="dash-stat-label">Aufrufe</span>
            </div>
            <span class="dash-stat-value">{{ number_format($stats['page_views']) }}</span>
            @include('partials.dashboard.stat-trend', ['change' => $stats['page_views_change']])
        </div>

        {{-- Durchschnittliche Bewertung --}}
        <div class="dash-stat-card">
            <div class="flex items-center gap-2 mb-1">
                <svg class="w-4 h-4 text-portal-accent" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                </svg>
                <span class="dash-stat-label">Rating</span>
            </div>
            <span class="dash-stat-value">
                {{ $stats['rating'] > 0 ? number_format($stats['rating'], 1) : '—' }}
            </span>
            <span class="dash-stat-sub">
                @if($stats['rating_count'] > 0)
                    {{ $stats['rating_count'] }} {{ $stats['rating_count'] === 1 ? 'Bewertung' : 'Bewertungen' }}
                @else
                    Noch keine Bewertungen
                @endif
            </span>
        </div>

        {{-- Bewertungen --}}
        <div class="dash-stat-card">
            <div class="flex items-center gap-2 mb-1">
                <svg class="w-4 h-4" style="color: var(--dash-text-muted)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                </svg>
                <span class="dash-stat-label">Reviews</span>
            </div>
            <span class="dash-stat-value">{{ $stats['reviews_total'] }}</span>
            @if($stats['reviews_pending'] > 0)
                <span class="dash-stat-sub" style="color: var(--portal-accent); font-weight: 500;">{{ $stats['reviews_pending'] }} wartend</span>
            @else
                <span class="dash-stat-sub">{{ $stats['reviews_approved'] }} freigegeben</span>
            @endif
        </div>

        {{-- Kontaktanfragen --}}
        <div class="dash-stat-card">
            <div class="flex items-center gap-2 mb-1">
                <svg class="w-4 h-4" style="color: var(--portal-primary)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                </svg>
                <span class="dash-stat-label">Kontakte</span>
            </div>
            <span class="dash-stat-value">{{ number_format($stats['contact_clicks']) }}</span>
            @include('partials.dashboard.stat-trend', ['change' => $stats['contact_clicks_change']])
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Linke Spalte: Profil-Vollständigkeit + Aktionen --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Profil-Vollständigkeit --}}
            <div class="dash-card dash-card-padded">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="dash-card-header-title">Profil-Vollständigkeit</h2>
                    <span class="text-sm font-bold" style="color: {{ $profileCompletion['percentage'] === 100 ? 'var(--dash-success)' : 'var(--portal-primary)' }}">
                        {{ $profileCompletion['percentage'] }}%
                    </span>
                </div>

                {{-- Progress Bar --}}
                <div class="dash-progress mb-4" role="progressbar" aria-valuenow="{{ $profileCompletion['percentage'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="Profil-Vollständigkeit">
                    <div class="dash-progress-fill {{ $profileCompletion['percentage'] === 100 ? 'dash-progress-fill-success' : '' }}"
                         style="width: {{ $profileCompletion['percentage'] }}%"></div>
                </div>

                {{-- Fehlende Felder --}}
                @php $missingFields = collect($profileCompletion['fields'])->where('filled', false); @endphp
                @if($missingFields->count() > 0)
                    <div class="space-y-2">
                        <p class="text-sm" style="color: var(--dash-text-secondary)">Noch ausfüllen:</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach($missingFields as $key => $field)
                                <a href="{{ route('portal.owner.edit') }}" class="dash-chip">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    {{ $field['label'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @else
                    <p class="text-sm flex items-center gap-1" style="color: var(--dash-success)">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Ihr Profil ist vollständig!
                    </p>
                @endif
            </div>

            {{-- Schnell-Aktionen --}}
            <div class="dash-card dash-card-padded">
                <h2 class="dash-card-header-title mb-3">Schnell-Aktionen</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <a href="{{ route('portal.owner.edit') }}" class="dash-action-item">
                        <div class="dash-action-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </div>
                        <div>
                            <span class="dash-action-label">Profil bearbeiten</span>
                            <p class="dash-action-desc">Daten, Bilder, Beschreibung</p>
                        </div>
                    </a>

                    <a href="{{ $company->portal_url }}" target="_blank" class="dash-action-item">
                        <div class="dash-action-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                            </svg>
                        </div>
                        <div>
                            <span class="dash-action-label">Firmenseite ansehen</span>
                            <p class="dash-action-desc">So sehen Kunden Ihren Eintrag</p>
                        </div>
                    </a>

                    <a href="{{ route('portal.owner.reviews') }}" class="dash-action-item">
                        <div class="dash-action-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                            </svg>
                        </div>
                        <div>
                            <span class="dash-action-label">Bewertungen</span>
                            <p class="dash-action-desc">{{ $stats['reviews_total'] }} gesamt, {{ $stats['reviews_pending'] }} wartend</p>
                        </div>
                    </a>

                    @if(!$company->is_premium)
                        <a href="{{ route('portal.owner.premium') }}" class="dash-action-item dash-action-item-accent">
                            <div class="dash-action-icon dash-action-icon-accent">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                                </svg>
                            </div>
                            <div>
                                <span class="dash-action-label">Premium werden</span>
                                <p class="dash-action-desc">Mehr Sichtbarkeit & Features</p>
                            </div>
                        </a>
                    @endif
                </div>
            </div>
        </div>

        {{-- Rechte Spalte: Neueste Bewertungen --}}
        <div class="space-y-6">
            <div class="dash-card dash-card-padded">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="dash-card-header-title">Neueste Bewertungen</h2>
                    @if($stats['reviews_total'] > 0)
                        <a href="{{ route('portal.owner.reviews') }}" class="text-xs link-portal">Alle ansehen</a>
                    @endif
                </div>

                @if($recentReviews->isEmpty())
                    <div class="dash-empty">
                        <svg class="dash-empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                        <p class="dash-empty-title">Noch keine Bewertungen</p>
                        <p class="dash-empty-description">Bewertungen erscheinen hier, sobald Kunden sie abgeben.</p>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach($recentReviews as $review)
                            <div class="p-3 rounded-lg" style="background: var(--dash-bg); border: 1px solid var(--dash-border);">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-sm font-medium" style="color: var(--dash-text-primary)">{{ $review->author_name ?: 'Anonym' }}</span>
                                    <span class="text-xs" style="color: var(--dash-text-muted)">{{ $review->created_at->diffForHumans() }}</span>
                                </div>
                                {{-- Star Rating --}}
                                <div class="flex items-center gap-0.5 mb-1" aria-label="{{ $review->rating }} von 5 Sternen">
                                    @for($i = 1; $i <= 5; $i++)
                                        <svg class="w-3.5 h-3.5 {{ $i <= $review->rating ? 'text-portal-accent' : '' }}" style="{{ $i > $review->rating ? 'color: var(--dash-text-muted)' : '' }}" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                                        </svg>
                                    @endfor
                                </div>
                                @if($review->title)
                                    <p class="text-sm font-medium" style="color: var(--dash-text-primary)">{{ $review->title }}</p>
                                @endif
                                @if($review->body)
                                    <p class="text-xs line-clamp-2 mt-0.5" style="color: var(--dash-text-secondary)">{{ $review->body }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Status-Info Card --}}
            <div class="dash-card dash-card-padded">
                <h2 class="dash-card-header-title mb-3">Ihr Eintrag</h2>
                <div class="dash-dl">
                    <div class="dash-dl-item">
                        <dt class="dash-dl-label">Status</dt>
                        <dd>
                            @if($company->is_active)
                                <span class="dash-badge dash-badge-success">Aktiv</span>
                            @else
                                <span class="dash-badge dash-badge-danger">Inaktiv</span>
                            @endif
                        </dd>
                    </div>
                    <div class="dash-dl-item">
                        <dt class="dash-dl-label">Plan</dt>
                        <dd>
                            @if($company->is_premium)
                                <span class="dash-badge dash-badge-premium">Premium</span>
                            @else
                                <span class="dash-badge dash-badge-neutral">Kostenlos</span>
                            @endif
                        </dd>
                    </div>
                    <div class="dash-dl-item">
                        <dt class="dash-dl-label">Verifiziert</dt>
                        <dd>
                            @if($company->is_verified)
                                <svg class="w-5 h-5" style="color: var(--dash-success)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-label="Verifiziert">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                            @else
                                <span class="text-xs" style="color: var(--dash-text-muted)">Noch nicht</span>
                            @endif
                        </dd>
                    </div>
                    <div class="dash-dl-item">
                        <dt class="dash-dl-label">Kategorien</dt>
                        <dd class="dash-dl-value text-right">
                            {{ $company->categories->pluck('name')->take(2)->join(', ') }}
                            @if($company->categories->count() > 2)
                                <span style="color: var(--dash-text-muted)">+{{ $company->categories->count() - 2 }}</span>
                            @endif
                        </dd>
                    </div>
                    <div class="dash-dl-item">
                        <dt class="dash-dl-label">Eingetragen</dt>
                        <dd class="dash-dl-value">{{ $company->created_at->format('d.m.Y') }}</dd>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
