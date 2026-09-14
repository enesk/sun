{{-- Leerer Zustand der Suche: kein Treffer fuer Suchbegriff, Ort oder Filter. --}}
@php
    $popular = config('themes.sun-v2.hero.popular', []);
    $plural = config('themes.sun-v2.search.branch_plural');
@endphp
<div class="card p-6 md:p-10">
  <div class="flex flex-col sm:flex-row sm:items-start gap-5">
    <span class="size-14 shrink-0 rounded-2xl bg-brand-50 text-brand flex items-center justify-center" aria-hidden="true">
      <x-sun.icon name="search-x" class="size-7" />
    </span>
    <div class="flex-1 min-w-0">
      <h2 class="text-2xl font-semibold text-zinc-900">Keine passenden Betriebe</h2>
      <p class="mt-2 text-zinc-700 leading-relaxed">
        @if($search['hasNarrowingFilters'])
          Mit den gesetzten Filtern bleibt kein Betrieb übrig. Nimm einen Filter heraus oder such ohne Einschränkung.
        @else
          Zu deiner Suche haben wir keinen Eintrag gefunden. Vielleicht hilft ein anderer Begriff oder ein größerer Ort in der Nähe.
        @endif
      </p>
      <ul class="mt-4 space-y-2 text-zinc-700">
        <li class="flex items-start gap-2"><x-sun.icon name="check" class="icon text-brand mt-0.5" />Allgemeiner suchen, z. B. „Elektroinstallation“ statt eines Firmennamens</li>
        <li class="flex items-start gap-2"><x-sun.icon name="check" class="icon text-brand mt-0.5" />Nur den Ort oder nur die Postleitzahl eingeben</li>
        <li class="flex items-start gap-2"><x-sun.icon name="check" class="icon text-brand mt-0.5" />Schreibweise prüfen</li>
      </ul>
      <div class="mt-6 flex flex-col sm:flex-row gap-2">
        @if($search['hasNarrowingFilters'])
          <a href="{{ $search['relaxUrl'] }}" class="btn-primary">Filter zurücksetzen</a>
        @endif
        <a href="{{ $search['resetUrl'] }}" @class(['btn-secondary' => $search['hasNarrowingFilters'], 'btn-primary' => ! $search['hasNarrowingFilters']])>Alle {{ $plural }} anzeigen</a>
      </div>
    </div>
  </div>

  @if($popular !== [])
    <div class="mt-8 pt-6 border-t border-zinc-200 flex flex-wrap items-center gap-2">
      <span class="text-sm text-zinc-500 mr-1">Beliebt:</span>
      @foreach($popular as $term)
        <a href="{{ route('portal.companies.index', ['q' => $term, 'sort' => 'rating']) }}" class="pill-link">{{ $term }}</a>
      @endforeach
    </div>
  @endif
</div>

<div class="card p-5 md:p-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
  <p class="text-zinc-700">Du bist selbst Elektriker? Trag deinen Betrieb kostenlos ein.</p>
  <a href="{{ route('portal.companies.create') }}" class="btn-ghost shrink-0">Firma eintragen</a>
</div>
