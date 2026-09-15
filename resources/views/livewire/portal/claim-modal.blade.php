{{-- Claim-Modal: 4 Szenarien nach Rathanas V5-Spec --}}
{{-- Livewire-Component: #160 (Dimitri) liefert $company, $scenario, $showModal, $activeTab --}}
<div>
    @if($showModal)
        <div class="claim-modal-overlay"
             x-data="claimModal()"
             x-on:keydown.escape.window="$wire.closeModal()"
             role="dialog"
             aria-modal="true"
             aria-labelledby="claim-modal-title">

            {{-- Backdrop --}}
            <div class="claim-modal-backdrop" wire:click="closeModal" aria-hidden="true"></div>

            {{-- Modal Container --}}
            <div class="claim-modal"
                 x-bind:class="{ 'claim-modal--bottom-sheet': window.innerWidth < 640 }"
                 @click.stop>

                {{-- Drag Handle (Mobile Bottom Sheet) --}}
                <div class="claim-modal__drag-handle sm:hidden" aria-hidden="true">
                    <div class="claim-modal__drag-bar"></div>
                </div>

                {{-- ==========================================
                     SUCCESS STATE: Claim erfolgreich!
                     Redirect ins Dashboard
                     ========================================== --}}
                @if($claimSuccess)
                    <div class="claim-modal__body" style="text-align: center; padding: 2.5rem 1.5rem;">
                        {{-- Almost-Done Icon --}}
                        <div style="width: 64px; height: 64px; border-radius: 50%; background: rgba(59, 130, 246, 0.1); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1.25rem;">
                            <svg width="32" height="32" fill="none" stroke="#3b82f6" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                            </svg>
                        </div>

                        <h3 id="claim-modal-title" class="claim-modal__title" style="margin-bottom: 0.5rem;">
                            {{ __('portal.claim_modal.success.heading') }}
                        </h3>
                        <p class="claim-modal__subtitle" style="margin-bottom: 1.5rem;">
                            {!! __('portal.claim_modal.success.text', ['firma' => '<span class="claim-modal__accent">'.e($company->name).'</span>']) !!}
                            {{ __('portal.claim_modal.success.upload_hint') }}
                        </p>

                        <button type="button"
                                wire:click="goToVerification"
                                class="claim-modal__cta"
                                style="width: 100%;">
                            <span wire:loading.remove wire:target="goToVerification">
                                {{ __('portal.claim_modal.success.upload') }}
                                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                </svg>
                            </span>
                            <span wire:loading wire:target="goToVerification" class="inline-flex items-center gap-2">
                                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ __('portal.claim_modal.success.loading') }}
                            </span>
                        </button>

                        {{-- E-Mail-Verifizierungs-Hinweis (nur bei Registrierung) --}}
                        @if($scenario === 'guest')
                            <p style="font-size: 0.8125rem; color: var(--dash-text-secondary, #64748b); margin-top: 1rem; line-height: 1.4;">
                                {{ __('portal.claim_modal.success.verify_sent') }}
                                {{ __('portal.claim_modal.success.verify_click') }}
                            </p>
                        @endif
                    </div>
                @else

                {{-- Company Context Header --}}
                <div class="claim-modal__company-header">
                    <div class="claim-modal__company-info">
                        @if($company->getFirstMediaUrl('logo', 'thumb'))
                            <img src="{{ $company->getFirstMediaUrl('logo', 'thumb') }}"
                                 alt="{{ $company->name }}"
                                 class="claim-modal__company-logo">
                        @else
                            <div class="claim-modal__company-logo-placeholder">
                                {{ strtoupper(substr($company->name, 0, 1)) }}
                            </div>
                        @endif
                        <div>
                            <p class="claim-modal__company-name">{{ $company->name }}</p>
                            @if($company->city)
                                <p class="claim-modal__company-location">{{ $company->city->name }}</p>
                            @endif
                        </div>
                    </div>
                    <button wire:click="closeModal"
                            class="claim-modal__close"
                            type="button"
                            aria-label="{{ __('portal.layout.close') }}">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                {{-- ==========================================
                     SZENARIO 1: Nicht eingeloggt (90%)
                     Register/Login Tabs
                     ========================================== --}}
                @if($scenario === 'guest')
                    <div class="claim-modal__body">
                        {{-- Headline mit Firmenname --}}
                        <div class="claim-modal__headline">
                            <h3 id="claim-modal-title" class="claim-modal__title">
                                {!! __('portal.claim_modal.guest.heading', ['firma' => '<span class="claim-modal__accent">'.e($company->name).'</span>']) !!}
                            </h3>
                            <p class="claim-modal__subtitle">{{ __('portal.claim_modal.guest.subtitle') }}</p>
                        </div>

                        {{-- Tabs: Registrieren / Anmelden --}}
                        <div class="claim-modal__tabs" role="tablist" aria-label="{{ __('portal.claim_modal.guest.tabs_label') }}">
                            <button type="button"
                                    role="tab"
                                    aria-selected="{{ $activeTab === 'register' ? 'true' : 'false' }}"
                                    aria-controls="claim-tab-register"
                                    class="claim-modal__tab {{ $activeTab === 'register' ? 'claim-modal__tab--active' : '' }}"
                                    wire:click="$set('activeTab', 'register')">
                                {{ __('portal.claim_modal.guest.tab_register') }}
                            </button>
                            <button type="button"
                                    role="tab"
                                    aria-selected="{{ $activeTab === 'login' ? 'true' : 'false' }}"
                                    aria-controls="claim-tab-login"
                                    class="claim-modal__tab {{ $activeTab === 'login' ? 'claim-modal__tab--active' : '' }}"
                                    wire:click="$set('activeTab', 'login')">
                                {{ __('portal.claim_modal.guest.tab_login') }}
                            </button>
                        </div>

                        {{-- Tab: Registrieren --}}
                        @if($activeTab === 'register')
                            <form wire:submit="register" id="claim-tab-register" role="tabpanel" class="claim-modal__form">
                                @csrf

                                <div>
                                    <label for="claim-name" class="claim-modal__label">{{ __('portal.claim_modal.register.name_label') }} *</label>
                                    <input type="text"
                                           id="claim-name"
                                           wire:model="name"
                                           class="claim-modal__input"
                                           placeholder="{{ __('portal.claim_modal.register.name_placeholder') }}"
                                           required
                                           autocomplete="name">
                                    @error('name')
                                        <p class="claim-modal__error" role="alert">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="claim-email" class="claim-modal__label">{{ __('portal.claim_modal.register.email_label') }} *</label>
                                    <input type="email"
                                           id="claim-email"
                                           wire:model="email"
                                           class="claim-modal__input"
                                           placeholder="{{ __('portal.claim_modal.register.email_placeholder') }}"
                                           required
                                           autocomplete="email">
                                    @error('email')
                                        <p class="claim-modal__error" role="alert">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="claim-password" class="claim-modal__label">{{ __('portal.claim_modal.register.password_label') }} *</label>
                                    <input type="password"
                                           id="claim-password"
                                           wire:model="password"
                                           class="claim-modal__input"
                                           placeholder="{{ __('portal.claim_modal.register.password_placeholder') }}"
                                           required
                                           autocomplete="new-password"
                                           minlength="8">
                                    @error('password')
                                        <p class="claim-modal__error" role="alert">{{ $message }}</p>
                                    @enderror
                                </div>

                                {{-- Honeypot --}}
                                <div class="hidden" aria-hidden="true">
                                    <input type="text" wire:model="website_url" tabindex="-1" autocomplete="off">
                                </div>

                                <button type="submit"
                                        class="claim-modal__cta"
                                        wire:loading.attr="disabled"
                                        wire:loading.class="claim-modal__cta--loading">
                                    <span wire:loading.remove wire:target="register">
                                        {{ __('portal.claim_modal.register.submit') }}
                                    </span>
                                    <span wire:loading wire:target="register" class="inline-flex items-center gap-2">
                                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        {{ __('portal.claim_modal.register.loading') }}
                                    </span>
                                </button>

                                <p class="claim-modal__legal">
                                    {!! __('portal.claim_modal.register.legal', [
                                        'agb' => '<a href="'.e(route('terms-of-service')).'" target="_blank" class="claim-modal__link">'.e(__('portal.claim_modal.register.terms_link')).'</a>',
                                        'datenschutz' => '<a href="'.e(route('privacy-policy')).'" target="_blank" class="claim-modal__link">'.e(__('portal.claim_modal.register.privacy_link')).'</a>',
                                    ]) !!}
                                </p>
                            </form>
                        @endif

                        {{-- Tab: Anmelden --}}
                        @if($activeTab === 'login')
                            <form wire:submit="login" id="claim-tab-login" role="tabpanel" class="claim-modal__form">
                                @csrf

                                <div>
                                    <label for="claim-login-email" class="claim-modal__label">{{ __('portal.claim_modal.login.email_label') }} *</label>
                                    <input type="email"
                                           id="claim-login-email"
                                           wire:model="loginEmail"
                                           class="claim-modal__input"
                                           placeholder="{{ __('portal.claim_modal.login.email_placeholder') }}"
                                           required
                                           autocomplete="email">
                                    @error('loginEmail')
                                        <p class="claim-modal__error" role="alert">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="claim-login-password" class="claim-modal__label">{{ __('portal.claim_modal.login.password_label') }} *</label>
                                    <input type="password"
                                           id="claim-login-password"
                                           wire:model="loginPassword"
                                           class="claim-modal__input"
                                           placeholder="{{ __('portal.claim_modal.login.password_placeholder') }}"
                                           required
                                           autocomplete="current-password">
                                    @error('loginPassword')
                                        <p class="claim-modal__error" role="alert">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="flex items-center justify-between">
                                    <label class="claim-modal__checkbox-label">
                                        <input type="checkbox" wire:model="remember" class="claim-modal__checkbox">
                                        <span>{{ __('portal.claim_modal.login.remember') }}</span>
                                    </label>
                                    <a href="{{ route('password.request') }}" class="claim-modal__link text-sm">
                                        {{ __('portal.claim_modal.login.forgot') }}
                                    </a>
                                </div>

                                <button type="submit"
                                        class="claim-modal__cta"
                                        wire:loading.attr="disabled"
                                        wire:loading.class="claim-modal__cta--loading">
                                    <span wire:loading.remove wire:target="login">
                                        {{ __('portal.claim_modal.login.submit', ['firma' => $company->name]) }}
                                    </span>
                                    <span wire:loading wire:target="login" class="inline-flex items-center gap-2">
                                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        {{ __('portal.claim_modal.login.loading') }}
                                    </span>
                                </button>
                            </form>
                        @endif

                        {{-- Benefits Footer --}}
                        <div class="claim-modal__benefits">
                            <div class="claim-modal__benefit">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                <span>{{ __('portal.claim_modal.benefits.free') }}</span>
                            </div>
                            <div class="claim-modal__benefit">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                                <span>{{ __('portal.claim_modal.benefits.gdpr') }}</span>
                            </div>
                            <div class="claim-modal__benefit">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                                <span>{{ __('portal.claim_modal.benefits.premium_trial') }}</span>
                            </div>
                        </div>
                    </div>

                {{-- ==========================================
                     SZENARIO 2: Eingeloggt, keine Firma
                     Bestätigungs-Checkbox
                     ========================================== --}}
                @elseif($scenario === 'logged_in_no_company')
                    <div class="claim-modal__body">
                        <div class="claim-modal__headline">
                            <h3 id="claim-modal-title" class="claim-modal__title">
                                {!! __('portal.claim_modal.no_company.heading', ['firma' => '<span class="claim-modal__accent">'.e($company->name).'</span>']) !!}
                            </h3>
                            <p class="claim-modal__subtitle">
                                {{ __('portal.claim_modal.no_company.subtitle', ['name' => auth()->user()->name]) }}
                            </p>
                        </div>

                        {{-- Benefits --}}
                        <div class="claim-modal__benefit-list">
                            <div class="claim-modal__benefit-item">
                                <div class="claim-modal__benefit-icon claim-modal__benefit-icon--primary">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="claim-modal__benefit-title">{{ __('portal.claim_modal.no_company.update_title') }}</p>
                                    <p class="claim-modal__benefit-desc">{{ __('portal.claim_modal.no_company.update_text') }}</p>
                                </div>
                            </div>
                            <div class="claim-modal__benefit-item">
                                <div class="claim-modal__benefit-icon claim-modal__benefit-icon--success">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="claim-modal__benefit-title">{{ __('portal.claim_modal.no_company.reviews_title') }}</p>
                                    <p class="claim-modal__benefit-desc">{{ __('portal.claim_modal.no_company.reviews_text') }}</p>
                                </div>
                            </div>
                            <div class="claim-modal__benefit-item">
                                <div class="claim-modal__benefit-icon claim-modal__benefit-icon--accent">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="claim-modal__benefit-title">{{ __('portal.claim_modal.no_company.stats_title') }}</p>
                                    <p class="claim-modal__benefit-desc">{{ __('portal.claim_modal.no_company.stats_text') }}</p>
                                </div>
                            </div>
                        </div>

                        {{-- Confirmation --}}
                        <form wire:submit="claim" class="claim-modal__form">
                            <label class="claim-modal__confirm-label">
                                <input type="checkbox"
                                       wire:model="confirmOwner"
                                       class="claim-modal__checkbox claim-modal__checkbox--lg"
                                       required>
                                <span>{!! __('portal.claim_modal.no_company.confirm', ['firma' => '<strong>'.e($company->name).'</strong>']) !!}</span>
                            </label>
                            @error('confirmOwner')
                                <p class="claim-modal__error" role="alert">{{ $message }}</p>
                            @enderror

                            <button type="submit"
                                    class="claim-modal__cta"
                                    wire:loading.attr="disabled"
                                    wire:loading.class="claim-modal__cta--loading">
                                <span wire:loading.remove wire:target="claim">
                                    {{ __('portal.claim_modal.no_company.submit') }}
                                </span>
                                <span wire:loading wire:target="claim" class="inline-flex items-center gap-2">
                                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    {{ __('portal.claim_modal.no_company.loading') }}
                                </span>
                            </button>
                        </form>
                    </div>

                {{-- ==========================================
                     SZENARIO 3: Eingeloggt, hat bereits Firma
                     Multi-Firma Option
                     ========================================== --}}
                @elseif($scenario === 'logged_in_has_company')
                    <div class="claim-modal__body">
                        <div class="claim-modal__headline">
                            <h3 id="claim-modal-title" class="claim-modal__title">
                                {{ __('portal.claim_modal.has_company.heading') }}
                            </h3>
                            <p class="claim-modal__subtitle">
                                {!! __('portal.claim_modal.has_company.subtitle', ['firma' => '<span class="claim-modal__accent">'.e($company->name).'</span>']) !!}
                            </p>
                        </div>

                        {{-- Existing Company Info --}}
                        @if($existingCompany)
                            <div class="claim-modal__existing-company">
                                <div class="claim-modal__existing-badge">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    {{ __('portal.claim_modal.has_company.current') }}
                                </div>
                                <div class="flex items-center gap-3">
                                    @if($existingCompany->getFirstMediaUrl('logo', 'thumb'))
                                        <img src="{{ $existingCompany->getFirstMediaUrl('logo', 'thumb') }}"
                                             alt="{{ $existingCompany->name }}"
                                             class="w-10 h-10 rounded-lg object-cover">
                                    @else
                                        <div class="w-10 h-10 rounded-lg flex items-center justify-center text-sm font-bold"
                                             style="background: rgba(var(--portal-primary-rgb), 0.1); color: var(--portal-primary);">
                                            {{ strtoupper(substr($existingCompany->name, 0, 1)) }}
                                        </div>
                                    @endif
                                    <p class="text-sm font-medium" style="color: var(--dash-text-primary, #1a1a2e);">{{ $existingCompany->name }}</p>
                                </div>
                            </div>
                        @endif

                        <form wire:submit="claimAdditional" class="claim-modal__form">
                            <label class="claim-modal__confirm-label">
                                <input type="checkbox"
                                       wire:model="confirmOwner"
                                       class="claim-modal__checkbox claim-modal__checkbox--lg"
                                       required>
                                <span>{!! __('portal.claim_modal.has_company.confirm', ['firma' => '<strong>'.e($company->name).'</strong>']) !!}</span>
                            </label>

                            <button type="submit"
                                    class="claim-modal__cta"
                                    wire:loading.attr="disabled"
                                    wire:loading.class="claim-modal__cta--loading">
                                <span wire:loading.remove wire:target="claimAdditional">
                                    {{ __('portal.claim_modal.has_company.submit', ['firma' => $company->name]) }}
                                </span>
                                <span wire:loading wire:target="claimAdditional" class="inline-flex items-center gap-2">
                                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    {{ __('portal.claim_modal.has_company.loading') }}
                                </span>
                            </button>
                        </form>
                    </div>

                {{-- ==========================================
                     SZENARIO 4: Bereits geclaimed
                     Dispute / Kontakt
                     ========================================== --}}
                @elseif($scenario === 'already_claimed')
                    <div class="claim-modal__body">
                        <div class="claim-modal__headline">
                            <div class="claim-modal__warning-icon">
                                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
                                </svg>
                            </div>
                            <h3 id="claim-modal-title" class="claim-modal__title">
                                {{ __('portal.claim_modal.already_claimed.heading') }}
                            </h3>
                            <p class="claim-modal__subtitle">
                                {!! __('portal.claim_modal.already_claimed.subtitle', ['firma' => '<span class="claim-modal__accent">'.e($company->name).'</span>']) !!}
                            </p>
                        </div>

                        {{-- Dispute Info --}}
                        <div class="claim-modal__dispute-info">
                            <p class="text-sm" style="color: var(--dash-text-secondary, #64748b);">
                                {{ __('portal.claim_modal.already_claimed.dispute_text') }}
                            </p>
                        </div>

                        <div class="claim-modal__dispute-actions">
                            <button type="button"
                                    wire:click="requestDispute"
                                    class="claim-modal__cta claim-modal__cta--secondary"
                                    wire:loading.attr="disabled">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                                <span wire:loading.remove wire:target="requestDispute">{{ __('portal.claim_modal.already_claimed.dispute') }}</span>
                                <span wire:loading wire:target="requestDispute">{{ __('portal.claim_modal.already_claimed.loading') }}</span>
                            </button>

                            <a href="{{ route('companies.suggest-edit', $company->slug) }}"
                               class="claim-modal__cta claim-modal__cta--outline">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                                {{ __('portal.profile.about.suggest_edit') }}
                            </a>
                        </div>

                        <button type="button"
                                wire:click="closeModal"
                                class="claim-modal__dismiss">
                            {{ __('portal.layout.close') }}
                        </button>
                    </div>
                @endif

                @endif {{-- End claimSuccess else --}}
            </div>
        </div>
    @endif
</div>

@script
<script>
    function claimModal() {
        return {
            init() {
                // Lock body scroll when modal is open
                document.body.style.overflow = 'hidden';
                this.$cleanup(() => {
                    document.body.style.overflow = '';
                });
            }
        }
    }
</script>
@endscript
