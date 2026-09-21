{{--
    Verweis zur Firmensuche.
    variant 'bar': Verweisleiste nach dem vorletzten Abschnitt (Block 7),
    sekundaere Schaltflaeche — ohne Anzeige davor oder danach.
    variant 'box': CTA-Box (Block 10), einziger primaerer Aufruf im Artikel.

    Erwartet: $cta — [text, label, url]; $variant.
--}}
@if(($variant ?? 'box') === 'bar')
    <aside class="ratgeber-cta-bar" aria-label="Firmensuche">
        <p class="ratgeber-cta-bar__text">{{ $cta['text'] }}</p>
        <a href="{{ $cta['url'] }}" class="ratgeber-cta-bar__btn">{{ $cta['label'] }}</a>
    </aside>
@else
    <aside class="ratgeber-cta" aria-labelledby="ratgeber-cta-title">
        <h2 id="ratgeber-cta-title" class="ratgeber-cta__title">Den passenden Betrieb finden</h2>
        <p class="ratgeber-cta__text">
            Vergleichen Sie Anbieter aus Ihrer Region und holen Sie sich ein Angebot für Ihr Vorhaben.
        </p>
        <a href="{{ $cta['url'] }}" class="ratgeber-cta__btn">{{ $cta['label'] }}</a>
    </aside>
@endif
