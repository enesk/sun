{{--
    Strukturierte Daten der Ratgeber-Seiten (#17).
    Erwartet: $graph — Liste von Schema.org-Knoten aus ArticleSeoService.
--}}
@php($ratgeberGraph = array_values(array_filter($graph ?? [])))

@if($ratgeberGraph)
    <script type="application/ld+json">
        @json(['@context' => 'https://schema.org', '@graph' => $ratgeberGraph], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG)
    </script>
@endif
