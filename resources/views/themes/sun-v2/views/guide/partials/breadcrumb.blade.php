{{--
    Brotkrume der Ratgeber-Seiten im Theme sun-v2, Optik wie <x-sun.breadcrumb>.
    Bewusst ohne diese Komponente: sie meldet die Liste an den SeoService, die
    BreadcrumbList steht beim Ratgeber aber schon im JSON-LD (GuideStructuredData).
    Erwartet: $items — Liste [label, url].
--}}
@if(count($items) > 1)
  <nav aria-label="{{ __('portal.layout.breadcrumb.label') }}" class="text-sm text-zinc-500 flex items-center gap-1.5 flex-wrap">
    @foreach($items as $crumb)
      @if($loop->last)
        <span class="text-zinc-900" aria-current="page">{{ \Illuminate\Support\Str::limit($crumb['label'], 60) }}</span>
      @elseif($loop->first)
        <a href="{{ $crumb['url'] }}" class="hover:text-brand hidden sm:inline">{{ $crumb['label'] }}</a><span class="hidden sm:inline"><x-sun.icon name="chevron-right" class="size-4 text-zinc-400 shrink-0" /></span>
      @else
        <a href="{{ $crumb['url'] }}" class="hover:text-brand">{{ $crumb['label'] }}</a><x-sun.icon name="chevron-right" class="size-4 text-zinc-400 shrink-0" />
      @endif
    @endforeach
  </nav>
@endif
