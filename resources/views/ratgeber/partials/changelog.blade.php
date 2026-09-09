{{--
    Aenderungshinweise (#24): was seit der Erstveroeffentlichung aktualisiert
    wurde. Steht unter der Autorenbox, direkt neben dem Prüfdatum — beides
    beantwortet dieselbe Frage: wie aktuell ist das hier?

    Erwartet: $changelog — Liste aus ArticleBlockPresenter::changelog(),
    juengster Eintrag zuerst, jeder mit at (Carbon), summary und reasons.
--}}
@if(!empty($changelog))
    <section class="ratgeber-changelog" aria-labelledby="ratgeber-changelog-title">
        <h2 id="ratgeber-changelog-title" class="ratgeber-changelog__title">Aktualisierungen</h2>

        <ol class="ratgeber-changelog__list">
            @foreach($changelog as $entry)
                <li class="ratgeber-changelog__item">
                    <time class="ratgeber-changelog__date" datetime="{{ $entry['at']->toDateString() }}">
                        Aktualisiert am {{ $entry['at']->translatedFormat('j. F Y') }}
                    </time>
                    <p class="ratgeber-changelog__text">{{ $entry['summary'] }}</p>
                    @if(!empty($entry['reasons']))
                        <p class="ratgeber-changelog__reason">Anlass: {{ implode(', ', $entry['reasons']) }}</p>
                    @endif
                </li>
            @endforeach
        </ol>
    </section>
@endif
