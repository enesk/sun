{{--
    "Ist das dein Betrieb?" im Theme sun-v2 (Vorlage elektrikerportal-uebernehmen.html),
    Route companies.suggest-edit. Zwei Reiter:
    - Übernehmen: livewire portal.claim-form (Logik aus ClaimModal)
    - Fehler melden: livewire portal.suggest-edit-form, direkt aktiv bei #aendern
    Daten: CompanyController::suggestEdit() plus $claim aus ClaimViewComposer.
--}}
@extends('layouts.sun')

@section('title', 'Ist das dein Betrieb? '.$company->name.' | '.($currentTenant->name ?? config('app.name')))
@section('meta_description', 'Übernimm den Eintrag von '.$company->name.' kostenlos oder melde, was daran nicht stimmt.')
@section('meta_robots', 'noindex, follow')
@section('canonical', route('companies.suggest-edit', $company->slug))

@section('content')
<div class="container-portal pt-8 pb-12 md:pt-12 md:pb-16">

  <div class="max-w-xl">
    <h1 class="text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">Ist das dein Betrieb?</h1>
    <p class="mt-3 text-base md:text-lg leading-relaxed">Übernimm den Eintrag von {{ $company->name }} – kostenlos, in zwei Minuten. Oder sag uns, was daran nicht stimmt.</p>
  </div>

  <div class="mt-8 grid gap-8 grid-cols-[minmax(0,1fr)] xl:grid-cols-[minmax(0,36rem)_minmax(0,1fr)] xl:items-start">

    <!-- ================= FORMULAR-KARTE ================= -->
    <div class="card p-5 md:p-8" id="claimCard">

      <!-- Umschalter -->
      <div class="grid grid-cols-2 gap-1 p-1 rounded-xl bg-zinc-100" role="tablist" aria-label="Was möchtest du tun?">
        <button type="button" role="tab" aria-selected="true" data-tab="claim" class="min-h-11 rounded-lg font-semibold text-base transition-colors duration-150 bg-white text-zinc-900 shadow-sm focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand">Übernehmen</button>
        <button type="button" role="tab" aria-selected="false" data-tab="suggest" data-tab-hash="aendern" class="min-h-11 rounded-lg font-semibold text-base transition-colors duration-150 text-zinc-500 hover:text-zinc-900 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand">Fehler melden</button>
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
        <h2 class="text-lg font-semibold text-zinc-900">Das bekommst du als Inhaber:in</h2>
        <ul class="mt-3 flex flex-col gap-3 text-zinc-700">
          @foreach([
            ['Anfragen direkt aufs Handy', 'Kund:innen schicken über dein Profil Anfragen – du bekommst sie per SMS und E-Mail.'],
            ['Auf Bewertungen antworten', 'Deine Antwort steht öffentlich unter der Bewertung.'],
            ['Profil selbst pflegen', 'Fotos, Leistungen, Öffnungszeiten – Änderungen sind sofort live.'],
            ['Badge „Geprüfter Eintrag“', 'Steht sichtbar auf deinem Profil und in der Ergebnisliste.'],
          ] as [$benefit, $detail])
            <li class="flex items-start gap-3"><span class="mt-0.5 shrink-0"><x-sun.icon name="check" class="icon text-brand" /></span><span class="min-w-0"><span class="font-medium text-zinc-900">{{ $benefit }}</span><br><span class="text-sm text-zinc-500">{{ $detail }}</span></span></li>
          @endforeach
        </ul>
      </div>
      <div class="card p-5 md:p-6">
        <dl class="grid grid-cols-2 gap-4">
          <div><dt class="text-sm text-zinc-500">Übernommene Einträge</dt><dd class="text-xl font-semibold text-zinc-900">{{ number_format($claim['claimedCount'], 0, ',', '.') }}</dd></div>
          <div><dt class="text-sm text-zinc-500">Dauer</dt><dd class="text-xl font-semibold text-zinc-900">2 Min.</dd></div>
          <div><dt class="text-sm text-zinc-500">Kosten</dt><dd class="text-xl font-semibold text-zinc-900">0 €</dd></div>
          <div><dt class="text-sm text-zinc-500">Kündigung</dt><dd class="text-xl font-semibold text-zinc-900">Jederzeit</dd></div>
        </dl>
        @if($claim['contactEmail'])
          <p class="mt-4 pt-4 border-t border-zinc-200 text-sm text-zinc-500">Fragen? <a href="mailto:{{ $claim['contactEmail'] }}" class="text-brand hover:underline">{{ $claim['contactEmail'] }}</a> – wir antworten werktags innerhalb eines Tages.</p>
        @endif
      </div>
    </aside>
  </div>
</div>
@endsection
