@extends('layouts.app')

@section('title', 'Verifizierung — ' . $company->name . ' — ' . config('app.name'))
@section('meta_robots', 'noindex, nofollow')

@section('content')

    {{-- SEKTION 1: HERO (Dunkel — Primary-Gradient) --}}
    <section class="claim-v5-hero" role="banner" aria-label="Verifizierung">
        <div class="claim-v5-hero__inner">
            @if($company->logo_url)
                <img src="{{ $company->logo_url }}" alt="{{ $company->name }}" class="claim-v5-hero__logo" width="80" height="80" loading="eager">
            @else
                <div class="claim-v5-hero__logo claim-v5-hero__logo--fallback" aria-label="{{ $company->name }} Logo">
                    {{ strtoupper(mb_substr($company->name, 0, 2)) }}
                </div>
            @endif

            <h1 class="claim-v5-hero__headline">
                Fast geschafft,
                <span class="claim-v5-hero__company-name">{{ $company->name }}</span>!
            </h1>

            <p class="claim-v5-hero__subline">
                Noch ein kurzer Schritt — dann gehört Ihr Firmenprofil Ihnen.
            </p>

            <div class="claim-verify-v4-progress" role="progressbar" aria-valuenow="75" aria-valuemin="0" aria-valuemax="100" aria-label="Fortschritt: 75%">
                <div class="claim-verify-v4-progress__track">
                    <div class="claim-verify-v4-progress__fill" style="width: 75%"></div>
                </div>
                <div class="claim-verify-v4-progress__labels">
                    <span class="claim-verify-v4-progress__label claim-verify-v4-progress__label--done">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        Registriert
                    </span>
                    <span class="claim-verify-v4-progress__label claim-verify-v4-progress__label--active">Verifizierung</span>
                    <span class="claim-verify-v4-progress__label">Fertig!</span>
                </div>
            </div>

            <div class="claim-v5-hero__urgency">
                <span><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Dauert 2 Minuten</span>
                <span><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Prüfung in 48h</span>
                <span><svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Auto-Löschung nach 90 Tagen</span>
            </div>
        </div>
    </section>

    {{-- SEKTION 2: UPLOAD (Weiß) --}}
    <section class="claim-verify-v4-upload-section" aria-label="Dokumente hochladen">
        <div class="claim-verify-v4-upload-section__inner">
            <h2 class="claim-verify-v4-upload-section__heading">
                Bestätigen Sie, dass <strong>{{ $company->name }}</strong> Ihnen gehört
            </h2>
            <p class="claim-verify-v4-upload-section__subline">
                Laden Sie ein Dokument hoch, das Ihren Firmennamen zeigt — z.B. Gewerbeanmeldung oder Handelsregisterauszug.
            </p>
            @livewire('portal.claim-verification', ['company' => $company])
        </div>
    </section>

    {{-- SEKTION 3: PREVIEW (Hell — Primary-Tint) --}}
    <section class="claim-v5-preview" aria-label="Vorschau nach Verifizierung">
        <div class="claim-v5-preview__inner">
            <h2 class="claim-v5-preview__heading">So sieht Ihr Eintrag nach der Verifizierung aus:</h2>
            <div class="claim-v5-preview-card">
                <div class="claim-v5-preview-card__header">
                    @if($company->logo_url)
                        <img src="{{ $company->logo_url }}" alt="{{ $company->name }}" class="claim-v5-preview-card__logo" width="64" height="64" loading="lazy">
                    @else
                        <div class="claim-v5-preview-card__logo claim-v5-preview-card__logo--fallback" aria-label="{{ $company->name }} Logo">{{ strtoupper(mb_substr($company->name, 0, 2)) }}</div>
                    @endif
                    <div>
                        <h3 class="claim-v5-preview-card__name">{{ $company->name }}</h3>
                        @if($company->full_address)
                            <p class="claim-v5-preview-card__address">{{ $company->full_address }}</p>
                        @endif
                        @if($company->rating_count > 0)
                            <div class="claim-v5-preview-card__rating">
                                <div class="claim-v5-preview-card__stars" aria-label="{{ number_format($company->rating, 1) }} von 5 Sternen">
                                    @for($i = 1; $i <= 5; $i++)
                                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20" class="{{ $i <= round($company->rating) ? 'star-filled' : 'star-empty' }}" aria-hidden="true"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                    @endfor
                                </div>
                                <span class="claim-v5-preview-card__rating-text">{{ number_format($company->rating, 1) }} ({{ $company->rating_count }} {{ $company->rating_count === 1 ? 'Bewertung' : 'Bewertungen' }})</span>
                            </div>
                        @endif
                    </div>
                </div>
                <div class="claim-verify-v4-verified-preview" role="status">
                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                    <div>
                        <strong>Verifizierter Inhaber</strong>
                        <p>Dieses Profil wird als verifiziert angezeigt — Kunden vertrauen Ihnen mehr.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- SEKTION 4: BENEFITS (Dunkel — Slate-900) --}}
    <section class="claim-v5-benefits" aria-label="Vorteile nach Verifizierung">
        <div class="claim-v5-benefits__inner">
            <h2 class="claim-v5-benefits__heading">Was Sie nach der Verifizierung erhalten:</h2>
            <div class="claim-v5-benefits__grid">
                <div class="claim-v5-benefit-card">
                    <div class="claim-v5-benefit-card__icon"><svg width="28" height="28" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg></div>
                    <h3 class="claim-v5-benefit-card__title">Profil bearbeiten</h3>
                    <p class="claim-v5-benefit-card__text">Logo, Beschreibung, Öffnungszeiten und Kontaktdaten — alles in Ihrer Hand.</p>
                </div>
                <div class="claim-v5-benefit-card">
                    <div class="claim-v5-benefit-card__icon"><svg width="28" height="28" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/></svg></div>
                    <h3 class="claim-v5-benefit-card__title">Auf Bewertungen antworten</h3>
                    <p class="claim-v5-benefit-card__text">Reagieren Sie auf Kundenfeedback und zeigen Sie, dass Ihnen Ihre Kunden wichtig sind.</p>
                </div>
                <div class="claim-v5-benefit-card">
                    <div class="claim-v5-benefit-card__icon" style="background: var(--portal-accent, #F59E0B);"><svg width="28" height="28" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/></svg></div>
                    <h3 class="claim-v5-benefit-card__title">30 Tage Premium gratis</h3>
                    <p class="claim-v5-benefit-card__text">Hervorgehobener Eintrag, Statistiken, Bildergalerie und mehr — kostenlos testen.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- SEKTION 5: TRUST + FINAL-CTA --}}
    <section class="claim-verify-v4-trust-section" aria-label="Sicherheit und Datenschutz">
        <div class="claim-verify-v4-trust-section__inner">
            <div class="claim-verify-v4-trust-grid">
                <div class="claim-verify-v4-trust-item">
                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    <div><strong>SSL-verschlüsselt</strong><span>256-bit Verschlüsselung</span></div>
                </div>
                <div class="claim-verify-v4-trust-item">
                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                    <div><strong>DSGVO-konform</strong><span>Ihre Daten sind geschützt</span></div>
                </div>
                <div class="claim-verify-v4-trust-item">
                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div><strong>Prüfung in 48h</strong><span>Schnelle Bearbeitung</span></div>
                </div>
                <div class="claim-verify-v4-trust-item">
                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                    <div><strong>Auto-Löschung</strong><span>Dokumente nach 90 Tagen gelöscht</span></div>
                </div>
            </div>
        </div>
    </section>

@endsection
