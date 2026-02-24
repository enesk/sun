<div>
@if($showModal && $stats)
    <div class="dash-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="downgrade-modal-title"
         x-data="{ open: true }"
         x-show="open"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @keydown.escape.window="open = false; $wire.dismiss()">

        <div class="dash-modal-backdrop" @click="open = false; $wire.dismiss()"></div>

        <div class="dash-modal" style="max-width: 28rem;">
            {{-- Header mit Warnsignal --}}
            <div class="text-center px-6 pt-6 pb-4">
                <div class="w-14 h-14 rounded-full mx-auto mb-3 flex items-center justify-center" style="background: linear-gradient(135deg, rgba(var(--portal-accent-rgb, 245 158 11), 0.15), rgba(var(--portal-accent-rgb, 245 158 11), 0.05));">
                    <svg class="w-7 h-7" style="color: var(--portal-accent);" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                    </svg>
                </div>
                <h3 id="downgrade-modal-title" class="text-lg font-bold" style="color: var(--dash-text-primary);">
                    Ihre Trial-Phase ist abgelaufen
                </h3>
                <p class="text-sm mt-1" style="color: var(--dash-text-secondary);">
                    {{ $stats['companyName'] }} ist jetzt im Free-Modus.
                </p>
            </div>

            {{-- Personalisierte Statistiken --}}
            <div class="px-6 pb-4">
                <div class="rounded-lg p-4 space-y-3" style="background: var(--dash-bg-secondary); border: 1px solid var(--dash-border);">
                    <p class="text-xs font-semibold uppercase tracking-wider" style="color: var(--dash-text-muted);">Während Ihrer Trial-Phase</p>

                    @if($stats['totalReviews'] > 0)
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" style="background: rgba(var(--portal-primary-rgb, 59 130 246), 0.1);">
                                <svg class="w-4 h-4" style="color: var(--portal-primary);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/></svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold" style="color: var(--dash-text-primary);">{{ $stats['totalReviews'] }} {{ $stats['totalReviews'] === 1 ? 'Bewertung' : 'Bewertungen' }}</p>
                                @if($stats['avgRating'] > 0)
                                    <p class="text-xs" style="color: var(--dash-text-muted);">Durchschnitt: {{ $stats['avgRating'] }} Sterne</p>
                                @endif
                            </div>
                        </div>
                    @endif

                    @if($stats['answeredReviews'] > 0)
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" style="background: rgba(22, 163, 74, 0.1);">
                                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold" style="color: var(--dash-text-primary);">{{ $stats['answeredReviews'] }} {{ $stats['answeredReviews'] === 1 ? 'Antwort' : 'Antworten' }} auf Bewertungen</p>
                                <p class="text-xs" style="color: var(--dash-danger, #dc2626);">Neue Antworten nicht mehr möglich</p>
                            </div>
                        </div>
                    @endif

                    @if($stats['galleryCount'] > 0)
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" style="background: rgba(139, 92, 246, 0.1);">
                                <svg class="w-4 h-4 text-violet-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold" style="color: var(--dash-text-primary);">{{ $stats['galleryCount'] }} {{ $stats['galleryCount'] === 1 ? 'Galerie-Bild' : 'Galerie-Bilder' }}</p>
                                <p class="text-xs" style="color: var(--dash-danger, #dc2626);">Werden öffentlich nicht mehr angezeigt</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Was verloren geht --}}
            <div class="px-6 pb-4">
                <p class="text-xs font-semibold mb-2" style="color: var(--dash-text-secondary);">Im Free-Modus nicht verfügbar:</p>
                <ul class="space-y-1.5">
                    <li class="flex items-center gap-2 text-xs" style="color: var(--dash-text-secondary);">
                        <svg class="w-3.5 h-3.5 shrink-0" style="color: var(--dash-danger);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        Auf Bewertungen antworten
                    </li>
                    <li class="flex items-center gap-2 text-xs" style="color: var(--dash-text-secondary);">
                        <svg class="w-3.5 h-3.5 shrink-0" style="color: var(--dash-danger);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        Bildergalerie (mehr als 3 Bilder)
                    </li>
                    <li class="flex items-center gap-2 text-xs" style="color: var(--dash-text-secondary);">
                        <svg class="w-3.5 h-3.5 shrink-0" style="color: var(--dash-danger);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        Cover-Bild & Öffnungszeiten
                    </li>
                    <li class="flex items-center gap-2 text-xs" style="color: var(--dash-text-secondary);">
                        <svg class="w-3.5 h-3.5 shrink-0" style="color: var(--dash-danger);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        Hervorgehobene Platzierung in der Suche
                    </li>
                    <li class="flex items-center gap-2 text-xs" style="color: var(--dash-text-secondary);">
                        <svg class="w-3.5 h-3.5 shrink-0" style="color: var(--dash-danger);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        Detaillierte Statistiken
                    </li>
                </ul>
            </div>

            {{-- CTAs --}}
            <div class="px-6 pb-6 space-y-2">
                @if($stats['convertUrl'])
                    <a href="{{ $stats['convertUrl'] }}" class="dash-btn dash-btn-primary w-full text-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                        Jetzt Premium sichern — ab 9,90 €/Monat
                    </a>
                @else
                    <a href="{{ route('verwaltung.subscriptions.index') }}" class="dash-btn dash-btn-primary w-full text-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                        Premium-Pläne ansehen
                    </a>
                @endif
                <button type="button"
                        wire:click="dismiss"
                        @click="open = false"
                        class="dash-btn dash-btn-ghost w-full text-center justify-center text-sm">
                    Später entscheiden
                </button>
            </div>
        </div>
    </div>
@endif
</div>
