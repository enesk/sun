{{-- LEGAL-3: DSGVO Cookie-Consent — 3-Stufen (Banner, Modal, Footer-Link) --}}
{{-- Stack: Alpine.js + localStorage — kein Backend noetig --}}
<div x-data="cookieConsent" x-cloak>

    {{-- ═══ STUFE 1: Bottom-Banner (Erstbesuch) ═══ --}}
    <div
        x-show="showBanner"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="translate-y-full opacity-0"
        x-transition:enter-end="translate-y-0 opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="translate-y-0 opacity-100"
        x-transition:leave-end="translate-y-full opacity-0"
        class="cookie-banner"
        role="dialog"
        aria-label="Cookie-Einstellungen"
        aria-modal="false"
    >
        <div class="container mx-auto px-4">
            <div class="cookie-banner__inner">
                {{-- Icon + Text --}}
                <div class="cookie-banner__content">
                    <div class="cookie-banner__icon" aria-hidden="true">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="cookie-banner__text">
                            Wir verwenden Cookies, um Ihnen die bestmoegliche Nutzung unserer Website zu ermoeglichen.
                            <a href="{{ route('portal.datenschutz') }}" class="cookie-banner__link">Datenschutzerklaerung</a>
                        </p>
                    </div>
                </div>

                {{-- Buttons — DSGVO: Alle gleich gross, kein Dark Pattern --}}
                <div class="cookie-banner__actions">
                    <button
                        @click="openSettings()"
                        class="cookie-btn cookie-btn--settings"
                        type="button"
                    >
                        Einstellungen
                    </button>
                    <button
                        @click="acceptEssentialOnly()"
                        class="cookie-btn cookie-btn--reject"
                        type="button"
                    >
                        Nur Notwendige
                    </button>
                    <button
                        @click="acceptAll()"
                        class="cookie-btn cookie-btn--accept"
                        type="button"
                    >
                        Alle akzeptieren
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══ STUFE 2: Detail-Modal (granulare Auswahl) ═══ --}}
    <div
        x-show="showModal"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="cookie-modal-backdrop"
        @click.self="showModal = false"
        @keydown.escape.window="showModal = false"
        role="dialog"
        aria-modal="true"
        aria-label="Cookie-Einstellungen verwalten"
    >
        <div
            x-show="showModal"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-8 sm:translate-y-4 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-8 sm:translate-y-4 sm:scale-95"
            class="cookie-modal"
            @click.stop
        >
            {{-- Header --}}
            <div class="cookie-modal__header">
                <div class="flex items-center gap-3">
                    <div class="cookie-modal__icon" aria-hidden="true">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                    <h2 class="cookie-modal__title">Cookie-Einstellungen</h2>
                </div>
                <button
                    @click="showModal = false"
                    class="cookie-modal__close"
                    type="button"
                    aria-label="Schliessen"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Beschreibung --}}
            <div class="cookie-modal__body">
                <p class="cookie-modal__description">
                    Wir nutzen Cookies und aehnliche Technologien. Notwendige Cookies sind fuer den Betrieb der Website erforderlich.
                    Statistik- und Marketing-Cookies helfen uns, die Website zu verbessern und relevante Inhalte anzuzeigen.
                    Sie koennen Ihre Einstellungen jederzeit aendern.
                    <a href="{{ route('portal.datenschutz') }}" class="cookie-banner__link">Mehr erfahren</a>
                </p>

                {{-- Kategorie-Liste --}}
                <div class="cookie-categories">

                    {{-- 1. Notwendig (immer an, nicht deaktivierbar) --}}
                    <div class="cookie-category">
                        <div class="cookie-category__header">
                            <div class="cookie-category__info">
                                <h3 class="cookie-category__title">Notwendig</h3>
                                <p class="cookie-category__description">
                                    Technisch erforderliche Cookies fuer Login, Warenkorb und Sicherheit. Ohne diese funktioniert die Website nicht.
                                </p>
                            </div>
                            <div class="cookie-toggle cookie-toggle--locked" aria-label="Immer aktiv">
                                <div class="cookie-toggle__track cookie-toggle__track--active">
                                    <div class="cookie-toggle__thumb cookie-toggle__thumb--active"></div>
                                </div>
                                <span class="cookie-toggle__label">Immer aktiv</span>
                            </div>
                        </div>
                        <details class="cookie-category__details">
                            <summary class="cookie-category__summary">Details anzeigen</summary>
                            <div class="cookie-category__detail-content">
                                <ul class="cookie-detail-list">
                                    <li><strong>Session-Cookie</strong> — Haelt Ihre Sitzung aktiv (Ablauf: Sitzungsende)</li>
                                    <li><strong>CSRF-Token</strong> — Schuetzt vor Cross-Site-Angriffen (Ablauf: Sitzungsende)</li>
                                    <li><strong>Cookie-Einstellungen</strong> — Speichert Ihre Cookie-Praeferenzen (Ablauf: 12 Monate)</li>
                                </ul>
                            </div>
                        </details>
                    </div>

                    {{-- 2. Statistik (default AUS — DSGVO: kein Pre-Ticking) --}}
                    <div class="cookie-category">
                        <div class="cookie-category__header">
                            <div class="cookie-category__info">
                                <h3 class="cookie-category__title">Statistik</h3>
                                <p class="cookie-category__description">
                                    Hilft uns zu verstehen, wie Besucher die Website nutzen. Die Daten werden anonymisiert erhoben (Google Analytics).
                                </p>
                            </div>
                            <label class="cookie-toggle" for="cookie-statistics">
                                <button
                                    type="button"
                                    role="switch"
                                    :aria-checked="statistics.toString()"
                                    @click="statistics = !statistics"
                                    id="cookie-statistics"
                                    class="cookie-toggle__track"
                                    :class="statistics ? 'cookie-toggle__track--active' : ''"
                                >
                                    <span
                                        class="cookie-toggle__thumb"
                                        :class="statistics ? 'cookie-toggle__thumb--active' : ''"
                                    ></span>
                                </button>
                            </label>
                        </div>
                        <details class="cookie-category__details">
                            <summary class="cookie-category__summary">Details anzeigen</summary>
                            <div class="cookie-category__detail-content">
                                <ul class="cookie-detail-list">
                                    <li><strong>Google Analytics (_ga, _ga_*)</strong> — Anonymisierte Besucherstatistiken (Ablauf: 2 Jahre)</li>
                                </ul>
                            </div>
                        </details>
                    </div>

                    {{-- 3. Marketing (default AUS, aktuell leer — zukunftssicher) --}}
                    <div class="cookie-category">
                        <div class="cookie-category__header">
                            <div class="cookie-category__info">
                                <h3 class="cookie-category__title">Marketing</h3>
                                <p class="cookie-category__description">
                                    Werden genutzt, um Ihnen relevante Werbung und Inhalte anzuzeigen. Aktuell setzen wir keine Marketing-Cookies ein.
                                </p>
                            </div>
                            <label class="cookie-toggle" for="cookie-marketing">
                                <button
                                    type="button"
                                    role="switch"
                                    :aria-checked="marketing.toString()"
                                    @click="marketing = !marketing"
                                    id="cookie-marketing"
                                    class="cookie-toggle__track"
                                    :class="marketing ? 'cookie-toggle__track--active' : ''"
                                >
                                    <span
                                        class="cookie-toggle__thumb"
                                        :class="marketing ? 'cookie-toggle__thumb--active' : ''"
                                    ></span>
                                </button>
                            </label>
                        </div>
                    </div>

                </div>
            </div>

            {{-- Footer mit Aktionen --}}
            <div class="cookie-modal__footer">
                <button
                    @click="acceptEssentialOnly()"
                    class="cookie-btn cookie-btn--reject"
                    type="button"
                >
                    Nur Notwendige
                </button>
                <button
                    @click="savePreferences()"
                    class="cookie-btn cookie-btn--save"
                    type="button"
                >
                    Auswahl speichern
                </button>
                <button
                    @click="acceptAll()"
                    class="cookie-btn cookie-btn--accept"
                    type="button"
                >
                    Alle akzeptieren
                </button>
            </div>
        </div>
    </div>

</div>
