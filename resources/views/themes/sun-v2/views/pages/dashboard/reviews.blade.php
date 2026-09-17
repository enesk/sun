{{--
    Bewertungen im Betriebsbereich, Theme sun-v2 (Vorlage bewertungen-elektrikerportal.html).
    Daten: OwnerDashboardController::reviews(). Texte: lang/de/portal.php (owner.reviews.*).

    Bewertungslink /bewerten/{slug}, QR-Code und Widget (#12): $reviewLink aus dem Controller,
    Tools in livewire:portal.company.dashboard.reviews. Antworten nur mit Feature review_replies ($canReply).

    Abweichung von der Vorlage, weil das Backend fehlt:
    - "Melden" oeffnet eine E-Mail an die Kontaktadresse des Portals; ohne Adresse entfaellt es.
--}}
@extends('layouts.panel')

@php
    $supportEmail = $currentTenant?->getAttribute(\App\Constants\TenantConfigConstants::CONTACT_EMAIL);
    $filters = ['all', 'published', 'pending', 'unanswered'];
    $maxCount = max(1, max($distribution));
    // Antwort-Vorschau fuer Basis-Eintraege nur unter der ersten veroeffentlichten Bewertung
    $previewReviewId = $canReply ? null : $reviews->first(fn ($review) => $review->isApproved())?->id;
@endphp

@section('title', __('portal.owner.reviews.title'))

@section('content')
  <div class="flex flex-wrap items-start justify-between gap-3">
    <div class="min-w-0">
      <h1 class="text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">{{ __('portal.owner.reviews.title') }}</h1>
      <p class="mt-1 text-base text-zinc-500">
        {{ trans_choice('portal.owner.reviews.intro', $counts['all'], ['anzahl' => $counts['all'], 'firma' => $company->name]) }}@if($counts['all'] > 0){{ $counts['published'] === $counts['all'] ? __('portal.owner.reviews.intro_all_published') : __('portal.owner.reviews.intro_published', ['anzahl' => $counts['published']]) }}@endif
      </p>
    </div>
    <button type="button" class="btn-secondary" data-copy data-copied="{{ __('portal.owner.reviews.copied') }}" aria-controls="bewertungslink">
      <x-sun.icon name="link" class="icon" /> {{ __('portal.owner.reviews.ask') }}
    </button>
  </div>
  <p class="sr-only" role="status" data-copy-status></p>

  <div class="mt-6 grid lg:grid-cols-[1fr_20rem] gap-4 md:gap-6 items-start">

    <div class="space-y-4 md:space-y-6 min-w-0">

      {{-- Zusammenfassung --}}
      <section class="card p-5 md:p-6 grid sm:grid-cols-[auto_1fr] gap-6 items-center">
        <div class="text-center sm:pr-6 sm:border-r sm:border-zinc-200">
          <p class="text-5xl font-bold text-zinc-900">{{ $company->rating_count > 0 ? number_format((float) $company->rating, 1, ',', '') : '–' }}</p>
          <p class="mt-1 flex justify-center" role="img" aria-label="{{ __('portal.owner.reviews.stars', ['anzahl' => number_format((float) $company->rating, 1, ',', '')]) }}">
            <x-sun.stars :rating="$company->rating" size="size-5" />
          </p>
          <p class="mt-1 text-sm text-zinc-500">{{ trans_choice('portal.owner.reviews.rating_count', (int) $company->rating_count, ['anzahl' => (int) $company->rating_count]) }}</p>
        </div>
        <ul class="space-y-1.5" aria-label="{{ __('portal.owner.reviews.distribution') }}">
          @foreach($distribution as $stars => $count)
            <li class="flex items-center gap-3 text-sm">
              <span class="w-6 text-zinc-500 text-right">{{ $stars }}</span>
              <x-sun.icon name="star" class="size-4 shrink-0 fill-amber-500 text-amber-500" stroke="none" />
              <span class="flex-1 h-2 rounded-full bg-zinc-100 overflow-hidden"><span class="block h-full bg-amber-500 rounded-full" style="width:{{ round($count / $maxCount * 100) }}%"></span></span>
              <span class="w-6 text-zinc-500">{{ $count }}</span>
            </li>
          @endforeach
        </ul>
      </section>

      {{-- Filter --}}
      <nav aria-label="{{ __('portal.owner.reviews.filter.label') }}" class="-mx-4 px-4 flex gap-2 overflow-x-auto pb-1 md:mx-0 md:px-0">
        @foreach($filters as $key)
          <a href="{{ route('portal.owner.reviews', $key === 'all' ? [] : ['filter' => $key]) }}"
             class="{{ $filter === $key ? 'pill-brand' : 'pill hover:bg-zinc-200' }} whitespace-nowrap min-h-11 px-4"
             @if($filter === $key) aria-current="page" @endif>{{ __("portal.owner.reviews.filter.{$key}") }} · {{ $counts[$key] }}</a>
        @endforeach
      </nav>

      @if($reviews->isEmpty())
        <div class="card p-5 md:p-6">
          <p class="text-base text-zinc-500">{{ $counts['all'] === 0 ? __('portal.owner.reviews.empty') : __('portal.owner.reviews.empty_filter') }}</p>
        </div>
      @endif

      @foreach($reviews as $review)
        <article class="card p-5 md:p-6">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex flex-wrap items-center gap-2 min-w-0">
              <h3 class="text-lg font-semibold text-zinc-900">{{ $review->author_name ?: __('portal.owner.reviews.anonymous') }}</h3>
              @if($review->isApproved())
                <span class="pill bg-emerald-50 text-emerald-700 text-xs">{{ __('portal.owner.reviews.status.published') }}</span>
              @elseif($review->isRejected())
                <span class="pill bg-red-50 text-red-600 text-xs">{{ __('portal.owner.reviews.status.rejected') }}</span>
              @else
                <span class="pill bg-amber-50 text-amber-700 text-xs">{{ __('portal.owner.reviews.status.pending') }}</span>
              @endif
            </div>
            <p class="text-sm text-zinc-500">{{ $review->created_at->format('d.m.Y') }}</p>
          </div>
          <p class="mt-1" role="img" aria-label="{{ __('portal.owner.reviews.stars', ['anzahl' => (int) round((float) $review->rating)]) }}">
            <x-sun.stars :rating="$review->rating" />
          </p>
          @if($review->title)
            <p class="mt-3 text-base font-medium text-zinc-900">{{ $review->title }}</p>
          @endif
          @if($review->body)
            <p class="{{ $review->title ? 'mt-1' : 'mt-3' }} text-base leading-relaxed text-zinc-700 max-w-prose">{{ $review->body }}</p>
          @endif
          @if($review->isRejected() && $review->moderation_note)
            <p class="mt-2 text-sm text-red-600">{{ __('portal.owner.reviews.rejected_reason', ['grund' => $review->moderation_note]) }}</p>
          @endif

          {{-- Vorhandene Antwort (review_replies) --}}
          @if($canReply && $review->isApproved() && ! empty($review->owner_response))
            <div class="mt-4 rounded-xl bg-zinc-50 p-4">
              <p class="text-sm font-semibold text-zinc-900 flex items-center gap-2">
                <x-sun.icon name="reply" class="size-4 shrink-0" />{{ __('portal.owner.reviews.your_reply') }}
                @if($review->owner_response_at)
                  <span class="font-normal text-zinc-500">· {{ $review->owner_response_at->format('d.m.Y') }}</span>
                @endif
              </p>
              <p class="mt-1 text-base text-zinc-700">{{ $review->owner_response }}</p>
              <div id="antwort-loeschen-{{ $review->id }}" class="mt-3" hidden>
                <p class="text-sm text-red-600">{{ __('portal.owner.reviews.delete_confirm') }}</p>
                <form action="{{ route('portal.owner.reviews.delete-response', $review->id) }}" method="POST" class="mt-2">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="btn-secondary text-red-600">{{ __('portal.owner.reviews.delete_yes') }}</button>
                </form>
              </div>
            </div>
          @endif

          {{-- Antwort-Vorschau (Basis-Eintrag) --}}
          @if($review->id === $previewReviewId)
            <div class="mt-4 rounded-xl border border-dashed border-brand-200 bg-brand-50 p-4">
              <p class="text-sm font-semibold text-brand-700 flex items-center gap-2"><x-sun.icon name="reply" class="size-4 shrink-0" />{{ __('portal.owner.reviews.preview.title') }}</p>
              <p class="mt-1 text-base text-zinc-500">{{ __('portal.owner.reviews.preview.text') }} – {{ $company->name }}</p>
              <p class="mt-1 text-sm text-zinc-500">{{ __('portal.owner.reviews.preview.hint') }}</p>
            </div>
          @endif

          {{-- Antwortformular (review_replies): eine Antwort je Bewertung, bestehende wird bearbeitet --}}
          @if($canReply && $review->isApproved())
            <form id="antwort-{{ $review->id }}" action="{{ route('portal.owner.reviews.respond', $review->id) }}" method="POST" class="mt-4" hidden>
              @csrf
              <label for="antwort-text-{{ $review->id }}" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.owner.reviews.reply_label') }}</label>
              <textarea id="antwort-text-{{ $review->id }}" name="owner_response" rows="3" maxlength="1000" required
                        class="input py-3" placeholder="{{ __('portal.owner.reviews.reply_placeholder') }}">{{ $review->owner_response }}</textarea>
              <p class="mt-1 text-sm text-zinc-500">{{ __('portal.owner.reviews.reply_hint') }}</p>
              <button type="submit" class="btn-primary mt-3">{{ empty($review->owner_response) ? __('portal.owner.reviews.reply_save') : __('portal.owner.reviews.reply_update') }}</button>
            </form>
          @endif

          <div class="mt-3 -mx-3 flex flex-wrap items-center gap-1">
            @if($review->isApproved())
              @if(! $canReply)
                <a href="{{ route('portal.owner.premium') }}" class="btn-ghost"><x-sun.icon name="reply" class="icon" />{{ __('portal.owner.reviews.reply') }} <span class="pill-brand text-xs ml-1">{{ __('portal.owner.reviews.premium.badge') }}</span></a>
              @elseif(empty($review->owner_response))
                <button type="button" class="btn-ghost" data-disclosure="antwort-{{ $review->id }}" aria-controls="antwort-{{ $review->id }}" aria-expanded="false"><x-sun.icon name="reply" class="icon" />{{ __('portal.owner.reviews.reply') }}</button>
              @else
                <button type="button" class="btn-ghost" data-disclosure="antwort-{{ $review->id }}" aria-controls="antwort-{{ $review->id }}" aria-expanded="false"><x-sun.icon name="pencil" class="icon" />{{ __('portal.owner.reviews.edit_reply') }}</button>
                <button type="button" class="btn-ghost text-zinc-500 hover:bg-zinc-100" data-disclosure="antwort-loeschen-{{ $review->id }}" aria-controls="antwort-loeschen-{{ $review->id }}" aria-expanded="false"><x-sun.icon name="x" class="icon" />{{ __('portal.owner.reviews.delete_reply') }}</button>
              @endif
            @endif
            @if($supportEmail)
              <a href="mailto:{{ $supportEmail }}?subject={{ rawurlencode(__('portal.owner.reviews.report_subject', ['firma' => $company->name, 'id' => $review->id])) }}" class="btn-ghost text-zinc-500 hover:bg-zinc-100"><x-sun.icon name="flag" class="icon" />{{ __('portal.owner.reviews.report') }}</a>
            @endif
          </div>
        </article>
      @endforeach

      <x-sun.pagination :paginator="$reviews" :pages="\App\Themes\SunV2\PageWindow::for($reviews)" />
    </div>

    {{-- Rechte Spalte --}}
    <aside class="space-y-4 md:space-y-6 lg:sticky lg:top-24">
      @unless($canReply)
        <section id="premium" class="rounded-2xl bg-brand-50 border-2 border-brand p-5 md:p-6">
          <p class="pill-brand"><x-sun.icon name="sparkles" class="size-4 shrink-0" />{{ __('portal.owner.reviews.premium.badge') }}</p>
          <h2 class="mt-3 text-2xl font-semibold text-zinc-900">{{ trans_choice('portal.owner.reviews.premium.title', $counts['unanswered'], ['anzahl' => $counts['unanswered']]) }}</h2>
          <p class="mt-2 text-base text-zinc-700">{{ __('portal.owner.reviews.premium.text') }}</p>
          <a href="{{ route('portal.owner.premium') }}" class="btn-primary mt-4 w-full">{{ __('portal.owner.reviews.premium.cta') }}</a>
          <p class="mt-2 text-sm text-zinc-500 text-center">{{ __('portal.owner.reviews.premium.note') }}</p>
        </section>
      @endunless

      <section class="card p-5 md:p-6">
        <h2 class="text-lg font-semibold text-zinc-900">{{ __('portal.owner.reviews.link.title') }}</h2>
        <p class="mt-1 text-base text-zinc-700">{{ __('portal.owner.reviews.link.text') }}</p>
        <div class="mt-3 flex gap-2">
          <input id="bewertungslink" class="input text-sm" readonly value="{{ $reviewLink }}" aria-label="{{ __('portal.owner.reviews.link.label') }}">
          <button type="button" class="btn-secondary shrink-0 px-3" data-copy data-copied="{{ __('portal.owner.reviews.copied') }}" aria-controls="bewertungslink" aria-label="{{ __('portal.owner.reviews.link.copy') }}">
            <x-sun.icon name="link" class="icon" />
          </button>
        </div>
      </section>

      <livewire:portal.company.dashboard.reviews />

      @if($supportEmail)
        <section class="card p-5 md:p-6">
          <h2 class="text-lg font-semibold text-zinc-900">{{ __('portal.owner.reviews.report_help.title') }}</h2>
          <p class="mt-1 text-base text-zinc-700">{{ __('portal.owner.reviews.report_help.text') }}</p>
        </section>
      @endif
    </aside>
  </div>
@endsection
