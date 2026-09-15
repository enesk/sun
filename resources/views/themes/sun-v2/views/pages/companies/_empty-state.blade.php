{{-- Leerer Zustand der Suche: kein Treffer fuer Suchbegriff, Ort oder Filter. --}}
@php
    $popular = config('themes.sun-v2.hero.popular', []);
@endphp
<div class="card p-6 md:p-10">
  <div class="flex flex-col sm:flex-row sm:items-start gap-5">
    <span class="size-14 shrink-0 rounded-2xl bg-brand-50 text-brand flex items-center justify-center" aria-hidden="true">
      <x-sun.icon name="search-x" class="size-7" />
    </span>
    <div class="flex-1 min-w-0">
      <h2 class="text-2xl font-semibold text-zinc-900">{{ __('portal.empty.search.heading') }}</h2>
      <p class="mt-2 text-zinc-700 leading-relaxed">
        @if($search['hasNarrowingFilters'])
          {{ __('portal.empty.search.text_filtered') }}
        @else
          {{ __('portal.empty.search.text') }}
        @endif
      </p>
      <ul class="mt-4 space-y-2 text-zinc-700">
        <li class="flex items-start gap-2"><x-sun.icon name="check" class="icon text-brand mt-0.5" />{{ __('portal.empty.search.tip_general') }}</li>
        <li class="flex items-start gap-2"><x-sun.icon name="check" class="icon text-brand mt-0.5" />{{ __('portal.empty.search.tip_place') }}</li>
        <li class="flex items-start gap-2"><x-sun.icon name="check" class="icon text-brand mt-0.5" />{{ __('portal.empty.search.tip_spelling') }}</li>
      </ul>
      <div class="mt-6 flex flex-col sm:flex-row gap-2">
        @if($search['hasNarrowingFilters'])
          <a href="{{ $search['relaxUrl'] }}" class="btn-primary">{{ __('portal.layout.filters.reset') }}</a>
        @endif
        <a href="{{ $search['resetUrl'] }}" @class(['btn-secondary' => $search['hasNarrowingFilters'], 'btn-primary' => ! $search['hasNarrowingFilters']])>{{ __('portal.empty.search.show_all') }}</a>
      </div>
    </div>
  </div>

  @if($popular !== [])
    <div class="mt-8 pt-6 border-t border-zinc-200 flex flex-wrap items-center gap-2">
      <span class="text-sm text-zinc-500 mr-1">{{ __('portal.layout.search_form.popular') }}</span>
      @foreach($popular as $term)
        <a href="{{ route('portal.companies.index', ['q' => $term, 'sort' => 'rating']) }}" class="pill-link">{{ $term }}</a>
      @endforeach
    </div>
  @endif
</div>

<div class="card p-5 md:p-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
  <p class="text-zinc-700">{{ __('portal.empty.search.signup_text') }}</p>
  <a href="{{ route('portal.companies.create') }}" class="btn-ghost shrink-0">{{ __('portal.empty.search.signup_button') }}</a>
</div>
