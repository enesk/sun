{{-- Onboarding-Overlay: Erscheint nach erfolgreichem Claim --}}
{{-- Livewire-Component: #161 (Dimitri) liefert $showOverlay, $companyName, $profileProgress, $quickActions --}}
<div>
@if($showOverlay)
    <div class="dash-onboarding-overlay"
         x-data="{ open: true, confetti: true }"
         x-show="open"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         role="dialog"
         aria-modal="true"
         aria-labelledby="onboarding-title"
         @keydown.escape.window="open = false; $wire.dismiss()">

        <div class="dash-modal-backdrop" @click="open = false; $wire.dismiss()"></div>

        <div class="dash-onboarding-card">
            {{-- Celebration Header --}}
            <div class="dash-onboarding__header">
                {{-- Confetti Emoji --}}
                <div class="dash-onboarding__confetti" x-show="confetti" x-transition>
                    <span class="dash-onboarding__emoji" aria-hidden="true">🎉</span>
                </div>

                {{-- Success Icon --}}
                <div class="dash-onboarding__success-icon">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>

                <h3 id="onboarding-title" class="dash-onboarding__title">
                    Glückwunsch!
                </h3>
                <p class="dash-onboarding__subtitle">
                    <strong>{{ $companyName }}</strong> gehört jetzt Ihnen.
                </p>

                {{-- Trial Badge --}}
                <div class="dash-onboarding__trial-badge">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                    </svg>
                    30 Tage Premium — kostenlos aktiviert
                </div>
            </div>

            {{-- Progress Section --}}
            <div class="dash-onboarding__progress">
                <div class="dash-onboarding__progress-header">
                    <span class="dash-onboarding__progress-label">Profil-Vollständigkeit</span>
                    <span class="dash-onboarding__progress-value">{{ $profileProgress }}%</span>
                </div>
                <div class="dash-onboarding__progress-bar">
                    <div class="dash-onboarding__progress-fill"
                         style="width: {{ $profileProgress }}%"
                         x-data="{ width: 0 }"
                         x-init="setTimeout(() => width = {{ $profileProgress }}, 300)"
                         x-bind:style="'width: ' + width + '%'"
                         role="progressbar"
                         aria-valuenow="{{ $profileProgress }}"
                         aria-valuemin="0"
                         aria-valuemax="100">
                    </div>
                </div>
                <p class="dash-onboarding__progress-hint">
                    Ihr Profil ist zu {{ $profileProgress }}% ausgefüllt — vervollständigen Sie es für maximale Sichtbarkeit.
                </p>
            </div>

            {{-- Quick Actions --}}
            <div class="dash-onboarding__actions">
                <p class="dash-onboarding__actions-title">Jetzt vervollständigen:</p>

                <div class="dash-onboarding__action-grid">
                    {{-- Logo hochladen --}}
                    <a href="{{ route('verwaltung.companies.edit', $companyId ?? '') }}"
                       class="dash-onboarding__action {{ ($quickActions['logo'] ?? false) ? 'dash-onboarding__action--done' : '' }}">
                        <div class="dash-onboarding__action-icon dash-onboarding__action-icon--purple">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <span class="dash-onboarding__action-label">Logo hochladen</span>
                        @if($quickActions['logo'] ?? false)
                            <svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                            </svg>
                        @else
                            <svg class="w-4 h-4 flex-shrink-0" style="color: var(--dash-text-muted);" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        @endif
                    </a>

                    {{-- Öffnungszeiten --}}
                    <a href="{{ route('verwaltung.companies.edit', $companyId ?? '') }}"
                       class="dash-onboarding__action {{ ($quickActions['hours'] ?? false) ? 'dash-onboarding__action--done' : '' }}">
                        <div class="dash-onboarding__action-icon dash-onboarding__action-icon--blue">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <span class="dash-onboarding__action-label">Öffnungszeiten eintragen</span>
                        @if($quickActions['hours'] ?? false)
                            <svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                            </svg>
                        @else
                            <svg class="w-4 h-4 flex-shrink-0" style="color: var(--dash-text-muted);" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        @endif
                    </a>

                    {{-- Beschreibung --}}
                    <a href="{{ route('verwaltung.companies.edit', $companyId ?? '') }}"
                       class="dash-onboarding__action {{ ($quickActions['description'] ?? false) ? 'dash-onboarding__action--done' : '' }}">
                        <div class="dash-onboarding__action-icon dash-onboarding__action-icon--green">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <span class="dash-onboarding__action-label">Beschreibung ergänzen</span>
                        @if($quickActions['description'] ?? false)
                            <svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                            </svg>
                        @else
                            <svg class="w-4 h-4 flex-shrink-0" style="color: var(--dash-text-muted);" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        @endif
                    </a>
                </div>
            </div>

            {{-- CTA Footer --}}
            <div class="dash-onboarding__footer">
                <a href="{{ route('verwaltung.companies.edit', $companyId ?? '') }}"
                   class="dash-btn dash-btn-primary w-full justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Profil jetzt bearbeiten
                </a>
                <button type="button"
                        wire:click="dismiss"
                        @click="open = false"
                        class="dash-btn dash-btn-ghost w-full justify-center text-sm">
                    Später erledigen
                </button>
            </div>
        </div>
    </div>
@endif
</div>
