{{-- Company Card Component — VR-5: Rich Cards mit Badge-Overlays + Action-Buttons --}}
{{-- Usage: @include('components.company-card', ['company' => $company, 'layout' => 'grid', 'showDate' => false]) --}}
{{-- Layouts: 'grid' (vertical), 'list' (horizontal) --}}
@php
    $layout = $layout ?? ($themeOptions['listing_layout'] ?? 'grid');
    $showDate = $showDate ?? false;
@endphp

<article class="company-card group reveal"
         aria-label="Firmeneintrag: {{ $company->name }}">

    @if($layout === 'list')
        {{-- ═══ LIST LAYOUT (horizontal) ═══ --}}
        <div class="flex flex-col sm:flex-row">
            {{-- Image mit Badge-Overlays --}}
            <div class="sm:w-44 md:w-52 shrink-0 relative overflow-hidden">
                <a href="{{ route('portal.companies.show', $company->url_slug) }}" class="block aspect-[4/3] sm:aspect-square bg-base-200 overflow-hidden" tabindex="-1" aria-hidden="true">
                    @if($company->card_image_url)
                        <img src="{{ $company->card_image_url }}"
                             alt=""
                             class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110"
                             loading="lazy">
                        {{-- Gradient Overlay für Lesbarkeit der Badges --}}
                        <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent pointer-events-none"></div>
                    @else
                        <div class="w-full h-full flex items-center justify-center text-base-content/20 bg-gradient-to-br from-base-200 to-base-300">
                            <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                        </div>
                    @endif
                </a>

                {{-- Badge-Overlays auf dem Bild --}}
                <div class="absolute top-2 left-2 flex flex-col gap-1.5 z-10">
                    @if($company->is_premium)
                        <span class="company-card__badge company-card__badge--premium">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            Premium
                        </span>
                    @endif
                    @if($company->is_verified)
                        <span class="company-card__badge company-card__badge--verified">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            Verifiziert
                        </span>
                    @endif
                </div>

                {{-- Rating-Badge unten rechts auf dem Bild --}}
                @if($company->rating_count > 0)
                    <div class="absolute bottom-2 right-2 z-10 company-card__rating-badge">
                        <svg class="w-3.5 h-3.5 text-portal-accent" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        <span class="font-bold text-[#0F172A]">{{ number_format($company->rating, 1, ',', '') }}</span>
                        <span class="text-[#94A3B8]">({{ $company->rating_count }})</span>
                    </div>
                @endif
            </div>

            {{-- Content --}}
            <div class="flex-1 p-5 flex flex-col justify-between min-w-0">
                <div>
                    {{-- Name --}}
                    <h3 class="text-base font-bold text-[#0F172A] mb-1 truncate">
                        <a href="{{ route('portal.companies.show', $company->url_slug) }}"
                           class="hover:text-portal-primary transition-colors focus:outline-none focus:underline decoration-portal">
                            {{ $company->name }}
                        </a>
                    </h3>

                    {{-- Rating (inline, ohne Badges — die sind auf dem Bild) --}}
                    @if($company->rating_count > 0)
                        <div class="flex items-center gap-1.5 mb-2">
                            @include('components.star-rating', ['rating' => $company->rating, 'size' => 'sm'])
                            <span class="text-xs text-[#94A3B8]">({{ $company->rating_count }})</span>
                        </div>
                    @endif

                    {{-- Description --}}
                    @if($company->description)
                        <p class="text-sm text-[#64748B] line-clamp-2 mb-2">{{ $company->description }}</p>
                    @endif

                    {{-- Categories --}}
                    @if($company->categories->isNotEmpty())
                        <div class="flex flex-wrap gap-1 mb-2">
                            @foreach($company->categories->take(3) as $cat)
                                <a href="{{ route('portal.categories.show', $cat->slug) }}"
                                   class="company-card__tag">
                                    {{ $cat->name }}
                                </a>
                            @endforeach
                            @if($company->categories->count() > 3)
                                <span class="inline-block px-2 py-0.5 text-xs text-[#94A3B8]">+{{ $company->categories->count() - 3 }}</span>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Footer: Adresse + Action Buttons --}}
                <div class="flex items-center justify-between gap-3 mt-auto pt-2 border-t border-[#F1F5F9]">
                    @if($company->full_address)
                        <span class="inline-flex items-center gap-1 text-xs text-[#94A3B8] truncate">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            {{ $company->full_address }}
                        </span>
                    @endif
                    <div class="flex items-center gap-1.5 shrink-0">
                        @if($company->tel)
                            <a href="tel:{{ $company->tel }}" class="company-card__action" title="Anrufen" aria-label="{{ $company->name }} anrufen">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            </a>
                        @endif
                        @if($company->email)
                            <a href="mailto:{{ $company->email }}" class="company-card__action" title="E-Mail senden" aria-label="{{ $company->name }} per E-Mail kontaktieren">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            </a>
                        @endif
                        <a href="{{ route('portal.companies.show', $company->url_slug) }}" class="company-card__action company-card__action--primary" title="Details ansehen" aria-label="Details zu {{ $company->name }} ansehen">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>

    @else
        {{-- ═══ GRID LAYOUT (vertical) — Rich Card ═══ --}}

        {{-- Image mit Badge-Overlays --}}
        <div class="relative overflow-hidden">
            <a href="{{ route('portal.companies.show', $company->url_slug) }}" class="block aspect-[16/10] bg-base-200 overflow-hidden" tabindex="-1" aria-hidden="true">
                @if($company->card_image_url)
                    <img src="{{ $company->card_image_url }}"
                         alt=""
                         class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110"
                         loading="lazy">
                    {{-- Gradient Overlay —  Lesbarkeit für Badges + visueller Tiefe --}}
                    <div class="absolute inset-0 bg-gradient-to-t from-black/50 via-black/10 to-transparent pointer-events-none"></div>
                @else
                    <div class="w-full h-full flex items-center justify-center text-base-content/20 bg-gradient-to-br from-base-200 to-base-300">
                        <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                    </div>
                @endif
            </a>

            {{-- Badge-Overlays oben links --}}
            <div class="absolute top-3 left-3 flex flex-col gap-1.5 z-10">
                @if($company->is_premium)
                    <span class="company-card__badge company-card__badge--premium">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        Premium
                    </span>
                @endif
                @if($company->is_verified)
                    <span class="company-card__badge company-card__badge--verified">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        Verifiziert
                    </span>
                @endif
            </div>

            {{-- Rating-Badge unten rechts auf dem Bild --}}
            @if($company->rating_count > 0)
                <div class="absolute bottom-3 right-3 z-10 company-card__rating-badge">
                    <svg class="w-3.5 h-3.5 text-portal-accent" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                    <span class="font-bold text-[#0F172A]">{{ number_format($company->rating, 1, ',', '') }}</span>
                    <span class="text-[#94A3B8]">({{ $company->rating_count }})</span>
                </div>
            @endif
        </div>

        {{-- Content --}}
        <div class="p-5">
            {{-- Name --}}
            <h3 class="text-base font-bold text-[#0F172A] mb-1 truncate">
                <a href="{{ route('portal.companies.show', $company->url_slug) }}"
                   class="hover:text-portal-primary transition-colors focus:outline-none focus:underline decoration-portal">
                    {{ $company->name }}
                </a>
            </h3>

            {{-- Rating (Sterne — kompakt, Badges sind auf dem Bild) --}}
            @if($company->rating_count > 0)
                <div class="flex items-center gap-1.5 mb-2">
                    @include('components.star-rating', ['rating' => $company->rating, 'size' => 'sm'])
                    <span class="text-xs text-[#94A3B8]">({{ $company->rating_count }})</span>
                </div>
            @endif

            {{-- Description --}}
            @if($company->description)
                <p class="text-sm text-[#64748B] line-clamp-2 mb-3">{{ $company->description }}</p>
            @endif

            {{-- Categories --}}
            @if($company->categories->isNotEmpty())
                <div class="flex flex-wrap gap-1 mb-3">
                    @foreach($company->categories->take(2) as $cat)
                        <a href="{{ route('portal.categories.show', $cat->slug) }}"
                           class="company-card__tag">
                            {{ $cat->name }}
                        </a>
                    @endforeach
                    @if($company->categories->count() > 2)
                        <span class="inline-block px-2 py-0.5 text-xs text-[#94A3B8]">+{{ $company->categories->count() - 2 }}</span>
                    @endif
                </div>
            @endif

            {{-- Address --}}
            @if($company->full_address)
                <p class="flex items-center gap-1 text-xs text-[#94A3B8] truncate mb-3">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    {{ $company->full_address }}
                </p>
            @endif

            {{-- Action Buttons — immer sichtbar, Fitts's Law --}}
            <div class="company-card__actions">
                @if($company->tel)
                    <a href="tel:{{ $company->tel }}" class="company-card__action-btn ripple" aria-label="{{ $company->name }} anrufen">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                        Anrufen
                    </a>
                @endif
                <a href="{{ route('portal.companies.show', $company->url_slug) }}" class="company-card__action-btn company-card__action-btn--primary ripple">
                    Details
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>

            {{-- Datum (optional, z.B. auf der Startseite) --}}
            @if($showDate && $company->created_at)
                <p class="flex items-center gap-1 text-xs text-[#CBD5E1] mt-2">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <time datetime="{{ $company->created_at->toDateString() }}">{{ $company->created_at->diffForHumans() }}</time>
                </p>
            @endif
        </div>
    @endif
</article>
