@extends('layouts.verwaltung')

@section('title', 'Verwaltung — Übersicht')

@section('content')
    {{-- Page Header --}}
    <div class="dash-page-header">
        <div>
            <h1 class="dash-page-title">Übersicht</h1>
            <p class="dash-page-subtitle">
                {{ $currentTenant->name ?? config('app.name') }} — Verwaltungskonsole
            </p>
        </div>
    </div>

    {{-- KPI Stats (Livewire — with period filter) --}}
    <div class="dash-section" style="margin-top:0;">
        @livewire('verwaltung.stats-overview')
    </div>

    {{-- Two-column: Recent Reviews + Recent Companies --}}
    <div class="dash-section grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="dash-card dash-card-padded">
            @livewire('verwaltung.recent-reviews')
        </div>

        <div class="dash-card dash-card-padded">
            @livewire('verwaltung.recent-companies')
        </div>
    </div>

    {{-- Schnell-Aktionen --}}
    <div class="dash-section">
        <div class="dash-card dash-card-padded">
            <h2 class="dash-card-header-title" style="margin-bottom:1rem;">Schnell-Aktionen</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                @php
                    $actions = [
                        ['route' => 'verwaltung.companies.index', 'label' => 'Firmen verwalten', 'desc' => 'Einträge bearbeiten & freigeben', 'icon' => 'M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21', 'perm' => 'manage_companies'],
                        ['route' => 'verwaltung.reviews.index', 'label' => 'Bewertungen moderieren', 'desc' => 'Neue Reviews prüfen', 'icon' => 'M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z', 'perm' => 'manage_reviews'],
                        ['route' => 'verwaltung.settings.general', 'label' => 'Einstellungen', 'desc' => 'Branding & Konfiguration', 'icon' => 'M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z M15 12a3 3 0 11-6 0 3 3 0 016 0z', 'perm' => 'update_settings'],
                        ['route' => 'verwaltung.users.index', 'label' => 'Team verwalten', 'desc' => 'Benutzer & Rollen', 'icon' => 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z', 'perm' => 'manage_team'],
                    ];
                @endphp

                @foreach($actions as $action)
                    @if($dashboardPermissions[$action['perm']] ?? false)
                        <a href="{{ route($action['route']) }}" class="dash-action-item group">
                            <div class="dash-action-icon">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $action['icon'] }}"/>
                                </svg>
                            </div>
                            <div>
                                <span class="dash-action-label">{{ $action['label'] }}</span>
                                <p class="dash-action-desc">{{ $action['desc'] }}</p>
                            </div>
                        </a>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
@endsection
