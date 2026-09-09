{{--
    CTA-Box zur Firmensuche. Steht nach der Quellenzeile, damit sie nicht mit
    dem Regionalblock im Mittelteil konkurriert.
    Erwartet: $ctaUrl, $ctaLabel.
--}}
<aside class="ratgeber-cta">
    <h2 class="ratgeber-cta__title">Den passenden Betrieb finden</h2>
    <p class="ratgeber-cta__text">
        Vergleichen Sie Anbieter aus Ihrer Region und holen Sie sich ein Angebot für Ihr Vorhaben.
    </p>
    <a href="{{ $ctaUrl }}" class="ratgeber-cta__btn">{{ $ctaLabel }}</a>
</aside>
