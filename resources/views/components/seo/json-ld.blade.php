{{-- JSON-LD aus dem SeoService (#8). Inhalt kommt ausschliesslich aus dem Service. --}}
@foreach (app(\App\Services\Seo\SeoService::class)->jsonLd() as $jsonLdBlock)
<script type="application/ld+json">{!! json_encode($jsonLdBlock, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endforeach
