{{-- Abschnittskopf der Vorlage: Ueberschrift links, "Alle ..."-Link rechts --}}
@props(['title', 'href' => null, 'link' => null])
<div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-2 sm:gap-4 mb-6">
    <h2 class="text-2xl font-semibold text-zinc-900">{{ $title }}</h2>
    @if($href && $link)
        <a href="{{ $href }}" class="text-brand font-medium hover:underline shrink-0">{{ $link }}</a>
    @endif
</div>
