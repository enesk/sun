{{--
    „Passende Ratgeber“ (#18) im Theme sun-v2. Daten wie die Basisfassung
    (resources/views/components/guide/related.blade.php): App\View\Components\Guide\Related.
--}}
<section {{ $attributes->merge(['class' => 'mt-12 md:mt-16']) }}>
  <x-sun.section-heading title="Passende Ratgeber" :href="route('guide.index')" link="Alle Ratgeber" />
  <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
    @foreach($topics as $card)
      <a href="{{ $card['url'] }}" class="card-interactive p-5 flex flex-col gap-2">
        <h3 class="text-lg font-semibold text-zinc-900 leading-snug">{{ $card['title'] }}</h3>
        @if($card['teaser'] !== '')
          <p class="text-sm text-zinc-500 line-clamp-3">{{ $card['teaser'] }}</p>
        @endif
        @if($card['date'])
          <span class="text-sm text-zinc-500 mt-auto pt-1">{{ $card['date_label'] }} <time datetime="{{ $card['date']->copy()->timezone(\App\Guide\Services\GuidePageData::DISPLAY_TIMEZONE)->toDateString() }}">{{ $card['date']->copy()->timezone(\App\Guide\Services\GuidePageData::DISPLAY_TIMEZONE)->format('d.m.Y') }}</time></span>
        @endif
      </a>
    @endforeach
  </div>
</section>
