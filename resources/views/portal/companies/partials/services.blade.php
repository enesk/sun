{{--
    Profil-Sektion "Leistungen & Preise" (#14), Leistungskatalog aus #13.
    $services: CompanyController::$profileServices, bereits nur mit Feature service_catalog befuellt.
    Mobil Karten, ab md Tabelle. Gestaltet fuer das Theme sun-v2.
--}}
@php
    $priceLabel = fn ($service) => $service->price_from_display
        ? __('portal.profile.catalog.price_from', ['preis' => $service->price_from_display])
        : __('portal.profile.catalog.price_on_request');
@endphp
<section class="card p-5 md:p-8" aria-labelledby="leistungen-preise">
  <h2 id="leistungen-preise" class="text-2xl font-semibold text-zinc-900">{{ __('portal.profile.catalog.heading') }}</h2>

  {{-- Mobil: Karten --}}
  <ul class="mt-4 flex flex-col gap-3 md:hidden">
    @foreach($services as $service)
      <li class="rounded-xl border border-zinc-200 p-4">
        <div class="flex items-start justify-between gap-3">
          <p class="font-semibold text-zinc-900 break-words">{{ $service->name }}</p>
          <p class="shrink-0 font-semibold text-zinc-900 tabular-nums whitespace-nowrap">{{ $priceLabel($service) }}</p>
        </div>
        @if($service->description)
          <p class="mt-1 text-sm leading-relaxed text-zinc-500">{{ $service->description }}</p>
        @endif
      </li>
    @endforeach
  </ul>

  {{-- Ab md: Tabelle --}}
  <table class="mt-4 hidden w-full text-left md:table">
    <thead>
      <tr class="border-b border-zinc-200 text-sm text-zinc-500">
        <th scope="col" class="py-2 pr-6 font-medium">{{ __('portal.profile.catalog.service') }}</th>
        <th scope="col" class="py-2 text-right font-medium">{{ __('portal.profile.catalog.price') }}</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-zinc-100">
      @foreach($services as $service)
        <tr class="align-top">
          <td class="py-3 pr-6">
            <span class="block font-medium text-zinc-900">{{ $service->name }}</span>
            @if($service->description)
              <span class="mt-0.5 block text-sm leading-relaxed text-zinc-500">{{ $service->description }}</span>
            @endif
          </td>
          <td class="py-3 text-right font-semibold text-zinc-900 tabular-nums whitespace-nowrap">{{ $priceLabel($service) }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>

  <p class="mt-4 text-sm text-zinc-500">{{ __('portal.profile.catalog.note') }}</p>
</section>
