{{--
    Verweis zur Firmensuche im Theme sun-v2.
    'bar': Verweisleiste nach dem vorletzten Abschnitt, sekundaere Schaltflaeche.
    'box': CTA-Box unter dem Artikel, einziger Primaer-Button der Seite.
    Erwartet: $cta — [text, label, url]; $variant.
--}}
@if(($variant ?? 'box') === 'bar')
  <aside class="mt-8 rounded-2xl bg-brand-50 p-5 flex flex-col sm:flex-row sm:items-center gap-4" aria-label="Firmensuche">
    <p class="flex-1 text-zinc-900 font-medium">{{ $cta['text'] }}</p>
    <a href="{{ $cta['url'] }}" class="btn-secondary shrink-0">{{ $cta['label'] }}</a>
  </aside>
@else
  <aside class="card p-5 md:p-8 mt-8 bg-brand-50 border-brand-100" aria-labelledby="ratgeber-cta-title">
    <h2 id="ratgeber-cta-title" class="text-2xl font-semibold text-zinc-900">Den passenden Betrieb finden</h2>
    <p class="mt-2 text-zinc-700 leading-relaxed">Vergleiche Betriebe aus deiner Region und hol dir ein Angebot für dein Vorhaben.</p>
    <a href="{{ $cta['url'] }}" class="btn-primary mt-4">{{ $cta['label'] }}</a>
  </aside>
@endif
