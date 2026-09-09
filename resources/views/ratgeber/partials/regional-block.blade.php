{{--
    Regionalblock. Fuehrt in die tatsaechliche Stadtseite des Tenants.
    Enthaelt bewusst keinen Werbeplatz.
    Erwartet: $region — aus ArticleBlockPresenter::region().
--}}
@if(!empty($region))
    <section class="ratgeber-region" aria-labelledby="region-heading">
        <h2 id="region-heading" class="ratgeber-region__heading">Was in {{ $region['name'] }} gilt</h2>

        @if($region['intro'])
            <p class="ratgeber-region__intro">{{ $region['intro'] }}</p>
        @endif

        @if(!empty($region['facts']))
            <dl class="ratgeber-region__facts">
                @foreach($region['facts'] as $fact)
                    <dt>{{ $fact['label'] }}</dt>
                    <dd>{{ $fact['value'] }}</dd>
                @endforeach
            </dl>
        @endif

        @if($region['outro'])
            <p class="ratgeber-region__outro">{{ $region['outro'] }}</p>
        @endif

        <div class="ratgeber-region__action">
            <a href="{{ $region['url'] }}" class="ratgeber-region__btn">
                Anbieter in {{ $region['name'] }} vergleichen
            </a>
            @if($region['company_count'])
                <span class="ratgeber-region__count">{{ number_format($region['company_count'], 0, ',', '.') }} gelistete Betriebe</span>
            @endif
        </div>
    </section>
@endif
