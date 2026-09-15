{{--
    "Ist das dein Betrieb?" im Theme sun-v2 (Vorlage elektrikerportal-uebernehmen.html),
    Route companies.suggest-edit. Zwei Reiter:
    - Übernehmen: livewire portal.claim-form (Logik aus ClaimModal)
    - Fehler melden: livewire portal.suggest-edit-form, direkt aktiv bei #aendern
    Daten: CompanyController::suggestEdit() plus $claim aus ClaimViewComposer.
--}}
@extends('layouts.sun')

@section('title', __('portal.claim.page.meta_title', ['firma' => $company->name]).' | '.($currentTenant->name ?? config('app.name')))
@section('meta_description', __('portal.claim.page.meta_description', ['firma' => $company->name]))
@section('meta_robots', 'noindex, follow')
@section('canonical', route('companies.suggest-edit', $company->slug))

@section('content')
<div class="container-portal pt-8 pb-12 md:pt-12 md:pb-16">

  <div class="max-w-xl">
    <h1 class="text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">{{ __('portal.claim.page.heading') }}</h1>
    <p class="mt-3 text-base md:text-lg leading-relaxed">{{ __('portal.claim.page.intro', ['firma' => $company->name]) }}</p>
  </div>

  <div class="mt-8 grid gap-8 grid-cols-[minmax(0,1fr)] xl:grid-cols-[minmax(0,36rem)_minmax(0,1fr)] xl:items-start">

    <!-- ================= FORMULAR-KARTE ================= -->
    <div class="card p-5 md:p-8" id="claimCard">

      <!-- Umschalter -->
      <div class="grid grid-cols-2 gap-1 p-1 rounded-xl bg-zinc-100" role="tablist" aria-label="{{ __('portal.claim.page.tabs_label') }}">
        <button type="button" role="tab" aria-selected="true" data-tab="claim" class="min-h-11 rounded-lg font-semibold text-base transition-colors duration-150 bg-white text-zinc-900 shadow-sm focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand">{{ __('portal.claim.page.tab_claim') }}</button>
        <button type="button" role="tab" aria-selected="false" data-tab="suggest" data-tab-hash="aendern" class="min-h-11 rounded-lg font-semibold text-base transition-colors duration-150 text-zinc-500 hover:text-zinc-900 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand">{{ __('portal.claim.page.tab_suggest') }}</button>
      </div>

      <!-- ===== TAB: ÜBERNEHMEN ===== -->
      <div data-panel="claim" class="mt-6">
        <livewire:portal.claim-form :company="$company" />
      </div>

      <!-- ===== TAB: ÄNDERUNG VORSCHLAGEN ===== -->
      <div data-panel="suggest" class="mt-6 hidden">
        <livewire:portal.suggest-edit-form :company="$company" />
      </div>
    </div>

    <!-- ================= VERTRAUEN (Desktop rechts, Mobile darunter) ================= -->
    <aside class="flex flex-col gap-4 xl:sticky xl:top-20 min-w-0">
      <div class="card p-5 md:p-6">
        <h2 class="text-lg font-semibold text-zinc-900">{{ __('portal.claim.page.benefits.heading') }}</h2>
        <ul class="mt-3 flex flex-col gap-3 text-zinc-700">
          @foreach([
            [__('portal.claim.page.benefits.requests_title'), __('portal.claim.page.benefits.requests_text')],
            [__('portal.claim.page.benefits.reviews_title'), __('portal.claim.page.benefits.reviews_text')],
            [__('portal.claim.page.benefits.profile_title'), __('portal.claim.page.benefits.profile_text')],
            [__('portal.claim.page.benefits.badge_title'), __('portal.claim.page.benefits.badge_text')],
          ] as [$benefit, $detail])
            <li class="flex items-start gap-3"><span class="mt-0.5 shrink-0"><x-sun.icon name="check" class="icon text-brand" /></span><span class="min-w-0"><span class="font-medium text-zinc-900">{{ $benefit }}</span><br><span class="text-sm text-zinc-500">{{ $detail }}</span></span></li>
          @endforeach
        </ul>
      </div>
      <div class="card p-5 md:p-6">
        <dl class="grid grid-cols-2 gap-4">
          <div><dt class="text-sm text-zinc-500">{{ __('portal.claim.page.stats.claimed') }}</dt><dd class="text-xl font-semibold text-zinc-900">{{ number_format($claim['claimedCount'], 0, ',', '.') }}</dd></div>
          <div><dt class="text-sm text-zinc-500">{{ __('portal.claim.page.stats.duration') }}</dt><dd class="text-xl font-semibold text-zinc-900">{{ __('portal.claim.page.stats.duration_value') }}</dd></div>
          <div><dt class="text-sm text-zinc-500">{{ __('portal.claim.page.stats.cost') }}</dt><dd class="text-xl font-semibold text-zinc-900">{{ __('portal.claim.page.stats.cost_value') }}</dd></div>
          <div><dt class="text-sm text-zinc-500">{{ __('portal.claim.page.stats.cancellation') }}</dt><dd class="text-xl font-semibold text-zinc-900">{{ __('portal.claim.page.stats.cancellation_value') }}</dd></div>
        </dl>
        @if($claim['contactEmail'])
          <p class="mt-4 pt-4 border-t border-zinc-200 text-sm text-zinc-500">{!! strtr(e(__('portal.claim.page.contact', ['email' => '[[email]]'])), [
            '[[email]]' => '<a href="mailto:'.e($claim['contactEmail']).'" class="text-brand hover:underline">'.e($claim['contactEmail']).'</a>',
          ]) !!}</p>
        @endif
      </div>
    </aside>
  </div>
</div>
@endsection
