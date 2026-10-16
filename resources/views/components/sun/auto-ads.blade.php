{{--
    AdSense Auto Ads (Ticket #18) — Markup zu
    App\View\Components\Sun\AutoAds.

    Kein Container und keine Hoehenreservierung: Auto Ads platzieren selbst,
    ausserhalb jeder Reservierung (docs/ad-slots.md 1). Gerendert wird nur,
    wenn `ads_enabled` und `adsense_auto_ads` des Portals stehen, eine
    Publisher-ID wirksam ist und die Einwilligung vorliegt — die Komponente
    rendert sonst gar nicht (shouldRender()).

    Der Loader fehlt hier, wenn ihn ein Anzeigenblock der Seite schon in den
    `head`-Stack geschrieben hat; das <meta> steht trotzdem, es schaltet die
    seitenweiten Auto Ads in resources/js/ads.js ein.
--}}
@if($loader !== null)
{!! $scriptTag() !!}
@endif
<meta name="adsense-auto-client" content="{{ $publisherId }}">
