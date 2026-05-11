@extends('layouts.app')

@section('title', $company->name . ' übernehmen — ' . config('app.name'))

{{-- PROF-1 Iteration 4: noindex — rein funktional, kein SEO --}}
@section('meta_robots', 'noindex, nofollow')

@section('content')

    {{-- ============================
         1. CLAIM-HERO (Above the Fold)
         ============================ --}}
    <section class="claim-hero" role="banner">
        <div class="claim-hero__content">
            {{-- Firmen-Logo --}}
            <div class="claim-hero__logo claim-hero__logo--fallback"
                 aria-label="{{ $company->name }} Logo">
                {{ strtoupper(mb_substr($company->name, 0, 2)) }}
            </div>

            {{-- Headline --}}
            <h1 class="claim-hero__headline">{{ $company->name }} gehört Ihnen?</h1>
            <p class="claim-hero__subline">Übernehmen Sie Ihren Eintrag und verwalten Sie Ihr Unternehmensprofil — kostenlos.</p>

            {{-- Social Proof --}}
            <div class="claim-hero__social-proof">
                <div class="claim-hero__social-avatars">
                    <span class="claim-hero__avatar" aria-hidden="true"></span>
                    <span class="claim-hero__avatar" aria-hidden="true"></span>
                    <span class="claim-hero__avatar" aria-hidden="true"></span>
                </div>
                <span>Bereits <strong>2.400+</strong> Unternehmen verwalten ihren Eintrag</span>
            </div>

            {{-- Primary CTA --}}
            <a href="{{ route('register') }}?claim={{ $company->slug }}"
               class="claim-hero__cta"
               aria-label="Jetzt {{ $company->name }} kostenlos übernehmen">
                Jetzt kostenlos übernehmen
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                </svg>
            </a>

            {{-- Dringlichkeits-Hinweis --}}
            <p class="claim-hero__urgency">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Kostenlos
                <span class="claim-hero__urgency-dot" aria-hidden="true">&middot;</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Keine Kreditkarte
                <span class="claim-hero__urgency-dot" aria-hidden="true">&middot;</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                In 2 Minuten fertig
            </p>

            {{-- Login-Link --}}
            <a href="{{ route('login') }}" class="claim-hero__login">
                Bereits registriert? <span class="underline">Einloggen</span>
            </a>
        </div>
    </section>

    {{-- ============================
         2. BENEFIT-CARDS
         ============================ --}}
    <section class="claim-benefits" aria-label="Vorteile der Übernahme">
        <div class="claim-benefits__grid">
            <div class="claim-benefits__card">
                <div class="claim-benefits__icon-circle">
                    <svg class="claim-benefits__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/>
                    </svg>
                </div>
                <h3 class="claim-benefits__title">Daten pflegen</h3>
                <p class="claim-benefits__desc">Name, Adresse und Öffnungszeiten selbst aktualisieren</p>
            </div>
            <div class="claim-benefits__card">
                <div class="claim-benefits__icon-circle">
                    <svg class="claim-benefits__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 01-.825-.242m9.345-8.334a2.126 2.126 0 00-.476-.095 48.64 48.64 0 00-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0011.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155"/>
                    </svg>
                </div>
                <h3 class="claim-benefits__title">Bewertungen antworten</h3>
                <p class="claim-benefits__desc">Auf Kundenfeedback reagieren und Vertrauen aufbauen</p>
            </div>
            <div class="claim-benefits__card">
                <div class="claim-benefits__icon-circle">
                    <svg class="claim-benefits__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/>
                    </svg>
                </div>
                <h3 class="claim-benefits__title">Fotos hochladen</h3>
                <p class="claim-benefits__desc">Logo, Galerie und Titelbild für mehr Sichtbarkeit</p>
            </div>
            <div class="claim-benefits__card">
                <div class="claim-benefits__icon-circle">
                    <svg class="claim-benefits__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/>
                    </svg>
                </div>
                <h3 class="claim-benefits__title">Statistiken einsehen</h3>
                <p class="claim-benefits__desc">Profilaufrufe, Kontaktklicks und Trends verfolgen</p>
            </div>
        </div>
    </section>

    {{-- ============================
         3. PREVIEW-CARD (Firmenprofil)
         ============================ --}}
    <section class="claim-preview-section" aria-label="Vorschau Ihres Firmenprofils">
        <h2 class="claim-preview-section__heading">So sieht Ihr Eintrag gerade aus:</h2>
        <div class="claim-preview-card">
            {{-- Firmendaten --}}
            <div class="claim-preview-card__header">
                <div class="claim-preview-card__logo claim-preview-card__logo--fallback"
                     aria-label="{{ $company->name }} Logo">
                    {{ strtoupper(mb_substr($company->name, 0, 2)) }}
                </div>
                <div class="claim-preview-card__info">
                    <h3 class="claim-preview-card__name">{{ $company->name }}</h3>
                    @if($company->full_address)
                        <p class="claim-preview-card__address">{{ $company->full_address }}</p>
                    @endif
                    @if($company->reviews_count > 0)
                        <div class="claim-preview-card__rating">
                            {{-- Sterne --}}
                            <div class="claim-preview-card__stars" aria-label="{{ number_format($company->average_rating, 1) }} von 5 Sternen">
                                @for($i = 1; $i <= 5; $i++)
                                    <svg class="w-4 h-4 {{ $i <= round($company->average_rating) ? 'text-amber-400' : 'text-gray-200' }}"
                                         fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                    </svg>
                                @endfor
                            </div>
                            <span class="claim-preview-card__rating-text">
                                {{ number_format($company->average_rating, 1) }}
                                ({{ $company->reviews_count }} {{ $company->reviews_count === 1 ? 'Bewertung' : 'Bewertungen' }})
                            </span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Warning Badge --}}
            @if(!$company->user_id)
                <div class="claim-preview-warning" aria-label="Hinweis: Eintrag nicht verifiziert">
                    <svg class="claim-preview-warning__icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                    </svg>
                    <div>
                        <strong class="claim-preview-warning__title">Nicht verifiziert</strong>
                        <p class="claim-preview-warning__text">Dieser Eintrag wurde noch nicht vom Inhaber übernommen. Daten könnten veraltet sein.</p>
                    </div>
                </div>
            @endif

            {{-- Zweiter CTA --}}
            <a href="{{ route('register') }}?claim={{ $company->slug }}"
               class="claim-preview-cta"
               aria-label="Jetzt {{ $company->name }} übernehmen und Daten aktualisieren">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                Jetzt übernehmen und Daten aktualisieren
            </a>
        </div>
    </section>

    {{-- ============================
         4. VISUELLER TRENNER
         ============================ --}}
    <div class="claim-divider" role="separator">
        <span>Sie sind nicht der Inhaber? Schlagen Sie eine Änderung vor.</span>
    </div>

    {{-- ============================
         5. EDIT-FORMULAR (sekundär)
         ============================ --}}
    <section class="claim-edit-section" aria-label="Änderung vorschlagen">
        <div class="claim-edit-card">
            <h3 class="claim-edit-card__title">Was stimmt nicht?</h3>
            <p class="claim-edit-card__desc">Beschreiben Sie die Änderung so genau wie möglich. Unser Team prüft jeden Vorschlag.</p>

            @livewire('portal.suggest-edit-form', ['company' => $company])
        </div>

        {{-- Trust-Hinweise --}}
        <div class="claim-trust-hints">
            <span class="claim-trust-hints__item">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
                SSL-verschlüsselt
            </span>
            <span class="claim-trust-hints__item">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                DSGVO-konform
            </span>
            <span class="claim-trust-hints__item">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                Vorschlag wird manuell geprüft
            </span>
        </div>
    </section>

@endsection
