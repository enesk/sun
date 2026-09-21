{{--
    Strukturierte Daten der Ratgeber-Seiten (#17): Article, FAQPage,
    BreadcrumbList. Erwartet: $graph aus GuideStructuredData.
    JSON_HEX_TAG ist Pflicht (tests/Unit/Seo/JsonLdEscapingTest.php).
--}}
@php
    $guideGraph = array_values(array_filter($graph ?? []));
@endphp
@if($guideGraph !== [])
    <script type="application/ld+json">{!! json_encode(['@con'.'text' => 'https://schema.org', '@graph' => $guideGraph], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endif
