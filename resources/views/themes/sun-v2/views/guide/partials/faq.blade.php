{{--
    FAQ im Theme sun-v2 als <details>, erster Eintrag offen. Die Antworten stehen
    auch geschlossen im HTML (FAQPage). Sprungziel des Inhaltsverzeichnisses: #faq.
    Erwartet: $faq — Liste [question, answer].
--}}
@if(!empty($faq))
  <section id="faq" class="mt-10 scroll-mt-24" aria-labelledby="faq-heading">
    <h2 id="faq-heading" class="text-2xl font-semibold text-zinc-900">Häufige Fragen</h2>
    <div class="mt-3 divide-y divide-zinc-200 border-y border-zinc-200">
      @foreach($faq as $item)
        <details class="group py-4" @if($loop->first) open @endif>
          <summary class="flex items-center justify-between gap-4 cursor-pointer list-none font-medium text-zinc-900 min-h-11"><span>{{ $item['question'] }}</span><x-sun.icon name="chevron-down" class="size-5 shrink-0 text-zinc-400 transition-transform duration-200 group-open:rotate-180" /></summary>
          <p class="mt-3 text-zinc-700 leading-relaxed">{{ $item['answer'] }}</p>
        </details>
      @endforeach
    </div>
  </section>
@endif
