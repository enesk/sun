@extends('layouts.verwaltung')

@section('title', $company->name . ' — Statistiken')

@section('content')
    <div x-data="statsCompany({{ $company->id }})" x-init="load()">
        {{-- Page Header --}}
        <div class="dash-page-header">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <a href="{{ route('verwaltung.statistics.index') }}" class="dash-btn dash-btn-sm dash-btn-ghost">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        Zurück
                    </a>
                </div>
                <h1 class="dash-page-title">{{ $company->name }}</h1>
                <p class="dash-page-subtitle">Detaillierte Statistiken für diesen Eintrag</p>
            </div>

            {{-- Perioden-Auswahl --}}
            <div class="flex items-center gap-1 mt-3 sm:mt-0" role="group" aria-label="Zeitraum auswählen">
                @foreach(['7d' => '7 Tage', '30d' => '30 Tage', '90d' => '90 Tage', '12m' => '12 Monate'] as $key => $label)
                    <button @click="setPeriod('{{ $key }}')"
                            :class="period === '{{ $key }}' ? 'dash-period-btn dash-period-btn-active' : 'dash-period-btn'"
                            :aria-current="period === '{{ $key }}' ? 'true' : false">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Loading --}}
        <template x-if="loading">
            <div class="flex items-center justify-center py-16">
                <svg class="animate-spin h-8 w-8" style="color: var(--portal-primary)" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
            </div>
        </template>

        <template x-if="!loading && summary">
            <div>
                {{-- KPI Cards --}}
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 sm:gap-4 mb-6">
                    <div class="dash-stat-card">
                        <div class="flex items-center gap-2 mb-1">
                            <svg class="w-4 h-4" style="color: var(--portal-primary)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            <span class="dash-stat-label">Aufrufe</span>
                        </div>
                        <span class="dash-stat-value" x-text="fmt(summary.page_views)"></span>
                        <template x-if="summary.page_views_change !== undefined">
                            <span :class="trendClass(summary.page_views_change)" x-text="trendText(summary.page_views_change)"></span>
                        </template>
                    </div>

                    <div class="dash-stat-card">
                        <div class="flex items-center gap-2 mb-1">
                            <svg class="w-4 h-4" style="color: var(--portal-primary)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                            </svg>
                            <span class="dash-stat-label">Kontakte</span>
                        </div>
                        <span class="dash-stat-value" x-text="fmt(summary.contact_clicks)"></span>
                        <template x-if="summary.contact_clicks_change !== undefined">
                            <span :class="trendClass(summary.contact_clicks_change)" x-text="trendText(summary.contact_clicks_change)"></span>
                        </template>
                    </div>

                    <div class="dash-stat-card">
                        <div class="flex items-center gap-2 mb-1">
                            <svg class="w-4 h-4" style="color: var(--portal-primary)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <span class="dash-stat-label">Suchanfragen</span>
                        </div>
                        <span class="dash-stat-value" x-text="fmt(summary.search_impressions)"></span>
                        <template x-if="summary.search_impressions_change !== undefined">
                            <span :class="trendClass(summary.search_impressions_change)" x-text="trendText(summary.search_impressions_change)"></span>
                        </template>
                    </div>
                </div>

                {{-- Kontakt-Breakdown --}}
                <template x-if="summary.contact_breakdown">
                    <div class="dash-card mb-6">
                        <div class="dash-card-header">
                            <h2 class="dash-card-title">Kontakt-Aufschlüsselung</h2>
                        </div>
                        <div class="dash-card-body">
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                <div class="text-center p-3">
                                    <div class="text-2xl font-semibold" x-text="fmt(summary.contact_breakdown.phone || 0)"></div>
                                    <div class="text-sm" style="color: var(--dash-text-secondary, #64748b)">Telefon</div>
                                </div>
                                <div class="text-center p-3">
                                    <div class="text-2xl font-semibold" x-text="fmt(summary.contact_breakdown.email || 0)"></div>
                                    <div class="text-sm" style="color: var(--dash-text-secondary, #64748b)">E-Mail</div>
                                </div>
                                <div class="text-center p-3">
                                    <div class="text-2xl font-semibold" x-text="fmt(summary.contact_breakdown.website || 0)"></div>
                                    <div class="text-sm" style="color: var(--dash-text-secondary, #64748b)">Website</div>
                                </div>
                                <div class="text-center p-3">
                                    <div class="text-2xl font-semibold" x-text="fmt(summary.contact_breakdown.map || 0)"></div>
                                    <div class="text-sm" style="color: var(--dash-text-secondary, #64748b)">Karte</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                {{-- Trend Chart --}}
                <template x-if="trend && trend.length > 0">
                    <div class="dash-card mb-6">
                        <div class="dash-card-header">
                            <h2 class="dash-card-title">Täglicher Trend</h2>
                        </div>
                        <div class="dash-card-body">
                            <div class="dash-chart-bars" style="height: 140px; display: flex; align-items: flex-end; gap: 2px;" role="img" aria-label="Täglicher Trend">
                                <template x-for="(day, i) in trend" :key="i">
                                    <div style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%;"
                                         @mouseenter="tooltip = day"
                                         @mouseleave="tooltip = null">
                                        <div :style="`height: ${maxViews > 0 ? Math.max((day.page_views / maxViews) * 100, 2) : 2}%; background: var(--portal-primary); border-radius: 2px 2px 0 0; width: 100%; min-height: 2px; opacity: 0.8;`"></div>
                                    </div>
                                </template>
                            </div>
                            <template x-if="tooltip">
                                <div class="mt-2 text-sm" style="color: var(--dash-text-secondary, #64748b)">
                                    <span x-text="tooltip.date"></span>:
                                    <strong x-text="fmt(tooltip.page_views)"></strong> Aufrufe,
                                    <strong x-text="fmt(tooltip.contact_clicks)"></strong> Kontakte,
                                    <strong x-text="fmt(tooltip.search_impressions)"></strong> Suchen
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                {{-- Top Referrer + Suchbegriffe --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    {{-- Top Referrer --}}
                    <template x-if="referrers && referrers.length > 0">
                        <div class="dash-card">
                            <div class="dash-card-header">
                                <h2 class="dash-card-title">Top-Herkunft</h2>
                            </div>
                            <div class="dash-card-body">
                                <template x-for="ref in referrers.slice(0, 10)" :key="ref.domain">
                                    <div class="flex items-center justify-between py-1.5 border-b last:border-0" style="border-color: var(--dash-border, #e2e8f0)">
                                        <span class="text-sm truncate" x-text="ref.domain"></span>
                                        <span class="text-sm font-medium ml-2" x-text="fmt(ref.count)"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    {{-- Top Suchbegriffe --}}
                    <template x-if="searchQueries && searchQueries.length > 0">
                        <div class="dash-card">
                            <div class="dash-card-header">
                                <h2 class="dash-card-title">Top-Suchbegriffe</h2>
                            </div>
                            <div class="dash-card-body">
                                <template x-for="sq in searchQueries.slice(0, 10)" :key="sq.query">
                                    <div class="flex items-center justify-between py-1.5 border-b last:border-0" style="border-color: var(--dash-border, #e2e8f0)">
                                        <span class="text-sm truncate" x-text="sq.query"></span>
                                        <span class="text-sm font-medium ml-2" x-text="fmt(sq.count)"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Wochen-Trend --}}
                <template x-if="weekly && weekly.length > 0">
                    <div class="dash-card mb-6">
                        <div class="dash-card-header">
                            <h2 class="dash-card-title">Wochen-Trend</h2>
                        </div>
                        <div class="dash-card-body overflow-x-auto">
                            <table class="dash-table w-full">
                                <thead>
                                    <tr>
                                        <th scope="col" class="text-left">Woche</th>
                                        <th scope="col" class="text-right">Aufrufe</th>
                                        <th scope="col" class="text-right">Kontakte</th>
                                        <th scope="col" class="text-right">Suchen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="w in weekly.slice(0, 12)" :key="w.week">
                                        <tr>
                                            <td class="text-left text-sm" x-text="w.week_start || ('KW ' + w.week)"></td>
                                            <td class="text-right" x-text="fmt(w.page_views)"></td>
                                            <td class="text-right" x-text="fmt(w.contact_clicks)"></td>
                                            <td class="text-right" x-text="fmt(w.search_impressions)"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>
            </div>
        </template>

        {{-- Empty State --}}
        <template x-if="!loading && !summary">
            <div class="dash-empty-state">
                <svg class="w-12 h-12 mx-auto mb-3" style="color: var(--dash-text-tertiary, #94a3b8)" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                <p>Noch keine Tracking-Daten für {{ $company->name }}.</p>
            </div>
        </template>
    </div>

    <script>
    function statsCompany(companyId) {
        return {
            companyId,
            period: '30d',
            loading: true,
            summary: null,
            trend: [],
            referrers: [],
            searchQueries: [],
            weekly: [],
            maxViews: 0,
            tooltip: null,

            async load() {
                this.loading = true;
                try {
                    const res = await fetch(`{{ url('/verwaltung/statistiken/api/company') }}/${this.companyId}?period=${this.period}`);
                    const data = await res.json();

                    this.summary = data.summary;
                    this.trend = data.trend || [];
                    this.referrers = data.referrers || [];
                    this.searchQueries = data.search_queries || [];
                    this.weekly = data.weekly || [];
                    this.maxViews = Math.max(...this.trend.map(d => d.page_views || 0), 1);
                } catch (e) {
                    console.error('Stats load error:', e);
                    this.summary = null;
                } finally {
                    this.loading = false;
                }
            },

            setPeriod(p) {
                this.period = p;
                this.load();
            },

            fmt(n) {
                return new Intl.NumberFormat('de-DE').format(n || 0);
            },

            trendClass(change) {
                if (change > 0) return 'dash-stat-trend dash-stat-trend-up';
                if (change < 0) return 'dash-stat-trend dash-stat-trend-down';
                return 'dash-stat-trend';
            },

            trendText(change) {
                if (change > 0) return '+' + Math.round(change) + '%';
                if (change < 0) return Math.round(change) + '%';
                return 'stabil';
            },
        };
    }
    </script>
@endsection
