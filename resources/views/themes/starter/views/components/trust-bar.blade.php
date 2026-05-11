{{-- Trust Bar: Social-Proof Cards mit Stagger-Animation (VR-4) --}}
@php
    $stats = [
        [
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="w-[22px] h-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
            'value' => $totalCompanies ?? 0,
            'label' => 'Unternehmen',
            'show' => ($totalCompanies ?? 0) > 0,
        ],
        [
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="w-[22px] h-[22px]" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
            'value' => $avgRating ?? 0,
            'label' => 'Ø Bewertung',
            'show' => ($avgRating ?? 0) > 0,
            'is_rating' => true,
        ],
        [
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="w-[22px] h-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
            'value' => $totalReviews ?? 0,
            'label' => 'Bewertungen',
            'show' => ($totalReviews ?? 0) > 0,
        ],
        [
            'icon' => '<svg xmlns="http://www.w3.org/2000/svg" class="w-[22px] h-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>',
            'value' => $totalCities ?? 0,
            'label' => 'Städte',
            'show' => ($totalCities ?? 0) > 0,
        ],
    ];

    $visibleStats = collect($stats)->where('show', true)->values();
@endphp

@if($visibleStats->isNotEmpty())
<section class="bg-[#f8fafc] border-b border-base-200/50" aria-label="Portal-Statistiken">
    <div class="container mx-auto px-4 py-10">
        {{-- Trust Cards Grid: max 4 sichtbar (5 auf breiten Screens) --}}
        <div class="grid grid-cols-2 md:grid-cols-{{ min($visibleStats->count(), 4) }} lg:grid-cols-{{ $visibleStats->count() }} gap-3 md:gap-4 max-w-[900px] mx-auto">
            @foreach($visibleStats as $index => $stat)
                <div class="trust-card reveal"
                     data-stagger-delay="{{ $index * 100 }}ms"
                     data-target="{{ $stat['value'] }}"
                     @if(!empty($stat['is_rating'])) data-is-rating="true" @endif>

                    {{-- Icon Circle --}}
                    <div class="trust-card__icon">
                        {!! $stat['icon'] !!}
                    </div>

                    {{-- Value --}}
                    <div class="trust-card__value trust-bar-value tabular-nums">
                        {{ !empty($stat['is_rating']) ? number_format($stat['value'], 1, ',', '.') : number_format($stat['value'], 0, ',', '.') }}
                    </div>

                    {{-- Label --}}
                    <div class="trust-card__label">
                        {{ $stat['label'] }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Count-Up Animation mit Stagger (IntersectionObserver) --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (prefersReducedMotion) return;

    const cards = document.querySelectorAll('.trust-card');
    if (!cards.length) return;

    const observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (!entry.isIntersecting) return;
            observer.unobserve(entry.target);

            const el = entry.target.querySelector('.trust-bar-value');
            const isRating = entry.target.dataset.isRating === 'true';
            const target = parseFloat(entry.target.dataset.target);
            const staggerDelay = parseInt(entry.target.dataset.staggerDelay) || 0;
            const duration = 1200;

            setTimeout(function () {
                const start = performance.now();

                function animate(now) {
                    const elapsed = now - start;
                    const progress = Math.min(elapsed / duration, 1);
                    const eased = 1 - Math.pow(1 - progress, 3);
                    const current = target * eased;

                    if (isRating) {
                        el.textContent = current.toFixed(1).replace('.', ',');
                    } else {
                        el.textContent = Math.round(current).toLocaleString('de-DE');
                    }

                    if (progress < 1) {
                        requestAnimationFrame(animate);
                    }
                }

                el.textContent = isRating ? '0,0' : '0';
                requestAnimationFrame(animate);
            }, staggerDelay);
        });
    }, { threshold: 0.3 });

    cards.forEach(function (card) { observer.observe(card); });
});
</script>
@endif
