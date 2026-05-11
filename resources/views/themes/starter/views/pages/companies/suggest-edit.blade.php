@extends('layouts.app')

@section('title', $company->name . ' übernehmen — ' . config('app.name'))

{{-- V5: noindex — Conversion-Page, kein SEO --}}
@section('meta_robots', 'noindex, nofollow')

@section('content')

    {{-- ============================================
         SEKTION 1: HERO — "Dein Name, dein Geschäft"
         Dunkel (Primary-Dark → Secondary Gradient)
         ============================================ --}}
    <section class="claim-v5-hero" role="banner" aria-label="Eintrag übernehmen">
        <div class="claim-v5-hero__inner">
            {{-- Firmen-Logo --}}
            <div class="claim-v5-hero__logo claim-v5-hero__logo--fallback"
                 aria-label="{{ $company->name }} Logo">
                {{ strtoupper(mb_substr($company->name, 0, 2)) }}
            </div>
            @endif

            {{-- Headline: Firmenname in Accent-Gold --}}
            <h1 class="claim-v5-hero__headline">
                <span class="claim-v5-hero__company-name">{{ $company->name }}</span>
                gehört Ihnen?
            </h1>

            <p class="claim-v5-hero__subline">
                Übernehmen Sie Ihren Eintrag — kostenlos und in 2 Minuten fertig.
            </p>

            {{-- CTA #1 → Claim-Modal öffnen --}}
            <button type="button"
                    class="claim-v5-cta"
                    onclick="Livewire.dispatch('openClaimModal')"
                    aria-haspopup="dialog"
                    aria-label="Jetzt {{ $company->name }} kostenlos übernehmen">
                Jetzt kostenlos übernehmen
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                </svg>
            </button>

            {{-- Urgency --}}
            <div class="claim-v5-hero__urgency">
                <span>
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Kostenlos
                </span>
                <span>
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Keine Kreditkarte
                </span>
                <span>
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    2 Min
                </span>
            </div>

            {{-- Social Proof --}}
            <div class="claim-v5-hero__proof">
                <div class="claim-v5-hero__avatars">
                    <span class="claim-v5-hero__avatar" aria-hidden="true"></span>
                    <span class="claim-v5-hero__avatar" aria-hidden="true"></span>
                    <span class="claim-v5-hero__avatar" aria-hidden="true"></span>
                </div>
                <span class="claim-v5-hero__proof-text">
                    <strong>2.400+</strong> Inhaber verwalten bereits ihren Eintrag
                </span>
            </div>

            {{-- Login → Modal mit Login-Tab --}}
            <button type="button"
                    class="claim-v5-hero__login"
                    onclick="Livewire.dispatch('openClaimModalLogin')"
                    aria-haspopup="dialog">
                Bereits registriert? <span>Einloggen</span>
            </button>
        </div>
    </section>

    {{-- ============================================
         SEKTION 2: BENEFITS — "Was Sie bekommen"
         Weiß mit farbigen Akzent-Cards
         ============================================ --}}
    <section class="claim-v5-benefits" aria-label="Vorteile">
        <div class="claim-v5-benefits__inner">
            <h2 class="claim-v5-benefits__heading">Was Sie als Inhaber bekommen:</h2>

            <div class="claim-v5-benefits__grid">
                {{-- Benefit 1: Mehr Kunden --}}
                <div class="claim-v5-benefit-card">
                    <div class="claim-v5-benefit-card__icon">
                        <svg width="28" height="28" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                        </svg>
                    </div>
                    <h3 class="claim-v5-benefit-card__title">Mehr Kunden finden Sie</h3>
                    <p class="claim-v5-benefit-card__text">
                        Ihr Profil erscheint in der Suche und bei Google — mit vollständigen Kontaktdaten und Öffnungszeiten.
                    </p>
                </div>

                {{-- Benefit 2: Besserer Ruf --}}
                <div class="claim-v5-benefit-card">
                    <div class="claim-v5-benefit-card__icon">
                        <svg width="28" height="28" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/>
                        </svg>
                    </div>
                    <h3 class="claim-v5-benefit-card__title">Besserer Ruf im Netz</h3>
                    <p class="claim-v5-benefit-card__text">
                        Antworten Sie auf Bewertungen, zeigen Sie Ihre beste Seite und bauen Sie Vertrauen bei neuen Kunden auf.
                    </p>
                </div>

                {{-- Benefit 3: Volle Kontrolle --}}
                <div class="claim-v5-benefit-card">
                    <div class="claim-v5-benefit-card__icon">
                        <svg width="28" height="28" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                        </svg>
                    </div>
                    <h3 class="claim-v5-benefit-card__title">Volle Kontrolle</h3>
                    <p class="claim-v5-benefit-card__text">
                        Fotos, Beschreibung, Öffnungszeiten, Kontaktdaten — alles in Ihrer Hand. Änderungen sind sofort live.
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- ============================================
         SEKTION 3: PREVIEW — "So sieht Ihr Eintrag aus"
         Heller Hintergrund (Primary-Light)
         ============================================ --}}
    <section class="claim-v5-preview" aria-label="Vorschau Ihres Firmenprofils">
        <div class="claim-v5-preview__inner">
            <h2 class="claim-v5-preview__heading">So sieht Ihr Eintrag für Ihre Kunden aus:</h2>

            <div class="claim-v5-preview-card">
                {{-- Firmendaten-Header --}}
                <div class="claim-v5-preview-card__header">
                    <div class="claim-v5-preview-card__logo claim-v5-preview-card__logo--fallback"
                         aria-label="{{ $company->name }} Logo">
                        {{ strtoupper(mb_substr($company->name, 0, 2)) }}
                    </div>
                    <div>
                        <h3 class="claim-v5-preview-card__name">{{ $company->name }}</h3>
                        @if($company->full_address)
                            <p class="claim-v5-preview-card__address">{{ $company->full_address }}</p>
                        @endif
                        @if($company->reviews_count > 0)
                            <div class="claim-v5-preview-card__rating">
                                <div class="claim-v5-preview-card__stars" aria-label="{{ number_format($company->average_rating, 1) }} von 5 Sternen">
                                    @for($i = 1; $i <= 5; $i++)
                                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20"
                                             class="{{ $i <= round($company->average_rating) ? 'star-filled' : 'star-empty' }}"
                                             aria-hidden="true">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                        </svg>
                                    @endfor
                                </div>
                                <span class="claim-v5-preview-card__rating-text">
                                    {{ number_format($company->average_rating, 1) }}
                                    ({{ $company->reviews_count }} {{ $company->reviews_count === 1 ? 'Bewertung' : 'Bewertungen' }})
                                </span>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Warning Badge --}}
                @if(!$company->user_id)
                    <div class="claim-v5-preview-warning" role="alert">
                        <svg class="claim-v5-preview-warning__icon" width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                        </svg>
                        <div>
                            <strong class="claim-v5-preview-warning__title">Nicht verifiziert</strong>
                            <p class="claim-v5-preview-warning__text">Dieser Eintrag wurde noch nicht vom Inhaber übernommen. Kunden sehen das.</p>
                        </div>
                    </div>
                @endif

                {{-- Fehlende Daten (Gap-Grid) --}}
                @php
                    $gaps = [];
                    if (!$company->hasMedia('gallery')) $gaps[] = 'Keine Fotos hochgeladen';
                    if (!$company->relationLoaded('openingHours') || $company->openingHours->isEmpty()) $gaps[] = 'Keine Öffnungszeiten hinterlegt';
                    if (strlen($company->description ?? '') < 20) $gaps[] = 'Keine Beschreibung vorhanden';
                    if (($company->reviews_count ?? 0) > 0 && !$company->user_id) $gaps[] = 'Keine Antworten auf Bewertungen';
                @endphp

                @if(count($gaps) > 0)
                    <div class="claim-v5-preview-gaps">
                        @foreach($gaps as $gap)
                            <div class="claim-v5-preview-gap">
                                <svg class="claim-v5-preview-gap__x" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                                {{ $gap }}
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- CTA #2 → Claim-Modal öffnen --}}
                <button type="button"
                        class="claim-v5-preview-cta"
                        onclick="Livewire.dispatch('openClaimModal')"
                        aria-haspopup="dialog"
                        aria-label="Jetzt {{ $company->name }} übernehmen und verbessern">
                    Jetzt übernehmen und verbessern
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                    </svg>
                </button>
            </div>
        </div>
    </section>

    {{-- ============================================
         SEKTION 4: SO FUNKTIONIERT'S — "3 Schritte"
         Weiß
         ============================================ --}}
    <section class="claim-v5-steps" aria-label="So funktioniert's">
        <div class="claim-v5-steps__inner">
            <h2 class="claim-v5-steps__heading">In 3 Schritten zum eigenen Profil:</h2>

            <div class="claim-v5-steps__grid">
                <div class="claim-v5-step">
                    <div class="claim-v5-step__number">1</div>
                    <h3 class="claim-v5-step__title">Registrieren</h3>
                    <p class="claim-v5-step__text">Name und E-Mail — das war's schon. Keine Kreditkarte, keine Verpflichtung.</p>
                </div>
                <div class="claim-v5-step">
                    <div class="claim-v5-step__number">2</div>
                    <h3 class="claim-v5-step__title">Profil vervollständigen</h3>
                    <p class="claim-v5-step__text">Logo, Beschreibung und Öffnungszeiten ergänzen. Dauert 5 Minuten.</p>
                </div>
                <div class="claim-v5-step">
                    <div class="claim-v5-step__number">3</div>
                    <h3 class="claim-v5-step__title">Kunden gewinnen!</h3>
                    <p class="claim-v5-step__text">Ihr Profil ist live und wird in der Suche und bei Google gefunden.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- ============================================
         SEKTION 5: SOCIAL PROOF — Testimonials
         Dunkel (Slate-900)
         ============================================ --}}
    <section class="claim-v5-proof" aria-label="Das sagen Firmeninhaber">
        <div class="claim-v5-proof__inner">
            <h2 class="claim-v5-proof__heading">Das sagen Firmeninhaber:</h2>

            <div class="claim-v5-testimonials">
                <div class="claim-v5-testimonial">
                    <blockquote class="claim-v5-testimonial__quote">
                        &ldquo;Endlich kann ich auf Bewertungen antworten — das war überfällig. Meine Kunden sehen, dass ich mich kümmere.&rdquo;
                    </blockquote>
                    <div class="claim-v5-testimonial__author">Maria K.</div>
                    <div class="claim-v5-testimonial__role">Friseursalon, Köln</div>
                </div>
                <div class="claim-v5-testimonial">
                    <blockquote class="claim-v5-testimonial__quote">
                        &ldquo;In 5 Minuten war mein Profil fertig. Einfacher geht's nicht. Und die Statistiken zeigen mir, dass es sich lohnt.&rdquo;
                    </blockquote>
                    <div class="claim-v5-testimonial__author">Stefan M.</div>
                    <div class="claim-v5-testimonial__role">Elektriker, Hamburg</div>
                </div>
            </div>

            {{-- Stats --}}
            <div class="claim-v5-stats">
                <div class="claim-v5-stat">
                    <div class="claim-v5-stat__number">2.400+</div>
                    <div class="claim-v5-stat__label">Profile aktiv</div>
                </div>
                <div class="claim-v5-stat">
                    <div class="claim-v5-stat__number">4.8/5</div>
                    <div class="claim-v5-stat__label">Durchschnitt</div>
                </div>
                <div class="claim-v5-stat">
                    <div class="claim-v5-stat__number">12</div>
                    <div class="claim-v5-stat__label">Portale online</div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============================================
         SEKTION 6: FINAL CTA — "Letzte Chance"
         Primary Gradient (wie Hero)
         ============================================ --}}
    <section class="claim-v5-final-cta" aria-label="Jetzt übernehmen">
        <div class="claim-v5-final-cta__inner">
            <h2 class="claim-v5-final-cta__headline">
                <strong>{{ $company->name }}</strong> wartet auf Sie.
            </h2>
            <p class="claim-v5-final-cta__subline">
                Übernehmen Sie jetzt Ihren Eintrag — kostenlos.
            </p>
            <button type="button"
                    class="claim-v5-cta"
                    onclick="Livewire.dispatch('openClaimModal')"
                    aria-haspopup="dialog"
                    aria-label="Jetzt {{ $company->name }} kostenlos übernehmen">
                Jetzt kostenlos übernehmen
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                </svg>
            </button>
            <div class="claim-v5-hero__urgency" style="margin-top: 1.25rem;">
                <span>
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Kostenlos
                </span>
                <span>
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Keine Kreditkarte
                </span>
                <span>
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    2 Min
                </span>
            </div>
        </div>
    </section>

    {{-- ============================================
         SEKTION 7: EDIT-HINWEIS — "Nicht der Inhaber?"
         Minimal, Modal-Link
         ============================================ --}}
    <section class="claim-v5-edit-hint" aria-label="Änderung vorschlagen">
        <div class="claim-v5-edit-hint__inner">
            <h3 class="claim-v5-edit-hint__title">Sie sind nicht der Inhaber?</h3>
            <p class="claim-v5-edit-hint__text">
                Wenn Sie fehlerhafte Daten gefunden haben, können Sie eine
                <button type="button"
                        class="claim-v5-edit-hint__link"
                        onclick="Livewire.dispatch('openSuggestEditModal')"
                        aria-haspopup="dialog">Änderung vorschlagen</button>.
            </p>

            {{-- Trust-Zeile --}}
            <div class="claim-v5-trust">
                <span>
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    SSL-verschlüsselt
                </span>
                <span>
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                    DSGVO-konform
                </span>
                <span>
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    Wird manuell geprüft
                </span>
            </div>
        </div>
    </section>

    {{-- Claim-Modal (Livewire) — 4 Szenarien: Guest, Logged-In, Multi-Firma, Already-Claimed --}}
    @livewire('portal.claim-modal', ['company' => $company])

    {{-- Suggest-Edit Modal (Livewire) --}}
    @livewire('portal.suggest-edit-modal', ['company' => $company, 'hideTrigger' => true])

@endsection
