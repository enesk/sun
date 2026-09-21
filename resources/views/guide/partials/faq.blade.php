{{--
    FAQ als <details>, erster Eintrag offen. Vollstaendig ohne JavaScript
    bedienbar; die Antworten stehen auch geschlossen im HTML (FAQPage).
    Sprungziel des Inhaltsverzeichnisses: #faq.

    Erwartet: $faq — Liste [question, answer].
--}}
@if(!empty($faq))
    <section id="faq" class="ratgeber-faq" aria-labelledby="faq-heading">
        <h2 id="faq-heading" class="ratgeber-faq__heading">Häufige Fragen</h2>
        @foreach($faq as $item)
            <details class="ratgeber-faq__item" @if($loop->first) open @endif>
                <summary class="ratgeber-faq__question">
                    <span>{{ $item['question'] }}</span>
                    <svg class="ratgeber-faq__chevron" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </summary>
                <div class="ratgeber-faq__answer">{{ $item['answer'] }}</div>
            </details>
        @endforeach
    </section>
@endif
