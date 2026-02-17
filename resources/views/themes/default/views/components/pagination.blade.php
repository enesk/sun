{{-- Pagination Component --}}
{{-- Usage: @include('components.pagination', ['paginator' => $companies]) --}}
@if($paginator->hasPages())
    <nav role="navigation" aria-label="Seitennavigation" class="flex items-center justify-between">
        {{-- Mobile: simple prev/next --}}
        <div class="flex justify-between flex-1 sm:hidden">
            @if($paginator->onFirstPage())
                <span class="px-4 py-2 text-sm font-medium text-base-content/30 bg-base-100 border border-base-200 rounded-lg cursor-not-allowed">
                    Zurück
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="px-4 py-2 text-sm font-medium text-base-content bg-base-100 border border-base-200 rounded-lg hover:bg-base-200 transition-colors">
                    Zurück
                </a>
            @endif

            <span class="px-3 py-2 text-sm text-base-content/60">
                Seite {{ $paginator->currentPage() }} von {{ $paginator->lastPage() }}
            </span>

            @if($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="px-4 py-2 text-sm font-medium text-base-content bg-base-100 border border-base-200 rounded-lg hover:bg-base-200 transition-colors">
                    Weiter
                </a>
            @else
                <span class="px-4 py-2 text-sm font-medium text-base-content/30 bg-base-100 border border-base-200 rounded-lg cursor-not-allowed">
                    Weiter
                </span>
            @endif
        </div>

        {{-- Desktop: full pagination --}}
        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
            <p class="text-sm text-base-content/60">
                Zeige
                <span class="font-medium text-base-content">{{ $paginator->firstItem() }}</span>
                bis
                <span class="font-medium text-base-content">{{ $paginator->lastItem() }}</span>
                von
                <span class="font-medium text-base-content">{{ $paginator->total() }}</span>
                Ergebnissen
            </p>

            <div class="flex items-center gap-1">
                {{-- Previous --}}
                @if($paginator->onFirstPage())
                    <span class="p-2 text-base-content/30 cursor-not-allowed" aria-disabled="true">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" class="p-2 rounded-lg text-base-content/60 hover:bg-base-200 transition-colors" aria-label="Vorherige Seite">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </a>
                @endif

                {{-- Page Numbers --}}
                @foreach($paginator->getUrlRange(1, $paginator->lastPage()) as $page => $url)
                    @if($page == $paginator->currentPage())
                        <span class="px-3 py-1.5 rounded-lg text-sm font-medium text-white bg-portal-primary" aria-current="page">
                            {{ $page }}
                        </span>
                    @elseif($page == 1 || $page == $paginator->lastPage() || abs($page - $paginator->currentPage()) <= 1)
                        <a href="{{ $url }}" class="px-3 py-1.5 rounded-lg text-sm text-base-content/70 hover:bg-base-200 transition-colors">
                            {{ $page }}
                        </a>
                    @elseif(abs($page - $paginator->currentPage()) == 2)
                        <span class="px-1 text-base-content/30">...</span>
                    @endif
                @endforeach

                {{-- Next --}}
                @if($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" class="p-2 rounded-lg text-base-content/60 hover:bg-base-200 transition-colors" aria-label="Nächste Seite">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                @else
                    <span class="p-2 text-base-content/30 cursor-not-allowed" aria-disabled="true">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </span>
                @endif
            </div>
        </div>
    </nav>
@endif
