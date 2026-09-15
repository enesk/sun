{{-- Brotkrumen; Eintraege aus App\Support\Breadcrumb (dieselbe Quelle wie die BreadcrumbList im JSON-LD) --}}
@props(['items' => []])
@php
    // BreadcrumbList im <head> aus genau diesen Eintraegen (#9). Die Seiteninhalte
    // rendern vor dem Layout, der Block steht also rechtzeitig fuer <x-seo.json-ld /> bereit.
    app(\App\Services\Seo\SeoService::class)->forBreadcrumbs($items);
@endphp
@if(count($items) > 1)
  <nav aria-label="{{ __('portal.layout.breadcrumb.label') }}" class="text-sm text-zinc-500 flex items-center gap-1.5 flex-wrap">
    @foreach($items as $item)
      @if($loop->last)
        <span class="text-zinc-900" aria-current="page">{{ $item['label'] }}</span>
      @elseif($loop->first)
        <a href="{{ $item['url'] }}" class="hover:text-brand hidden sm:inline">{{ $item['label'] }}</a><span class="hidden sm:inline"><x-sun.icon name="chevron-right" class="size-4 text-zinc-400 shrink-0" /></span>
      @else
        <a href="{{ $item['url'] }}" class="hover:text-brand">{{ $item['label'] }}</a><x-sun.icon name="chevron-right" class="size-4 text-zinc-400 shrink-0" />
      @endif
    @endforeach
  </nav>
@endif
