{{--
    Verifizierungsseite /firma/{slug}/verifizierung im Theme sun-v2.
    Der Nachweis wird normalerweise direkt auf "Ist das dein Betrieb?"
    hochgeladen; diese Seite bleibt fuer bestehende Links (E-Mails, altes
    Theme) und zeigt denselben Upload in der Karte der Uebernahme-Seite.
--}}
@extends('layouts.sun')

@section('title', 'Nachweis hochladen – '.$company->name.' | '.($currentTenant->name ?? config('app.name')))
@section('meta_robots', 'noindex, nofollow')

@section('content')
<div class="container-portal pt-8 pb-12 md:pt-12 md:pb-16">
  <div class="max-w-xl">
    <h1 class="text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">Fast geschafft</h1>
    <p class="mt-3 text-base md:text-lg leading-relaxed">Noch der Nachweis, dann gehört der Eintrag von {{ $company->name }} dir.</p>
  </div>
  <div class="mt-8 max-w-xl card p-5 md:p-8">
    <div class="mb-6"><x-sun.claim-company :company="$company" /></div>
    <livewire:portal.claim-verification :company="$company" />
  </div>
</div>
@endsection
