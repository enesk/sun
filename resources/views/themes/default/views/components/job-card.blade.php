{{-- Job Card Component — Öffentliche Jobbörse --}}
{{-- Usage: @include('components.job-card', ['job' => $job, 'layout' => 'list']) --}}
{{-- Layouts: 'list' (horizontal, default), 'compact' (für Sidebar/Related) --}}
@php
    $layout = $layout ?? 'list';
    $company = $job->company;
    $isPremium = $company && $company->is_premium;
@endphp

@if($layout === 'compact')
    {{-- ═══ COMPACT LAYOUT (Sidebar, Related Jobs) ═══ --}}
    <a href="{{ route('portal.jobs.show', $job->slug) }}"
       class="block p-4 rounded-xl border border-base-200 hover:border-portal-primary/30 hover:shadow-sm transition-all group">
        <div class="flex items-start gap-3">
            {{-- Firmenlogo --}}
            <div class="w-10 h-10 rounded-lg bg-base-200 overflow-hidden shrink-0">
                @if($company && $company->getFirstMediaUrl('logo', 'thumb'))
                    <img src="{{ $company->getFirstMediaUrl('logo', 'thumb') }}" alt="" class="w-full h-full object-cover" loading="lazy">
                @else
                    <div class="w-full h-full flex items-center justify-center text-base-content/30 text-sm font-bold">
                        {{ $company ? mb_substr($company->name, 0, 1) : '?' }}
                    </div>
                @endif
            </div>
            <div class="min-w-0 flex-1">
                <h4 class="text-sm font-semibold text-base-content truncate group-hover:text-portal-primary transition-colors">
                    {{ $job->title }}
                </h4>
                @if($company)
                    <p class="text-xs text-base-content/60 truncate">{{ $company->name }}</p>
                @endif
                <div class="flex items-center gap-2 mt-1">
                    <span class="job-badge job-badge--{{ $job->employment_type }}">
                        {{ $job->employment_type_label }}
                    </span>
                    @if($job->location_display)
                        <span class="text-[10px] text-base-content/40 truncate">{{ $job->location_display }}</span>
                    @endif
                </div>
            </div>
        </div>
    </a>

@else
    {{-- ═══ LIST LAYOUT (Übersichtsseite — Indeed-Pattern) ═══ --}}
    <article class="job-card {{ $isPremium ? 'job-card--premium' : '' }} group"
             aria-label="Stellenanzeige: {{ $job->title }}{{ $isPremium ? ' (Premium-Arbeitgeber)' : '' }}">
        <div class="p-5 sm:p-6">
            <div class="flex flex-col sm:flex-row sm:items-start gap-4">
                {{-- Firmenlogo --}}
                <div class="w-14 h-14 rounded-xl bg-base-200 overflow-hidden shrink-0 ring-1 ring-base-200">
                    @if($company && $company->getFirstMediaUrl('logo', 'thumb'))
                        <img src="{{ $company->getFirstMediaUrl('logo', 'thumb') }}" alt="" class="w-full h-full object-cover" loading="lazy">
                    @else
                        <div class="w-full h-full flex items-center justify-center text-base-content/20 text-xl font-bold bg-gradient-to-br from-base-200 to-base-300">
                            {{ $company ? mb_substr($company->name, 0, 1) : '?' }}
                        </div>
                    @endif
                </div>

                {{-- Content --}}
                <div class="flex-1 min-w-0">
                    {{-- Title + Badges --}}
                    <div class="flex flex-wrap items-center gap-2 mb-1">
                        <h3 class="text-lg font-bold text-base-content">
                            <a href="{{ route('portal.jobs.show', $job->slug) }}"
                               class="hover:text-portal-primary transition-colors focus:outline-none focus:underline">
                                {{ $job->title }}
                            </a>
                        </h3>
                        @if($isPremium)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-100 text-amber-700">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                Premium
                            </span>
                        @endif
                    </div>

                    {{-- Firmenname --}}
                    @if($company)
                        <p class="text-sm text-base-content/70 mb-2">
                            <a href="{{ $company->portal_url }}" class="hover:text-portal-primary transition-colors">
                                {{ $company->name }}
                            </a>
                        </p>
                    @endif

                    {{-- Meta: Typ, Ort, Gehalt --}}
                    <div class="job-card__meta mb-3">
                        {{-- Beschäftigungsart --}}
                        <span class="job-badge job-badge--{{ $job->employment_type }}">
                            {{ $job->employment_type_label }}
                        </span>

                        {{-- Standort --}}
                        @if($job->location_display)
                            <span class="job-card__meta-item">
                                <svg class="job-card__meta-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                {{ $job->location_display }}
                            </span>
                        @endif

                        {{-- Gehalt --}}
                        @if($job->salary_display)
                            <span class="job-card__meta-item">
                                <svg class="job-card__meta-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                {{ $job->salary_display }}
                            </span>
                        @endif
                    </div>

                    {{-- Beschreibung (gekürzt) --}}
                    <p class="job-card__excerpt">{{ Str::limit(strip_tags($job->description), 200) }}</p>
                </div>

                {{-- Rechte Seite: Datum + CTA --}}
                <div class="sm:text-right sm:shrink-0 flex sm:flex-col items-center sm:items-end gap-3">
                    <time datetime="{{ $job->published_at->toDateString() }}" class="job-card__time">
                        {{ $job->published_at->diffForHumans() }}
                    </time>
                    <a href="{{ route('portal.jobs.show', $job->slug) }}"
                       class="job-card__cta">
                        Details
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>

                    {{-- Ablauf-Hinweis --}}
                    @if($job->days_remaining <= 7 && $job->days_remaining > 0)
                        <span class="job-card__expiry">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Noch {{ $job->days_remaining }} {{ $job->days_remaining === 1 ? 'Tag' : 'Tage' }}
                        </span>
                    @endif
                </div>
            </div>
        </div>
    </article>
@endif
