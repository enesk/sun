{{-- Star Rating Display Component --}}
{{-- Usage: @include('components.star-rating', ['rating' => 4.5, 'size' => 'sm']) --}}
{{-- Sizes: 'xs', 'sm', 'md', 'lg' --}}
@php
    $rating = floatval($rating ?? 0);
    $maxStars = 5;
    $fullStars = floor($rating);
    $halfStar = ($rating - $fullStars) >= 0.25 && ($rating - $fullStars) < 0.75;
    $fullIfRoundUp = ($rating - $fullStars) >= 0.75;
    if ($fullIfRoundUp) { $fullStars++; $halfStar = false; }
    $emptyStars = $maxStars - $fullStars - ($halfStar ? 1 : 0);

    $sizes = [
        'xs' => 'w-3 h-3',
        'sm' => 'w-4 h-4',
        'md' => 'w-5 h-5',
        'lg' => 'w-6 h-6',
    ];
    $starSize = $sizes[$size ?? 'sm'] ?? $sizes['sm'];
@endphp

<div class="inline-flex items-center gap-0.5" role="img" aria-label="Bewertung: {{ number_format($rating, 1) }} von {{ $maxStars }} Sternen">
    {{-- Full Stars --}}
    @for($i = 0; $i < $fullStars; $i++)
        <svg class="{{ $starSize }} shrink-0 text-portal-accent" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
        </svg>
    @endfor

    {{-- Half Star --}}
    @if($halfStar)
        <svg class="{{ $starSize }} shrink-0" viewBox="0 0 20 20" aria-hidden="true">
            <defs>
                <linearGradient id="half-star-{{ md5(json_encode([$rating, $size ?? 'sm'])) }}">
                    <stop offset="50%" class="stop-portal-accent"/>
                    <stop offset="50%" stop-color="#D1D5DB"/>
                </linearGradient>
            </defs>
            <path fill="url(#half-star-{{ md5(json_encode([$rating, $size ?? 'sm'])) }})" d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
        </svg>
    @endif

    {{-- Empty Stars --}}
    @for($i = 0; $i < $emptyStars; $i++)
        <svg class="{{ $starSize }} shrink-0 text-gray-300" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
        </svg>
    @endfor

    {{-- Numeric display (optional) --}}
    @if($showNumeric ?? false)
        <span class="ml-1 text-sm font-medium text-base-content">{{ number_format($rating, 1) }}</span>
    @endif
</div>
