@extends('layouts.app')

@section('title', $job->title . ' — ' . ($job->company->name ?? '') . ' — ' . ($currentTenant->name ?? config('app.name')))
@section('meta_description', Str::limit(strip_tags($job->description), 160))

@section('content')

    {{-- Schema.org: JobPosting --}}
    @push('scripts')
    <script type="application/ld+json">
    {!! json_encode(array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'JobPosting',
        'title' => $job->title,
        'description' => strip_tags($job->description),
        'datePosted' => $job->published_at?->toIso8601String(),
        'validThrough' => $job->expires_at?->toIso8601String(),
        'employmentType' => match($job->employment_type) {
            'vollzeit' => 'FULL_TIME',
            'teilzeit' => 'PART_TIME',
            'minijob' => 'PART_TIME',
            'ausbildung' => 'INTERN',
            'praktikum' => 'INTERN',
            default => 'OTHER',
        },
        'hiringOrganization' => $job->company ? [
            '@type' => 'Organization',
            'name' => $job->company->name,
            'sameAs' => $job->company->portal_url,
            'logo' => null,
        ] : null,
        'jobLocation' => $job->location_display ? [
            '@type' => 'Place',
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => $job->location_display,
                'addressCountry' => 'DE',
            ],
        ] : null,
        'baseSalary' => ($job->salary_min || $job->salary_max) ? [
            '@type' => 'MonetaryAmount',
            'currency' => 'EUR',
            'value' => [
                '@type' => 'QuantitativeValue',
                'minValue' => $job->salary_min,
                'maxValue' => $job->salary_max,
                'unitText' => match($job->salary_type) {
                    'hourly' => 'HOUR',
                    'monthly' => 'MONTH',
                    'yearly' => 'YEAR',
                    default => 'MONTH',
                },
            ],
        ] : null,
    ]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
    @endpush

    {{-- Breadcrumb --}}
    @include('components.breadcrumb', ['items' => $breadcrumb])

    <div class="container mx-auto px-4 pb-12">

        {{-- Abgelaufen-Banner --}}
        @if($job->is_expired)
            <div class="mb-6 p-4 rounded-xl bg-amber-50 border border-amber-200 flex items-center gap-3" role="alert">
                <svg class="w-5 h-5 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <div>
                    <p class="text-sm font-medium text-amber-800">Diese Stellenanzeige ist abgelaufen</p>
                    <p class="text-xs text-amber-600 mt-0.5">Die Bewerbungsfrist ist am {{ $job->expires_at->format('d.m.Y') }} abgelaufen.</p>
                </div>
            </div>
        @endif

        {{-- Success-Banner nach Bewerbung --}}
        @if(session('application_success'))
            <div class="mb-6 p-4 rounded-xl bg-green-50 border border-green-200 flex items-center gap-3" role="status">
                <svg class="w-5 h-5 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div>
                    <p class="text-sm font-medium text-green-800">Ihre Bewerbung wurde erfolgreich versendet!</p>
                    <p class="text-xs text-green-600 mt-0.5">Das Unternehmen wird sich zeitnah bei Ihnen melden.</p>
                </div>
            </div>
        @endif

        {{-- Error-Banner --}}
        @if(session('error'))
            <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 flex items-center gap-3" role="alert">
                <svg class="w-5 h-5 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p class="text-sm text-red-700">{{ session('error') }}</p>
            </div>
        @endif

        {{-- 2-Column Layout --}}
        <div class="flex flex-col lg:flex-row gap-8">

            {{-- Hauptinhalt (links) --}}
            <main class="flex-1 min-w-0">

                {{-- Job-Header --}}
                <header class="mb-8">
                    <div class="flex flex-col sm:flex-row sm:items-start gap-4 mb-4">
                        {{-- Firmenlogo --}}
                        @if($job->company)
                            <div class="w-16 h-16 rounded-xl bg-base-200 overflow-hidden shrink-0 ring-1 ring-base-200">
                                <div class="w-full h-full flex items-center justify-center text-base-content/20 text-2xl font-bold bg-gradient-to-br from-base-200 to-base-300">
                                    {{ mb_substr($job->company->name, 0, 1) }}
                                </div>
                            </div>
                        @endif

                        <div class="flex-1 min-w-0">
                            <h1 class="text-2xl sm:text-3xl font-bold text-base-content mb-2">{{ $job->title }}</h1>
                            @if($job->company)
                                <a href="{{ $job->company->portal_url }}" class="text-base text-portal-primary hover:underline font-medium">
                                    {{ $job->company->name }}
                                </a>
                            @endif
                        </div>
                    </div>

                    {{-- Meta-Badges --}}
                    <div class="job-card__meta">
                        <span class="job-badge job-badge--{{ $job->employment_type }}">
                            {{ $job->employment_type_label }}
                        </span>
                        @if($job->location_display)
                            <span class="job-card__meta-item">
                                <svg class="job-card__meta-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                {{ $job->location_display }}
                            </span>
                        @endif
                        @if($job->salary_display)
                            <span class="job-card__meta-item">
                                <svg class="job-card__meta-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                {{ $job->salary_display }}
                            </span>
                        @endif
                        <span class="job-card__meta-item">
                            <svg class="job-card__meta-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            Veröffentlicht {{ $job->published_at->diffForHumans() }}
                        </span>
                        @if($job->application_deadline && !$job->application_deadline->isPast())
                            <span class="job-card__meta-item" style="color: #B45309; font-weight: 600;">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Frist: {{ $job->application_deadline->format('d.m.Y') }}
                            </span>
                        @endif
                    </div>
                </header>

                {{-- Stellenbeschreibung --}}
                <section class="mb-8" aria-labelledby="job-description-title">
                    <h2 id="job-description-title" class="job-detail__section-title">Stellenbeschreibung</h2>
                    <div class="prose prose-sm max-w-none text-base-content/80">
                        {!! nl2br(e($job->description)) !!}
                    </div>
                </section>

                {{-- Anforderungen --}}
                @if($job->requirements)
                    <section class="mb-8" aria-labelledby="job-requirements-title">
                        <h2 id="job-requirements-title" class="job-detail__section-title">Anforderungen</h2>
                        <div class="prose prose-sm max-w-none text-base-content/80">
                            {!! nl2br(e($job->requirements)) !!}
                        </div>
                    </section>
                @endif

                {{-- Benefits --}}
                @if($job->benefits)
                    <section class="mb-8" aria-labelledby="job-benefits-title">
                        <h2 id="job-benefits-title" class="job-detail__section-title">Was wir bieten</h2>
                        <div class="prose prose-sm max-w-none text-base-content/80">
                            {!! nl2br(e($job->benefits)) !!}
                        </div>
                    </section>
                @endif

                {{-- Über das Unternehmen --}}
                @if($job->company)
                    <section class="mb-8 job-company-box" aria-labelledby="job-company-title">
                        <h2 id="job-company-title" class="text-lg font-bold text-base-content mb-4">Über {{ $job->company->name }}</h2>
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-lg bg-base-200 overflow-hidden shrink-0">
                                <div class="w-full h-full flex items-center justify-center text-base-content/20 text-lg font-bold">
                                    {{ mb_substr($job->company->name, 0, 1) }}
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                @if($job->company->description)
                                    <p class="text-sm text-base-content/70 line-clamp-3 mb-3">{{ $job->company->description }}</p>
                                @endif
                                <a href="{{ $job->company->portal_url }}"
                                   class="inline-flex items-center gap-1.5 text-sm font-medium text-portal-primary hover:underline">
                                    Firmenprofil ansehen
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </a>
                            </div>
                        </div>
                    </section>
                @endif

                {{-- Weitere Jobs dieser Firma --}}
                @if($relatedJobs->isNotEmpty())
                    <section class="mb-8" aria-labelledby="related-jobs-title">
                        <h2 id="related-jobs-title" class="text-lg font-bold text-base-content mb-4">Weitere Stellen bei {{ $job->company->name }}</h2>
                        <div class="space-y-3">
                            @foreach($relatedJobs as $relatedJob)
                                @include('components.job-card', ['job' => $relatedJob, 'layout' => 'compact'])
                            @endforeach
                        </div>
                    </section>
                @endif

                {{-- Ähnliche Jobs --}}
                @if($similarJobs->isNotEmpty())
                    <section aria-labelledby="similar-jobs-title">
                        <h2 id="similar-jobs-title" class="text-lg font-bold text-base-content mb-4">Ähnliche Stellen</h2>
                        <div class="space-y-3">
                            @foreach($similarJobs as $similarJob)
                                @include('components.job-card', ['job' => $similarJob, 'layout' => 'compact'])
                            @endforeach
                        </div>
                    </section>
                @endif
            </main>

            {{-- Sidebar (rechts) — Sticky --}}
            <aside class="lg:w-96 shrink-0" aria-label="Bewerbung und Kontakt">
                <div class="sticky top-24 space-y-6">

                    {{-- Bewerbungsformular --}}
                    @if(!$job->is_expired && !session('application_success'))
                        <div class="job-apply-card" id="bewerben">
                            <h2 class="job-apply-card__title">Jetzt bewerben</h2>
                            <p class="job-apply-card__subtitle">Ihre Bewerbung wird direkt an {{ $job->company->name ?? 'das Unternehmen' }} gesendet.</p>

                            <form action="{{ route('portal.jobs.apply', $job->slug) }}" method="POST" enctype="multipart/form-data" novalidate>
                                @csrf

                                {{-- Name --}}
                                <div class="mb-4">
                                    <label for="name" class="block text-sm font-medium text-base-content mb-1">Name <span class="text-red-500" aria-hidden="true">*</span></label>
                                    <input type="text" id="name" name="name" value="{{ old('name') }}" required
                                           class="w-full px-3 py-2.5 rounded-lg border border-base-200 text-sm text-base-content bg-white focus:outline-none focus:ring-2 focus:ring-portal-primary/30 focus:border-portal-primary transition-colors @error('name') border-red-300 @enderror"
                                           placeholder="Max Mustermann"
                                           autocomplete="name">
                                    @error('name')
                                        <p class="mt-1 text-xs text-red-500" role="alert">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- E-Mail --}}
                                <div class="mb-4">
                                    <label for="email" class="block text-sm font-medium text-base-content mb-1">E-Mail <span class="text-red-500" aria-hidden="true">*</span></label>
                                    <input type="email" id="email" name="email" value="{{ old('email') }}" required
                                           class="w-full px-3 py-2.5 rounded-lg border border-base-200 text-sm text-base-content bg-white focus:outline-none focus:ring-2 focus:ring-portal-primary/30 focus:border-portal-primary transition-colors @error('email') border-red-300 @enderror"
                                           placeholder="max@beispiel.de"
                                           autocomplete="email">
                                    @error('email')
                                        <p class="mt-1 text-xs text-red-500" role="alert">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- Telefon --}}
                                <div class="mb-4">
                                    <label for="phone" class="block text-sm font-medium text-base-content mb-1">Telefon <span class="text-base-content/40">(optional)</span></label>
                                    <input type="tel" id="phone" name="phone" value="{{ old('phone') }}"
                                           class="w-full px-3 py-2.5 rounded-lg border border-base-200 text-sm text-base-content bg-white focus:outline-none focus:ring-2 focus:ring-portal-primary/30 focus:border-portal-primary transition-colors"
                                           placeholder="030 1234567"
                                           autocomplete="tel">
                                </div>

                                {{-- Nachricht --}}
                                <div class="mb-4">
                                    <label for="message" class="block text-sm font-medium text-base-content mb-1">Nachricht <span class="text-red-500" aria-hidden="true">*</span></label>
                                    <textarea id="message" name="message" rows="4" required minlength="20"
                                              class="w-full px-3 py-2.5 rounded-lg border border-base-200 text-sm text-base-content bg-white focus:outline-none focus:ring-2 focus:ring-portal-primary/30 focus:border-portal-primary transition-colors resize-y @error('message') border-red-300 @enderror"
                                              placeholder="Warum interessieren Sie sich für diese Stelle?">{{ old('message') }}</textarea>
                                    @error('message')
                                        <p class="mt-1 text-xs text-red-500" role="alert">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- Lebenslauf --}}
                                <div class="mb-6">
                                    <label for="cv" class="block text-sm font-medium text-base-content mb-1">Lebenslauf <span class="text-base-content/40">(optional)</span></label>
                                    <input type="file" id="cv" name="cv" accept=".pdf,.doc,.docx"
                                           class="w-full text-sm text-base-content/60 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-base-200 file:text-base-content/70 hover:file:bg-base-300 transition-colors @error('cv') border border-red-300 rounded-lg @enderror">
                                    <p class="mt-1 text-[10px] text-base-content/40">PDF, DOC oder DOCX — max. 10 MB</p>
                                    @error('cv')
                                        <p class="mt-1 text-xs text-red-500" role="alert">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- Submit --}}
                                <button type="submit"
                                        class="w-full py-3 rounded-xl text-sm font-semibold text-white btn-portal transition-colors hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2 ring-portal">
                                    Bewerbung absenden
                                </button>

                                <p class="mt-3 text-[10px] text-base-content/40 text-center">
                                    Mit dem Absenden akzeptieren Sie unsere
                                    <a href="{{ route('portal.datenschutz') }}" class="underline hover:text-base-content/60">Datenschutzerklärung</a>.
                                </p>
                            </form>
                        </div>
                    @elseif(session('application_success'))
                        {{-- Success State --}}
                        <div class="rounded-xl border border-green-200 bg-green-50 p-6 text-center" role="status">
                            <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-3">
                                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <h3 class="text-base font-bold text-green-800 mb-1">Bewerbung gesendet!</h3>
                            <p class="text-sm text-green-600">{{ $job->company->name ?? 'Das Unternehmen' }} wird sich bei Ihnen melden.</p>
                        </div>
                    @endif

                    {{-- Kontaktdaten der Firma --}}
                    @if($job->company)
                        <div class="rounded-xl border border-base-200 bg-white p-6">
                            <h3 class="text-sm font-semibold text-base-content mb-3">Kontakt</h3>
                            <div class="space-y-2.5 text-sm">
                                @if($job->company->full_address)
                                    <div class="flex items-start gap-2">
                                        <svg class="w-4 h-4 shrink-0 text-base-content/40 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                        <span class="text-base-content/70">{{ $job->company->full_address }}</span>
                                    </div>
                                @endif
                                @if($job->company->tel)
                                    <div class="flex items-center gap-2">
                                        <svg class="w-4 h-4 shrink-0 text-base-content/40" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                        <a href="tel:{{ $job->company->tel }}" class="text-portal-primary hover:underline">{{ $job->company->tel }}</a>
                                    </div>
                                @endif
                                @if($job->company->email)
                                    <div class="flex items-center gap-2">
                                        <svg class="w-4 h-4 shrink-0 text-base-content/40" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                        <a href="mailto:{{ $job->company->email }}" class="text-portal-primary hover:underline truncate">{{ $job->company->email }}</a>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    {{-- Teilen --}}
                    <div class="rounded-xl border border-base-200 bg-white p-6">
                        <h3 class="text-sm font-semibold text-base-content mb-3">Stelle teilen</h3>
                        <div class="flex items-center gap-2">
                            <a href="mailto:?subject={{ urlencode($job->title . ' bei ' . ($job->company->name ?? '')) }}&body={{ urlencode('Schau dir diese Stellenanzeige an: ' . url()->current()) }}"
                               class="job-share-btn" title="Per E-Mail teilen" aria-label="Stelle per E-Mail teilen">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            </a>
                            <button onclick="navigator.clipboard.writeText(window.location.href).then(() => { this.querySelector('span').textContent = 'Kopiert!'; setTimeout(() => this.querySelector('span').textContent = 'Link kopieren', 2000); })"
                                    class="job-share-btn" aria-label="Link in die Zwischenablage kopieren">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
                                <span>Link kopieren</span>
                            </button>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    {{-- Mobile: Sticky Bottom CTA --}}
    @if(!$job->is_expired && !session('application_success'))
        <div class="job-mobile-cta lg:hidden">
            <a href="#bewerben"
               class="flex items-center justify-center gap-2 w-full py-3 rounded-xl text-sm font-semibold text-white btn-portal transition-colors hover:opacity-90">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                Jetzt bewerben
            </a>
        </div>
    @endif

@endsection
