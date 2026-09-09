{{--
    Organization-JSON-LD der Ratgeber-Seiten (#18).

    Erwartet: $organization — Knoten aus PortalProfileService::organizationJsonLd().
    Optional: $person — Person-Knoten der Autorenseite.

    Auf Artikelseiten steckt derselbe Organization-Knoten bereits im
    Article-Graph (ArticleSeoService); dieses Partial ist fuer die uebrigen
    Ratgeber-Seiten gedacht, die keinen Article-Graph ausgeben.
--}}
@php
    $organizationGraph = array_values(array_filter([$organization ?? [], $person ?? null]));
    // Der Schluessel wird zusammengesetzt: '@context' waere in Blade eine Direktive.
    $organizationDocument = $organizationGraph === [] ? null : [
        '@'.'context' => 'https://schema.org',
        '@'.'graph' => $organizationGraph,
    ];
@endphp

@if($organizationDocument)
    <script type="application/ld+json">
        {!! json_encode($organizationDocument, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}
    </script>
@endif
