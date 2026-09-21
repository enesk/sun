<li>
  @if(!empty($source['publisher']))
    <span class="font-medium text-zinc-700">{{ $source['publisher'] }}:</span>
  @endif
  @if(!empty($source['url']))
    <a href="{{ $source['url'] }}" rel="nofollow noopener" target="_blank" class="underline hover:text-brand">{{ $source['title'] }}<span class="sr-only"> (öffnet in neuem Tab)</span></a><x-sun.icon name="external" class="inline size-3.5 ml-0.5 align-baseline text-zinc-400" />
  @else
    {{ $source['title'] }}
  @endif
  @if(!empty($source['stale_year']))
    <span class="text-zinc-400">Stand {{ $source['stale_year'] }}</span>
  @endif
</li>
