{{-- Fuenf Sterne der Firmenkarte: volle Sterne amber, der Rest grau. Ab ,5 wird aufgerundet. --}}
@props(['rating' => 0, 'size' => 'size-4'])
@php
    $filled = (int) round((float) $rating);
@endphp
<span class="flex" aria-hidden="true">
    @for($i = 1; $i <= 5; $i++)
        <x-sun.icon name="star" class="{{ $size }} {{ $i <= $filled ? 'fill-amber-500 text-amber-500' : 'fill-zinc-300 text-zinc-300' }}" stroke="none" />
    @endfor
</span>
