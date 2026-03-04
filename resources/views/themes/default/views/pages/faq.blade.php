@extends('layouts.app')

@section('title', 'Häufige Fragen — ' . ($currentTenant->name ?? config('app.name')))
@section('meta_description', 'Antworten auf häufig gestellte Fragen rund um ' . ($currentTenant->name ?? config('app.name')))

@section('content')

    {{-- Schema.org FAQPage --}}
    @if($faqs->isNotEmpty())
        @push('scripts')
        <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $faqs->map(fn ($faq) => [
                '@type' => 'Question',
                'name' => $faq->question,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => strip_tags($faq->answer),
                ],
            ])->values()->all(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
        </script>
        @endpush
    @endif

    @include('components.breadcrumb', ['items' => [
        ['label' => 'Home', 'url' => route('home')],
        ['label' => 'Häufige Fragen'],
    ]])

    {{-- Mini-Hero --}}
    <div class="legal-hero">
        <div class="container mx-auto px-4 text-center relative z-10">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-white/15 backdrop-blur-sm mb-4">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
                </svg>
            </div>
            <h1 class="text-2xl md:text-3xl font-bold text-white mb-2">Häufig gestellte Fragen</h1>
            <p class="text-white/70 text-sm md:text-base">Antworten auf die wichtigsten Fragen rund um unser Portal</p>
        </div>
    </div>

    {{-- FAQ Content --}}
    <div class="container mx-auto px-4 pb-16">
        <div class="max-w-3xl mx-auto">

            @if($faqs->isNotEmpty())
                <div class="faq-list" role="list">
                    @foreach($faqs as $index => $faq)
                        <div class="faq-item reveal" x-data="{ open: false }" data-stagger-delay="{{ ($index + 1) * 80 }}ms" role="listitem">
                            <button
                                @click="open = !open"
                                class="faq-question"
                                :class="{ 'faq-question--open': open }"
                                :aria-expanded="open.toString()"
                                aria-controls="faq-answer-{{ $index }}"
                            >
                                <span class="faq-question__text">{{ $faq->question }}</span>
                                <svg class="faq-question__chevron" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div
                                id="faq-answer-{{ $index }}"
                                x-show="open"
                                x-collapse
                                x-cloak
                                role="region"
                                :aria-hidden="(!open).toString()"
                                class="faq-answer"
                            >
                                <div class="faq-answer__content">
                                    {!! nl2br(e($faq->answer)) !!}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Kontakt-CTA --}}
                <div class="faq-contact reveal">
                    <div class="faq-contact__icon">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-[#0F172A] mb-1">Ihre Frage war nicht dabei?</h3>
                    <p class="text-sm text-[#64748B] mb-4">Kontaktieren Sie uns — wir helfen Ihnen gerne weiter.</p>
                    <a href="{{ route('portal.impressum') }}" class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold rounded-xl transition-all duration-200 hover:-translate-y-0.5" style="background: var(--portal-primary, #3B82F6); color: white;">
                        Kontakt aufnehmen
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>

            @else
                {{-- Empty State --}}
                <div class="text-center py-16">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-[#F1F5F9] mb-4">
                        <svg class="w-8 h-8 text-[#94A3B8]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
                        </svg>
                    </div>
                    <h2 class="text-lg font-semibold text-[#0F172A] mb-2">Noch keine FAQs vorhanden</h2>
                    <p class="text-sm text-[#64748B]">Die häufigsten Fragen werden hier bald beantwortet.</p>
                </div>
            @endif

        </div>
    </div>

@endsection
