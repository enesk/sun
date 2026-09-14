{{-- Betriebskarte der Uebernahme-Seite (Bild, Name, Adresse, Sterne, Uebernahme-Status). --}}
@props(['company'])
<div class="flex items-start gap-4 p-4 rounded-2xl bg-zinc-50 border border-zinc-200">
  <img src="{{ $company->card_image_url ?: \App\Themes\SunV2\Asset::url('images/placeholder-company.svg') }}" alt="" width="80" height="80" class="size-16 md:size-20 rounded-2xl object-cover shrink-0">
  <div class="min-w-0">
    <p class="font-semibold text-zinc-900 leading-snug">{{ $company->name }}</p>
    @if($company->full_address)
      <p class="mt-1 text-sm text-zinc-500 flex items-start gap-1.5"><span class="mt-0.5"><x-sun.icon name="map-pin" class="size-4 shrink-0" stroke-linecap="butt" stroke-linejoin="miter" /></span><span>{{ $company->full_address }}</span></p>
    @endif
    @if($company->rating_count > 0)
      <div class="mt-1.5 flex items-center gap-1.5"><x-sun.stars :rating="$company->rating" /><span class="text-sm font-medium text-zinc-700">{{ number_format((float) $company->rating, 1, ',', '') }}</span><span class="text-sm text-zinc-500">({{ $company->rating_count }})</span></div>
    @endif
    <span class="mt-2 pill inline-flex">{{ $company->user_id ? 'Bereits übernommen' : 'Noch nicht übernommen' }}</span>
  </div>
</div>
