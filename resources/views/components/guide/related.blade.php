{{--
    „Passende Ratgeber“ (#18) auf Portal-Kategorie-, Stadt- und Profilseiten.
    Daten: App\View\Components\Guide\Related → GuidePageData::relatedForPortalCategory().
    Karten wie im Ratgeber (guide.partials.topic-card), ohne Pruefdatum.
    Das Theme sun-v2 hat eine eigene Fassung unter themes/sun-v2/views/components/guide/.
--}}
<section {{ $attributes->merge(['class' => 'ratgeber-section']) }} aria-labelledby="guide-related-heading">
    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 mb-4">
        <h2 id="guide-related-heading" class="ratgeber-section__heading" style="margin-bottom: 0">Passende Ratgeber</h2>
        <a href="{{ route('guide.index') }}" class="text-sm font-medium hover:underline" style="color: var(--portal-primary-text, #3472D8)">Alle Ratgeber</a>
    </div>
    <div class="ratgeber-list">
        @foreach($topics as $card)
            @include('guide.partials.topic-card', ['card' => $card, 'headingTag' => 'h3'])
        @endforeach
    </div>
</section>
