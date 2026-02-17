{{-- Empty State Component --}}
{{-- Usage: @include('components.empty-state', ['title' => '...', 'message' => '...', 'icon' => 'search|folder|star|users', 'action' => ['url' => '/', 'label' => 'Zurück']]) --}}
@php
    $icon = $icon ?? 'folder';
    $title = $title ?? 'Keine Einträge gefunden';
    $message = $message ?? 'Es wurden keine passenden Ergebnisse gefunden.';
    $action = $action ?? null;

    $icons = [
        'search' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>',
        'folder' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>',
        'star' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>',
        'users' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>',
    ];
    $svgPath = $icons[$icon] ?? $icons['folder'];
@endphp

<div class="flex flex-col items-center justify-center py-16 px-4 text-center" role="status">
    {{-- Icon --}}
    <div class="w-20 h-20 rounded-full bg-base-200 flex items-center justify-center mb-6">
        <svg class="w-10 h-10 text-base-content/30" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            {!! $svgPath !!}
        </svg>
    </div>

    {{-- Title --}}
    <h3 class="text-lg font-semibold text-base-content mb-2">{{ $title }}</h3>

    {{-- Message --}}
    <p class="text-sm text-base-content/60 max-w-md mb-6">{{ $message }}</p>

    {{-- CTA Button (optional) --}}
    @if($action)
        <a href="{{ $action['url'] }}"
           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg text-sm font-medium text-white transition-colors hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2 btn-portal ring-portal"
            @if(isset($action['icon']) && $action['icon'] === 'back')
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            @endif
            {{ $action['label'] }}
        </a>
    @endif
</div>
