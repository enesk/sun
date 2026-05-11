{{-- Search Bar Component --}}
{{-- Usage: @include('components.search-bar', ['action' => '/firmen', 'showFilters' => true]) --}}
<div class="bg-base-100 rounded-xl shadow-sm border border-base-200 p-4">
    <form action="{{ $action ?? route('portal.companies.index') }}" method="GET" role="search">
        <div class="flex flex-col sm:flex-row gap-3">
            {{-- Search Input --}}
            <div class="flex-1 relative">
                <label for="search-input" class="sr-only">Suchbegriff</label>
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-base-content/40 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="search" name="q" id="search-input"
                       value="{{ request('q') }}"
                       placeholder="Firma oder Stichwort suchen..."
                       class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-base-300 bg-base-100 text-base-content placeholder:text-base-content/40 focus:outline-none focus:ring-2 focus:border-transparent transition-shadow ring-portal"
                       autocomplete="off">
            </div>

            {{-- Category Filter --}}
            @if($showFilters ?? false)
                <div class="sm:w-48">
                    <label for="category-filter" class="sr-only">Kategorie</label>
                    <select name="category" id="category-filter"
                            class="w-full py-2.5 px-3 rounded-lg border border-base-300 bg-base-100 text-base-content text-sm focus:outline-none focus:ring-2 focus:border-transparent transition-shadow ring-portal">
                        <option value="">Alle Kategorien</option>
                        @foreach($categories ?? [] as $cat)
                            <option value="{{ $cat->slug }}" {{ request('category') === $cat->slug ? 'selected' : '' }}>
                                {{ $cat->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- City Filter --}}
                <div class="sm:w-40">
                    <label for="city-filter" class="sr-only">Stadt</label>
                    <select name="city" id="city-filter"
                            class="w-full py-2.5 px-3 rounded-lg border border-base-300 bg-base-100 text-base-content text-sm focus:outline-none focus:ring-2 focus:border-transparent transition-shadow ring-portal">
                        <option value="">Alle St&auml;dte</option>
                        @foreach($cities ?? [] as $city)
                            <option value="{{ $city->slug }}" {{ request('city') === $city->slug ? 'selected' : '' }}>
                                {{ $city->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            {{-- Submit --}}
            <button type="submit"
                    class="btn-portal px-5 py-2.5 rounded-lg text-sm transition-opacity hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2 shrink-0">
                Suchen
            </button>
        </div>

        {{-- Active Filters --}}
        @if(request('q') || request('category') || request('city'))
            <div class="flex flex-wrap items-center gap-2 mt-3 pt-3 border-t border-base-200">
                <span class="text-xs text-base-content/50">Filter:</span>
                @if(request('q'))
                    <a href="{{ request()->fullUrlWithoutQuery('q') }}"
                       class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs bg-base-200 text-base-content/70 hover:bg-base-300 transition-colors"
                       aria-label="Suchfilter '{{ request('q') }}' entfernen">
                        &ldquo;{{ request('q') }}&rdquo;
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </a>
                @endif
                @if(request('category'))
                    <a href="{{ request()->fullUrlWithoutQuery('category') }}"
                       class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs bg-base-200 text-base-content/70 hover:bg-base-300 transition-colors"
                       aria-label="Kategoriefilter entfernen">
                        {{ request('category') }}
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </a>
                @endif
                @if(request('city'))
                    <a href="{{ request()->fullUrlWithoutQuery('city') }}"
                       class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs bg-base-200 text-base-content/70 hover:bg-base-300 transition-colors"
                       aria-label="Stadtfilter entfernen">
                        {{ request('city') }}
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </a>
                @endif
            </div>
        @endif
    </form>
</div>
