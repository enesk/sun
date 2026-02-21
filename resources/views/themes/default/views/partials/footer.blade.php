{{-- Default Theme: Footer — Dreistufig (VR-7) --}}
<footer class="mt-auto reveal" role="contentinfo">

    {{-- ─── Stufe 1: Newsletter-Bar ─── --}}
    <section class="footer-newsletter" aria-label="Newsletter">
        <div class="container mx-auto px-4">
            <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                <div class="text-center md:text-left">
                    <h3 class="text-lg font-bold text-white">
                        Neue Firmen in Ihrer Nähe?
                    </h3>
                    <p class="text-white/70 text-sm mt-1">
                        Wir informieren Sie über neue Einträge und Bewertungen.
                    </p>
                </div>
                <form class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full md:w-auto" action="#" method="POST" aria-label="Newsletter-Anmeldung">
                    @csrf
                    <label for="footer-newsletter-email" class="sr-only">E-Mail-Adresse</label>
                    <input
                        type="email"
                        id="footer-newsletter-email"
                        name="email"
                        required
                        placeholder="Ihre E-Mail-Adresse"
                        class="footer-newsletter__input w-full sm:w-[320px]"
                        autocomplete="email"
                    >
                    <button type="submit" class="footer-newsletter__submit ripple">
                        Abonnieren
                    </button>
                </form>
            </div>
        </div>
    </section>

    {{-- ─── Stufe 2: Haupt-Footer (Dark) ─── --}}
    <div class="footer-main">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-10 lg:gap-8">

                {{-- Spalte 1: Portal-Info --}}
                <div>
                    <h4 class="footer-main__heading flex items-center gap-2">
                        @if(!empty($currentTenant) && $currentTenant->getAttribute('branding.logo_path'))
                            <img src="{{ asset($currentTenant->getAttribute('branding.logo_path')) }}" alt="" class="h-6 w-auto" aria-hidden="true">
                        @endif
                        {{ $currentTenant->name ?? config('app.name') }}
                    </h4>
                    @if(!empty($currentTenant) && $currentTenant->getAttribute('branding.site_description'))
                        <p class="text-sm text-[#94A3B8] leading-relaxed">
                            {{ $currentTenant->getAttribute('branding.site_description') }}
                        </p>
                    @endif

                    {{-- Social Media --}}
                    @php
                        $socials = [];
                        if (!empty($currentTenant)) {
                            if ($currentTenant->getAttribute('settings.social_facebook')) $socials['facebook'] = $currentTenant->getAttribute('settings.social_facebook');
                            if ($currentTenant->getAttribute('settings.social_instagram')) $socials['instagram'] = $currentTenant->getAttribute('settings.social_instagram');
                            if ($currentTenant->getAttribute('settings.social_twitter')) $socials['twitter'] = $currentTenant->getAttribute('settings.social_twitter');
                            if ($currentTenant->getAttribute('settings.social_linkedin')) $socials['linkedin'] = $currentTenant->getAttribute('settings.social_linkedin');
                        }
                    @endphp
                    @if(count($socials) > 0)
                        <div class="flex items-center gap-3 mt-5">
                            @foreach($socials as $platform => $url)
                                <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
                                   class="footer-main__social"
                                   aria-label="{{ ucfirst($platform) }}">
                                    @if($platform === 'facebook')
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z"/></svg>
                                    @elseif($platform === 'instagram')
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12.315 2c2.43 0 2.784.013 3.808.06 1.064.049 1.791.218 2.427.465a4.902 4.902 0 011.772 1.153 4.902 4.902 0 011.153 1.772c.247.636.416 1.363.465 2.427.048 1.067.06 1.407.06 4.123v.08c0 2.643-.012 2.987-.06 4.043-.049 1.064-.218 1.791-.465 2.427a4.902 4.902 0 01-1.153 1.772 4.902 4.902 0 01-1.772 1.153c-.636.247-1.363.416-2.427.465-1.067.048-1.407.06-4.123.06h-.08c-2.643 0-2.987-.012-4.043-.06-1.064-.049-1.791-.218-2.427-.465a4.902 4.902 0 01-1.772-1.153 4.902 4.902 0 01-1.153-1.772c-.247-.636-.416-1.363-.465-2.427-.047-1.024-.06-1.379-.06-3.808v-.63c0-2.43.013-2.784.06-3.808.049-1.064.218-1.791.465-2.427a4.902 4.902 0 011.153-1.772A4.902 4.902 0 015.45 2.525c.636-.247 1.363-.416 2.427-.465C8.901 2.013 9.256 2 11.685 2h.63zm-.081 1.802h-.468c-2.456 0-2.784.011-3.807.058-.975.045-1.504.207-1.857.344-.467.182-.8.398-1.15.748-.35.35-.566.683-.748 1.15-.137.353-.3.882-.344 1.857-.047 1.023-.058 1.351-.058 3.807v.468c0 2.456.011 2.784.058 3.807.045.975.207 1.504.344 1.857.182.466.399.8.748 1.15.35.35.683.566 1.15.748.353.137.882.3 1.857.344 1.054.048 1.37.058 4.041.058h.08c2.597 0 2.917-.01 3.96-.058.976-.045 1.505-.207 1.858-.344.466-.182.8-.398 1.15-.748.35-.35.566-.683.748-1.15.137-.353.3-.882.344-1.857.048-1.055.058-1.37.058-4.041v-.08c0-2.597-.01-2.917-.058-3.96-.045-.976-.207-1.505-.344-1.858a3.097 3.097 0 00-.748-1.15 3.098 3.098 0 00-1.15-.748c-.353-.137-.882-.3-1.857-.344-1.023-.047-1.351-.058-3.807-.058zM12 6.865a5.135 5.135 0 110 10.27 5.135 5.135 0 010-10.27zm0 1.802a3.333 3.333 0 100 6.666 3.333 3.333 0 000-6.666zm5.338-3.205a1.2 1.2 0 110 2.4 1.2 1.2 0 010-2.4z"/></svg>
                                    @elseif($platform === 'twitter')
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                                    @elseif($platform === 'linkedin')
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Spalte 2: Portal-Navigation --}}
                <nav aria-label="Portal-Navigation">
                    <h4 class="footer-main__heading">Portal</h4>
                    <ul class="space-y-3">
                        <li><a href="{{ route('home') }}" class="footer-main__link">Startseite</a></li>
                        <li><a href="{{ route('portal.companies.index') }}" class="footer-main__link">Firmenverzeichnis</a></li>
                        <li><a href="{{ route('portal.categories.index') }}" class="footer-main__link">Kategorien</a></li>
                    </ul>
                </nav>

                {{-- Spalte 3: Für Firmen --}}
                <nav aria-label="Für Firmen">
                    <h4 class="footer-main__heading">Für Firmen</h4>
                    <ul class="space-y-3">
                        <li><a href="{{ route('portal.companies.create') }}" class="footer-main__link">Firma eintragen</a></li>
                        @auth
                            <li><a href="{{ route('portal.owner.dashboard') }}" class="footer-main__link">Firmenprofil-Dashboard</a></li>
                        @else
                            <li><a href="{{ route('login') }}" class="footer-main__link">Login</a></li>
                        @endauth
                    </ul>
                </nav>

                {{-- Spalte 4: Rechtliches --}}
                <nav aria-label="Rechtliches">
                    <h4 class="footer-main__heading">Rechtliches</h4>
                    <ul class="space-y-3">
                        @if(!empty($currentTenant) && $currentTenant->getAttribute('branding.impressum'))
                            <li><a href="{{ route('portal.impressum') }}" class="footer-main__link">Impressum</a></li>
                        @endif
                        @if(!empty($currentTenant) && $currentTenant->getAttribute('branding.datenschutz'))
                            <li><a href="{{ route('portal.datenschutz') }}" class="footer-main__link">Datenschutz</a></li>
                        @endif
                        @if(!empty($currentTenant) && $currentTenant->getAttribute('settings.contact_email'))
                            <li>
                                <a href="mailto:{{ $currentTenant->getAttribute('settings.contact_email') }}" class="footer-main__link">
                                    Kontakt
                                </a>
                            </li>
                        @endif
                    </ul>
                </nav>
            </div>
        </div>
    </div>

    {{-- ─── Stufe 3: Copyright-Bar ─── --}}
    <div class="footer-bottom">
        <div class="container mx-auto px-4 flex flex-col sm:flex-row items-center justify-between gap-2">
            <p>
                @if(!empty($currentTenant))
                    {{ app(\App\Services\TenantBrandingService::class)->getFooterText($currentTenant) }}
                @else
                    &copy; {{ date('Y') }} {{ config('app.name') }}. Alle Rechte vorbehalten.
                @endif
            </p>
            <p class="text-[#475569]">Mit <span class="text-red-400" aria-label="Liebe">&#10084;</span> für lokale Unternehmen</p>
        </div>
    </div>

</footer>
