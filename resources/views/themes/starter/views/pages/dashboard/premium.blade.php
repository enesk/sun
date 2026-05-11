@extends('layouts.dashboard')

@section('title', 'Premium')

@section('content')
    <div class="dash-page-header">
        <div>
            <h1 class="dash-page-title">Premium</h1>
            <p class="dash-page-subtitle">Mehr Sichtbarkeit und Funktionen für Ihr Unternehmen.</p>
        </div>
    </div>

    @if($company->is_premium)
        {{-- Active Premium State --}}
        <div class="dash-card dash-card-padded mb-6" style="border: 2px solid var(--portal-accent);">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: rgba(var(--portal-accent-rgb, 245 158 11), 0.12);">
                    <svg class="w-6 h-6" style="color: var(--portal-accent-dark)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-lg font-bold" style="color: var(--portal-accent-dark)">Premium aktiv</h2>
                    <p class="text-sm" style="color: var(--dash-text-secondary)">Sie nutzen alle Premium-Vorteile.</p>
                </div>
            </div>

            {{-- Premium Benefits List --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-4">
                @foreach([
                    ['icon' => 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z', 'text' => 'Hervorgehobener Eintrag in Suchergebnissen'],
                    ['icon' => 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z', 'text' => 'Auf Bewertungen antworten'],
                    ['icon' => 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z', 'text' => 'Bildergalerie bis zu 20 Fotos'],
                    ['icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', 'text' => 'Erweiterte Statistiken & Trends'],
                ] as $benefit)
                    <div class="flex items-center gap-2 text-sm">
                        <svg class="w-4 h-4 shrink-0" style="color: var(--portal-accent)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $benefit['icon'] }}"/>
                        </svg>
                        <span style="color: var(--dash-text-primary)">{{ $benefit['text'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Abo-Details & Verwaltung --}}
        @if($subscription)
            <div class="dash-card dash-card-padded mb-6">
                <h3 class="text-base font-semibold mb-4" style="color: var(--dash-text-primary)">Abo-Details</h3>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-4">
                    <div>
                        <span class="dash-stat-label">Plan</span>
                        <p class="text-sm font-medium mt-0.5" style="color: var(--dash-text-primary)">{{ $subscription->plan->name ?? 'Premium' }}</p>
                    </div>
                    <div>
                        <span class="dash-stat-label">Preis</span>
                        <p class="text-sm font-medium mt-0.5" style="color: var(--dash-text-primary)">
                            {{ money($subscription->price, $subscription->currency->code ?? 'EUR') }}
                            / {{ $subscription->interval->name ?? 'Monat' }}
                        </p>
                    </div>
                    @if($subscription->ends_at)
                        <div>
                            <span class="dash-stat-label">{{ $subscription->is_canceled_at_end_of_cycle ? 'Aktiv bis' : 'Nächste Verlängerung' }}</span>
                            <p class="text-sm font-medium mt-0.5" style="color: {{ $subscription->is_canceled_at_end_of_cycle ? 'var(--dash-danger)' : 'var(--dash-text-primary)' }}">
                                {{ $subscription->ends_at->format('d.m.Y') }}
                            </p>
                        </div>
                    @endif
                    <div>
                        <span class="dash-stat-label">Status</span>
                        <p class="text-sm font-medium mt-0.5" style="color: {{ $subscription->is_canceled_at_end_of_cycle ? 'var(--dash-danger)' : 'var(--dash-success)' }}">
                            {{ $subscription->is_canceled_at_end_of_cycle ? 'Gekündigt' : 'Aktiv' }}
                        </p>
                    </div>
                </div>

                {{-- Gekündigt-Warnung --}}
                @if($subscription->is_canceled_at_end_of_cycle)
                    <div class="dash-flash dash-flash-warning mb-4" role="alert" style="border-radius: 0.5rem;">
                        <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
                        <div>
                            <p class="text-sm font-medium">Ihr Abonnement wurde gekündigt.</p>
                            <p class="text-xs mt-0.5" style="opacity: 0.85">Sie können alle Premium-Funktionen noch bis zum {{ $subscription->ends_at ? $subscription->ends_at->format('d.m.Y') : 'Ende der Laufzeit' }} nutzen. Danach wird Ihr Eintrag auf den kostenlosen Plan zurückgestuft.</p>
                        </div>
                    </div>
                @endif

                {{-- Aktionen --}}
                <div class="flex flex-wrap items-center gap-2 pt-4" style="border-top: 1px solid var(--dash-border);">
                    <a href="{{ route('verwaltung.subscriptions.show', $subscription->uuid) }}"
                       class="dash-btn dash-btn-ghost dash-btn-sm">
                        Abo-Details anzeigen
                    </a>

                    @if($canCancel)
                        <a href="{{ route('verwaltung.subscriptions.cancel', $subscription->uuid) }}"
                           class="dash-btn dash-btn-ghost dash-btn-sm" style="color: var(--dash-danger);">
                            Premium kündigen
                        </a>
                    @endif

                    @if($canDiscardCancellation)
                        <a href="{{ route('verwaltung.subscriptions.index') }}"
                           class="dash-btn dash-btn-ghost dash-btn-sm" style="color: var(--dash-success);">
                            Kündigung widerrufen
                        </a>
                    @endif
                </div>
            </div>
        @endif
    @else
        {{-- Upgrade CTA — Hero Banner --}}
        <div class="bg-portal-gradient rounded-2xl p-6 sm:p-8 text-white mb-8 relative overflow-hidden">
            <div class="absolute inset-0 opacity-10">
                <svg class="absolute -right-8 -top-8 w-48 h-48 text-white/20" fill="currentColor" viewBox="0 0 24 24"><path d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
            </div>
            <div class="max-w-2xl relative">
                <h2 class="text-xl sm:text-2xl font-bold mb-2">Werden Sie sichtbarer</h2>
                <p class="text-white/80 text-sm sm:text-base mb-4">
                    Premium-Einträge erhalten durchschnittlich <strong class="text-white">3x mehr Aufrufe</strong> als kostenlose Einträge.
                </p>
                <div class="flex flex-wrap items-center gap-3">
                    <a href="#plans" class="dash-btn" style="background: white; color: var(--portal-primary-dark);">
                        Pläne vergleichen
                    </a>
                </div>
            </div>
        </div>

        {{-- Feature Comparison --}}
        <div id="plans" class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            {{-- Free Plan --}}
            <div class="dash-card dash-card-padded">
                <div class="mb-4">
                    <span class="dash-badge dash-badge-neutral mb-2">Aktueller Plan</span>
                    <h3 class="text-lg font-bold" style="color: var(--dash-text-primary)">Kostenlos</h3>
                    <p class="text-2xl font-bold mt-1" style="color: var(--dash-text-primary)">0 € <span class="text-sm font-normal" style="color: var(--dash-text-muted)">/ Monat</span></p>
                </div>
                <ul class="space-y-2.5">
                    @foreach([
                        'Firmeneintrag (Name, Adresse, Kontakt)',
                        'Bis zu 3 Kategorien',
                        'Logo-Upload',
                        'Bewertungen empfangen',
                        'Sichtbar im Verzeichnis',
                    ] as $feature)
                        <li class="flex items-start gap-2 text-sm">
                            <svg class="w-4 h-4 shrink-0 mt-0.5" style="color: var(--dash-success)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span style="color: var(--dash-text-secondary)">{{ $feature }}</span>
                        </li>
                    @endforeach
                    @foreach([
                        'Bewertungen beantworten',
                        'Bildergalerie (nur Logo)',
                        'Öffnungszeiten anzeigen',
                        'Erweiterte Statistiken',
                        'Hervorgehobene Platzierung',
                    ] as $locked)
                        <li class="flex items-start gap-2 text-sm opacity-40">
                            <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true" style="color: var(--dash-text-muted)">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                            <span style="color: var(--dash-text-muted)">{{ $locked }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- Premium Plan --}}
            <div class="dash-card dash-card-padded relative" style="border: 2px solid var(--portal-accent);">
                <div class="absolute -top-3 left-4">
                    <span class="dash-badge dash-badge-premium">Empfohlen</span>
                </div>
                <div class="mb-4">
                    <h3 class="text-lg font-bold flex items-center gap-2" style="color: var(--dash-text-primary)">
                        Premium
                        <svg class="w-5 h-5" style="color: var(--portal-accent)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                        </svg>
                    </h3>
                    <div class="mt-2" x-data="{ yearly: false }">
                        <div class="flex items-center gap-2 mb-2">
                            <button @click="yearly = false" :class="!yearly ? 'font-semibold' : 'opacity-60'" class="text-xs transition-all" style="color: var(--dash-text-secondary)">Monatlich</button>
                            <button @click="yearly = !yearly"
                                    :class="yearly ? 'justify-end' : 'justify-start'"
                                    class="relative w-10 h-5 rounded-full flex items-center px-0.5 transition-colors"
                                    :style="yearly ? 'background-color: var(--portal-accent)' : 'background-color: var(--dash-border)'"
                                    role="switch"
                                    :aria-checked="yearly.toString()"
                                    aria-label="Jahresabrechnung">
                                <span class="w-4 h-4 rounded-full bg-white shadow-sm transition-transform" :class="yearly ? 'translate-x-5' : 'translate-x-0'"></span>
                            </button>
                            <button @click="yearly = true" :class="yearly ? 'font-semibold' : 'opacity-60'" class="text-xs transition-all" style="color: var(--dash-text-secondary)">
                                Jährlich <span class="text-xs font-medium" style="color: var(--portal-accent)">-17%</span>
                            </button>
                        </div>
                        <p class="text-2xl font-bold" style="color: var(--dash-text-primary)">
                            <span x-show="!yearly">9,90 €</span>
                            <span x-show="yearly" x-cloak>99 €</span>
                            <span class="text-sm font-normal" style="color: var(--dash-text-muted)" x-show="!yearly">/ Monat</span>
                            <span class="text-sm font-normal" style="color: var(--dash-text-muted)" x-show="yearly" x-cloak>/ Jahr</span>
                        </p>
                        <p x-show="yearly" x-cloak class="text-xs mt-0.5" style="color: var(--portal-accent)">2 Monate gratis</p>
                    </div>
                </div>
                <ul class="space-y-2.5">
                    @foreach([
                        'Alles aus dem kostenlosen Plan',
                        'Bewertungen beantworten',
                        'Bildergalerie (bis zu 20 Fotos)',
                        'Cover/Banner-Bild',
                        'Öffnungszeiten anzeigen',
                        'Hervorgehobener Eintrag (Top-Platzierung)',
                        'Erweiterte Statistiken & Trends',
                        'Social-Media-Links',
                        'Prioritäts-Support',
                    ] as $feature)
                        <li class="flex items-start gap-2 text-sm">
                            <svg class="w-4 h-4 shrink-0 mt-0.5" style="color: var(--portal-accent)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span style="color: var(--dash-text-primary)">{{ $feature }}</span>
                        </li>
                    @endforeach
                </ul>

                <a x-show="!yearly" href="{{ route('tenant.checkout.subscription', 'premium-monthly') }}" class="dash-btn dash-btn-primary w-full mt-6 text-center" style="background-color: var(--portal-accent); color: white;">
                    30 Tage kostenlos testen
                </a>
                <a x-show="yearly" x-cloak href="{{ route('tenant.checkout.subscription', 'premium-yearly') }}" class="dash-btn dash-btn-primary w-full mt-6 text-center" style="background-color: var(--portal-accent); color: white;">
                    30 Tage kostenlos testen
                </a>
                <p class="text-xs text-center mt-2" style="color: var(--dash-text-muted)">Keine Bindung. Jederzeit kündbar.</p>
            </div>
        </div>

        {{-- Feature Detail Cards --}}
        <h3 class="text-base font-semibold mb-4" style="color: var(--dash-text-primary)">Was Premium Ihnen bringt</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
            @foreach([
                [
                    'icon' => 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z',
                    'title' => 'Top-Platzierung',
                    'desc' => 'Ihr Eintrag erscheint vor allen kostenlosen Einträgen in den Suchergebnissen und auf der Startseite.',
                ],
                [
                    'icon' => 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z',
                    'title' => 'Auf Bewertungen antworten',
                    'desc' => 'Reagieren Sie auf Kundenbewertungen — zeigen Sie, dass Ihnen Feedback wichtig ist.',
                ],
                [
                    'icon' => 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z',
                    'title' => 'Bildergalerie',
                    'desc' => 'Laden Sie bis zu 20 Fotos hoch und zeigen Sie Ihr Unternehmen von seiner besten Seite.',
                ],
                [
                    'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
                    'title' => 'Detaillierte Statistiken',
                    'desc' => 'Sehen Sie Wochen-Trends, Besucherherkunft und welche Suchbegriffe zu Ihnen führen.',
                ],
                [
                    'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
                    'title' => 'Öffnungszeiten',
                    'desc' => 'Zeigen Sie Ihre Öffnungszeiten auf Ihrem Profil — inkl. Echtzeit-Status „Jetzt geöffnet".',
                ],
                [
                    'icon' => 'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1',
                    'title' => 'Social-Media-Links',
                    'desc' => 'Verlinken Sie Ihre Facebook-, Instagram- und LinkedIn-Profile direkt auf Ihrem Eintrag.',
                ],
            ] as $card)
                <div class="dash-card dash-card-padded">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center mb-3" style="background-color: rgba(var(--portal-accent-rgb, 245 158 11), 0.08);">
                        <svg class="w-5 h-5" style="color: var(--portal-accent)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $card['icon'] }}"/>
                        </svg>
                    </div>
                    <h4 class="text-sm font-semibold mb-1" style="color: var(--dash-text-primary)">{{ $card['title'] }}</h4>
                    <p class="text-xs leading-relaxed" style="color: var(--dash-text-secondary)">{{ $card['desc'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- Loss Aversion --}}
        <div class="dash-card dash-card-padded" style="background-color: var(--dash-bg);">
            <h3 class="text-sm font-semibold mb-3" style="color: var(--dash-text-primary)">Was Sie ohne Premium verpassen</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                @foreach([
                    ['icon' => 'M13 17h8m0 0V9m0 8l-8-8-4 4-6-6', 'text' => '~3x weniger Sichtbarkeit'],
                    ['icon' => 'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636', 'text' => 'Keine detaillierten Statistiken'],
                    ['icon' => 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z', 'text' => 'Auf Bewertungen antworten nicht möglich'],
                ] as $item)
                    <div class="flex items-center gap-2 text-sm">
                        <svg class="w-4 h-4 shrink-0" style="color: var(--dash-danger)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $item['icon'] }}"/>
                        </svg>
                        <span style="color: var(--dash-text-secondary)">{{ $item['text'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
@endsection
