{{-- Sidebar Component --}}
{{-- Usage: @include('components.sidebar', ['categories' => $categories, 'cities' => $cities, 'activeCategory' => $slug, 'activeCity' => $city]) --}}
@php
    $categories = $categories ?? collect();
    $cities = $cities ?? collect();
    $activeCategory = $activeCategory ?? null;
    $activeCity = $activeCity ?? null;
    $showSidebar = $themeOptions['show_sidebar'] ?? true;
@endphp

@if($showSidebar)
<aside class="space-y-8" aria-label="Seitenleiste">

    {{-- Ad: Sidebar Top --}}
    <x-ad-slot position="sidebar_top" />

    {{-- ═══ Kategorien ═══ --}}
    @if($categories->isNotEmpty())
        <div>
            <h3 class="text-sm font-semibold text-base-content uppercase tracking-wider mb-3">Kategorien</h3>
            <nav aria-label="Kategorien">
                <ul class="space-y-1">
                    {{-- Alle anzeigen --}}
                    <li>
                        <a href="{{ route('portal.companies.index') }}"
                           class="flex items-center justify-between px-3 py-2 rounded-lg text-sm transition-colors
                                  {{ !$activeCategory ? 'sidebar-active' : 'text-base-content/70 hover:bg-base-200 hover:text-base-content' }}"
                           @if(!$activeCategory) aria-current="page" @endif>
                            <span>Alle Kategorien</span>
                        </a>
                    </li>
                    @foreach($categories as $category)
                        <li>
                            <a href="{{ route('portal.categories.show', $category->slug) }}"
                               class="flex items-center justify-between px-3 py-2 rounded-lg text-sm transition-colors
                                      {{ $activeCategory === $category->slug ? 'sidebar-active' : 'text-base-content/70 hover:bg-base-200 hover:text-base-content' }}"
                               @if($activeCategory === $category->slug) aria-current="page" @endif>
                                <span>{{ $category->name }}</span>
                                @if(isset($category->companies_count))
                                    <span class="text-xs {{ $activeCategory === $category->slug ? 'text-white/70' : 'text-base-content/40' }}">
                                        {{ $category->companies_count }}
                                    </span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        </div>
    @endif

    {{-- ═══ Städte ═══ --}}
    @if($cities->isNotEmpty())
        <div>
            <h3 class="text-sm font-semibold text-base-content uppercase tracking-wider mb-3">Städte</h3>
            <nav aria-label="Städte-Filter">
                <ul class="space-y-1">
                    <li>
                        <a href="{{ request()->fullUrlWithoutQuery('city') }}"
                           class="flex items-center justify-between px-3 py-2 rounded-lg text-sm transition-colors
                                  {{ !$activeCity ? 'sidebar-active' : 'text-base-content/70 hover:bg-base-200 hover:text-base-content' }}"
                           @if(!$activeCity) aria-current="page" @endif>
                            <span>Alle Städte</span>
                        </a>
                    </li>
                    @foreach($cities->take(15) as $city)
                        <li>
                            <a href="{{ request()->fullUrlWithQuery(['city' => $city->name]) }}"
                               class="flex items-center justify-between px-3 py-2 rounded-lg text-sm transition-colors
                                      {{ $activeCity === $city->name ? 'sidebar-active' : 'text-base-content/70 hover:bg-base-200 hover:text-base-content' }}"
                               @if($activeCity === $city->name) aria-current="page" @endif>
                                <span>{{ $city->name }}</span>
                                @if(isset($city->companies_count))
                                    <span class="text-xs {{ $activeCity === $city->name ? 'text-white/70' : 'text-base-content/40' }}">
                                        {{ $city->companies_count }}
                                    </span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                    @if($cities->count() > 15)
                        <li class="px-3 py-1 text-xs text-base-content/40">
                            +{{ $cities->count() - 15 }} weitere Städte
                        </li>
                    @endif
                </ul>
            </nav>
        </div>
    @endif

    {{-- ═══ Premium CTA ═══ --}}
    <div class="rounded-xl border-2 p-5 text-center border-portal-accent">
        <div class="w-10 h-10 rounded-full mx-auto mb-3 flex items-center justify-center bg-portal-accent">
            <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
            </svg>
        </div>
        <h4 class="font-semibold text-base-content mb-1">Premium-Eintrag</h4>
        <p class="text-xs text-base-content/60 mb-3">Mehr Sichtbarkeit für Ihr Unternehmen</p>
        <a href="{{ route('register') }}"
           class="inline-block w-full px-4 py-2 rounded-lg text-sm font-medium text-white transition-colors hover:opacity-90 btn-portal-accent">
            Jetzt eintragen
        </a>
    </div>

    {{-- Ad: Sidebar Sticky (FA-06: scrollt mit, top-Offset berücksichtigt fixierte Nav) --}}
    <div class="sticky top-[80px]">
        <x-ad-slot position="sidebar_sticky" />
    </div>

</aside>
@endif
