{{--
    Haeufige Fragen /faq im Theme sun-v2, im Aufbau der Rechtsseiten (partials/sun/legal-page).
    Daten: PublicFaqController@index: $faqs (question, answer). Aufklappen ueber natives
    <details>, ohne JavaScript. FAQPage-JSON-LD wie im alten Theme.
--}}
@extends('layouts.sun')

@php
    $portalName = $currentTenant->name ?? config('app.name');
    $contactEmail = (string) (tenant()?->getAttribute(\App\Constants\TenantConfigConstants::CONTACT_EMAIL) ?: config('mail.from.address'));
@endphp

@section('title', 'Häufige Fragen | '.$portalName)
@section('meta_description', 'Antworten auf häufige Fragen rund um '.$portalName.': Suche, Bewertungen, Firmeneinträge und Kontakt.')
@section('canonical', route('portal.faqs.index'))

@if($faqs->isNotEmpty())
@push('scripts')
<script type="application/ld+json">
{!! json_encode([
    '@'.'context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => $faqs->map(fn ($faq) => [
        '@type' => 'Question',
        'name' => $faq->question,
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags($faq->answer)],
    ])->values()->all(),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}
</script>
@endpush
@endif

@section('content')
<div class="container-portal pt-6 pb-12 md:pb-16">
  <div class="max-w-3xl">
    <nav aria-label="Brotkrumen" class="text-sm text-zinc-500 flex items-center gap-1.5">
      <a href="{{ route('home') }}" class="hover:text-brand">Start</a><x-sun.icon name="chevron-right" class="size-4 text-zinc-400 shrink-0" /><span class="text-zinc-900">Häufige Fragen</span>
    </nav>

    <h1 class="mt-4 text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">Häufige Fragen</h1>
    <p class="mt-3 text-zinc-500">Kurze Antworten zu Suche, Bewertungen und Firmeneinträgen auf {{ $portalName }}.</p>

    @if($faqs->isEmpty())
      <div class="card p-6 md:p-10 mt-6">
        <div class="flex flex-col sm:flex-row sm:items-start gap-5">
          <span class="size-14 shrink-0 rounded-2xl bg-brand-50 text-brand flex items-center justify-center" aria-hidden="true"><x-sun.icon name="search-x" class="size-7" /></span>
          <div class="flex-1 min-w-0">
            <h2 class="text-2xl font-semibold text-zinc-900">Noch keine Fragen</h2>
            <p class="mt-2 text-zinc-700 leading-relaxed">Hier stehen bald die häufigsten Fragen. Bis dahin helfen wir dir gern direkt weiter.</p>
          </div>
        </div>
      </div>
    @else
      <div class="card mt-6 divide-y divide-zinc-200">
        @foreach($faqs as $faq)
          <details class="group" @if($loop->first) open @endif>
            <summary class="flex items-start justify-between gap-4 p-5 md:px-6 cursor-pointer list-none min-h-11 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand rounded-2xl">
              <span class="font-semibold text-zinc-900 leading-snug">{{ $faq->question }}</span>
              <x-sun.icon name="chevron-down" class="size-5 text-zinc-400 shrink-0 mt-0.5 transition-transform duration-150 group-open:rotate-180" />
            </summary>
            <div class="px-5 md:px-6 pb-5 -mt-1 text-base leading-relaxed text-zinc-700">{!! nl2br(e($faq->answer)) !!}</div>
          </details>
        @endforeach
      </div>
    @endif

    @if($contactEmail !== '')
      <div class="mt-6 rounded-2xl bg-brand-50 p-5 md:p-6">
        <p class="font-semibold text-zinc-900">Deine Frage ist nicht dabei?</p>
        <p class="mt-1">Schreib uns – wir melden uns in der Regel innerhalb von zwei Werktagen.</p>
        <a href="mailto:{{ $contactEmail }}" class="mt-4 btn-secondary"><x-sun.icon name="mail" class="icon" />{{ $contactEmail }}</a>
      </div>
    @endif
  </div>
</div>
@endsection
