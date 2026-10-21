{{-- Messkennungen und der GA4-Block — die einzige Stelle, die ga-id/gtm-id und
     gtag.js ausgibt (#14, Consent Mode v2 Advanced).

     Aufgeloest wird in App\Analytics\AnalyticsResolver (Rangfolge Tenant ->
     global -> aus). Ohne Kennung steht hier nichts: kein Meta, kein Skript.

     GA4 (Consent Mode v2, Advanced)
     -------------------------------
     Die Reihenfolge im <head> ist entscheidend und darum fest hier verdrahtet
     statt in einem Modul:

       1. Inline: gtag('consent','default', … alles 'denied') + url_passthrough
       2. gtag.js (async) — laedt IMMER, auch ohne Einwilligung
       3. Inline: gtag('js') und gtag('config', <Property-ID>)

     Laeuft gtag.js vor dem Default-Block, geht der erste Hit mit Cookies raus.
     Darum zwei Inline-Bloecke und kein Vite-Modul — ein Modul haengt am
     Bundle und damit an einer Ladereihenfolge, die wir hier nicht garantieren
     koennen. Beide Bloecke tragen das Nonce des Requests
     (App\Services\Security\CspNonce), damit die Policy ohne 'unsafe-inline'
     auskommt (#21).

     Die Default-Werte kommen aus der gespeicherten Entscheidung
     (App\Support\ConsentState, #4): ohne Cookie und nach Ablehnung alles
     'denied', nach Zustimmung die zugestimmten Kategorien direkt auf
     'granted'. Ohne das lief beim Folgeaufruf jeder Seitenaufruf bis zum
     'consent update' aus dem Bundle cookielos, obwohl zugestimmt wurde.

     Gecachtes HTML: direkt hinter dem Default laeuft ein kurzer Abgleich mit
     document.cookie. Stimmen die Cookies dieses Browsers nicht mit der
     gerenderten Vorgabe ueberein — die Antwort kam aus einem Full-Page- oder
     Kanten-Cache und wurde fuer einen anderen Besucher gebaut —, zieht ein
     'consent update' den Zustand noch VOR gtag.js richtig. Damit ist die
     Ausgabe je Browser korrekt, egal wer die Seite in den Cache gelegt hat.

     Das 'consent update' nach einer Entscheidung im laufenden Besuch feuert
     weiter der Cookie-Hinweis (resources/js/analytics.js, #3).

     GTM bleibt unveraendert in resources/js/analytics.js und laedt weiter erst
     nach der Statistik-Einwilligung.

     $defer: Millisekunden nach 'load', bis gtm.js nachgeladen wird. Ohne
     Angabe laedt es sofort; gtag.js ist davon nicht betroffen. --}}
@props(['defer' => null, 'tenant' => null])

@php
    $analytics = app(\App\Analytics\AnalyticsResolver::class)->resolve($tenant);
    $cspNonce = app(\App\Services\Security\CspNonce::class)->value();
    $consentDefault = \App\Support\ConsentState::fromRequest(request())->signals();
@endphp

@if ($analytics->gaId !== null)
    <meta name="ga-id" content="{{ $analytics->gaId }}">
    <script nonce="{{ $cspNonce }}">
        window.dataLayer = window.dataLayer || [];
        function gtag(){ dataLayer.push(arguments); }
        var consentVorgabe = @json($consentDefault);

        gtag('consent', 'default', Object.assign({ wait_for_update: 500 }, consentVorgabe));

        // Abgleich fuer gecachtes HTML: die Vorgabe oben ist die Entscheidung
        // des Besuchers, FUER DEN diese Antwort gebaut wurde. Kommt sie aus
        // einem Cache, kann sie zu diesem Browser nicht passen — dann zieht
        // ein Update den Zustand noch vor gtag.js richtig (in beide
        // Richtungen). Ohne Entscheidung bleibt es beim Default 'denied'.
        (function () {
            function cookie(name) {
                var treffer = document.cookie.match(new RegExp('(?:^|;\\s*)' + name + '=([^;]*)'));

                return treffer ? treffer[1] : null;
            }

            if (cookie('cookie_consent_given') !== '1') {
                return;
            }

            var statistik = cookie('cookie_consent_statistics') === '1';
            var marketing = cookie('cookie_consent_marketing') === '1';
            var ja = 'granted';
            var nein = 'denied';
            // Gleiches Mapping wie App\Support\ConsentState und
            // resources/js/analytics.js (erlaubnisse()).
            var jetzt = {
                analytics_storage: statistik ? ja : nein,
                ad_storage: marketing ? ja : nein,
                ad_user_data: marketing ? ja : nein,
                ad_personalization: marketing ? ja : nein,
                functionality_storage: (statistik || marketing) ? ja : nein,
                security_storage: ja
            };

            var abweichung = Object.keys(jetzt).some(function (signal) {
                return jetzt[signal] !== consentVorgabe[signal];
            });

            if (abweichung) {
                gtag('consent', 'update', jetzt);
            }
        })();

        gtag('set', 'url_passthrough', true);
    </script>
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ urlencode($analytics->gaId) }}"></script>
    <script nonce="{{ $cspNonce }}">
        gtag('js', new Date());
        gtag('config', @json($analytics->gaId));
    </script>
@endif

@if ($analytics->gtmId !== null)
    <meta name="gtm-id" content="{{ $analytics->gtmId }}">
@endif

@if ($analytics->measures() && $defer !== null)
    <meta name="analytics-defer" content="{{ (int) $defer }}">
@endif
