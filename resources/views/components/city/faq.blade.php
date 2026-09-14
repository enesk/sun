{{--
    FAQ des Local Hubs unterhalb der Firmenliste (#12), natives <details> ohne JavaScript.
    Daten: CityContentResolver::forCity()['faqs']. Dieselbe Liste geht unveraendert in
    StructuredDataService::forCityFaq() — Frage und Antwort hier nicht umformulieren oder kuerzen.
    Antworten sind Klartext, Absaetze durch Leerzeile. Keine Werbeplaetze innerhalb dieser Komponente.
    Gestaltet fuer das Theme sun-v2 (Klassen .card, x-sun.icon).
--}}
@props(['city', 'faqs' => []])

@if($faqs !== [])
  <section {{ $attributes->merge(['class' => 'mt-12 md:mt-16 max-w-3xl']) }}>
    <h2 class="text-2xl font-semibold text-zinc-900">Häufige Fragen zu {{ app(\App\Services\Seo\CityMetaTemplates::class)->tradePlural() }} in {{ $city->name }}</h2>
    <div class="card mt-4 divide-y divide-zinc-200">
      @foreach($faqs as $faq)
        <details class="group">
          <summary class="flex items-start justify-between gap-4 p-5 md:px-6 cursor-pointer list-none min-h-11 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand rounded-2xl">
            <h3 class="font-semibold text-zinc-900 leading-snug">{{ $faq['question'] }}</h3>
            <x-sun.icon name="chevron-down" class="size-5 text-zinc-400 shrink-0 mt-0.5 transition-transform duration-150 group-open:rotate-180" />
          </summary>
          <div class="px-5 md:px-6 pb-5 -mt-1 text-base leading-relaxed text-zinc-700">
            @foreach(preg_split('/\n{2,}/u', $faq['answer']) as $paragraph)
              <p @class(['mt-3' => ! $loop->first])>{!! nl2br(e($paragraph), false) !!}</p>
            @endforeach
          </div>
        </details>
      @endforeach
    </div>
  </section>
@endif
