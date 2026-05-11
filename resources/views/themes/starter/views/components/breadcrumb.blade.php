{{-- Breadcrumb Navigation --}}
{{-- Usage: @include('components.breadcrumb', ['items' => [['label' => 'Home', 'url' => '/'], ['label' => 'Firmen']]]) --}}
@if(!empty($items) && count($items) > 1)
<nav aria-label="Breadcrumb" class="container mx-auto px-4 py-3">
    <ol class="flex flex-wrap items-center gap-1.5 text-sm text-base-content/60" itemscope itemtype="https://schema.org/BreadcrumbList">
        @foreach($items as $i => $item)
            <li class="flex items-center gap-1.5" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
                @if(!$loop->last && isset($item['url']))
                    <a href="{{ $item['url'] }}" itemprop="item"
                       class="hover:text-base-content transition-colors hover:underline">
                        <span itemprop="name">{{ $item['label'] }}</span>
                    </a>
                    <meta itemprop="position" content="{{ $i + 1 }}">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                @else
                    <span itemprop="name" class="text-base-content font-medium" aria-current="page">{{ $item['label'] }}</span>
                    <meta itemprop="item" content="{{ url()->current() }}">
                    <meta itemprop="position" content="{{ $i + 1 }}">
                @endif
            </li>
        @endforeach
    </ol>
</nav>
@endif
