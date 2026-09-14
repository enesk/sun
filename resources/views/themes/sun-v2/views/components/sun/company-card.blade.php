{{-- Firmenkarte der Vorlage ("Top bewertet"). Premium-Betriebe bekommen Markenleiste links und Pille. --}}
@props(['company'])
@php
    $status = \App\Themes\SunV2\OpeningStatus::for($company);
    $rating = number_format((float) $company->rating, 1, ',', '');
    $imageUrl = $company->card_image_url ?: \App\Themes\SunV2\Asset::url('images/placeholder-company.svg');
@endphp
<article class="relative card-interactive overflow-hidden flex flex-col min-w-[85%] sm:min-w-[60%] md:min-w-[45%] xl:min-w-0 @if($company->is_premium) border-l-4 border-l-brand @endif">
    <img src="{{ $imageUrl }}" alt="Betriebsfoto {{ $company->name }}" width="640" height="360" class="aspect-video w-full object-cover" loading="lazy">
    <div class="p-5 flex flex-col gap-3 flex-1">
        <div class="flex items-start gap-3">
            <div class="min-w-0 flex-1">
                @if($company->is_premium)
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <h3 class="text-lg font-semibold text-zinc-900 leading-snug"><a href="{{ $company->portal_url }}" class="hover:text-brand">{{ $company->name }}</a></h3>
                        <span class="pill-brand shrink-0">Premium</span>
                    </div>
                @else
                    <h3 class="text-lg font-semibold text-zinc-900 leading-snug"><a href="{{ $company->portal_url }}" class="hover:text-brand">{{ $company->name }}</a></h3>
                @endif
                @if($company->rating_count > 0)
                    <div class="flex items-center gap-1.5 mt-1">
                        <x-sun.stars :rating="$company->rating" />
                        <span class="text-sm font-medium text-zinc-700">{{ $rating }}</span><span class="text-sm text-zinc-500">({{ $company->rating_count }})</span>
                        <span class="sr-only">{{ $rating }} von 5 Sternen bei {{ $company->rating_count }} Bewertungen</span>
                    </div>
                @endif
            </div>
        </div>
        @if($company->full_address)
            <p class="text-sm text-zinc-500 flex items-center gap-1"><x-sun.icon name="map-pin" class="size-4 shrink-0" stroke-linecap="butt" stroke-linejoin="miter" />{{ $company->full_address }}</p>
        @endif
        @if($company->categories->isNotEmpty())
            <div class="flex flex-wrap gap-2">
                @foreach($company->categories->take(4) as $category)
                    <span class="pill">{{ $category->name }}</span>
                @endforeach
            </div>
        @endif
        @if($status)
            <p class="text-sm flex items-center gap-2">
                <span class="size-2 rounded-full {{ $status->open ? 'bg-emerald-500' : 'bg-zinc-400' }}"></span>
                <span class="{{ $status->open ? 'text-emerald-700' : 'text-zinc-500' }}">{{ $status->label }}</span>
            </p>
        @endif
        <div class="flex gap-2 mt-auto pt-1">
            <x-phone-link :number="$company->tel" class="btn-primary flex-1" fallback-class="flex-1 self-center text-sm text-zinc-700"><x-sun.icon name="phone" class="icon" />Anrufen</x-phone-link>
            <a href="{{ $company->portal_url }}" class="btn-secondary flex-1">Profil</a>
        </div>
    </div>
</article>
