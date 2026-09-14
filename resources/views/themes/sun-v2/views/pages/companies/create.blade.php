{{--
    Firma eintragen /eintragen im Theme sun-v2 (Vorlage elektrikerportal-firma-eintragen (1).html).
    Zwei Schritte in einer Karte ohne Seitenwechsel: livewire:portal.company-signup
    (App\Livewire\Portal\CompanySignup). Rechte Spalte statisch aus der Vorlage.
--}}
@extends('layouts.sun')

@php
    $portalName = $currentTenant->name ?? config('app.name');
    $panelItems = [
        ['Leistungen & Einsatzgebiet', 'Wofür du Anfragen bekommen willst'],
        ['Öffnungszeiten', 'Profile mit Zeiten werden häufiger angerufen'],
        ['Beschreibung & Fotos', 'Was deinen Betrieb ausmacht'],
        ['Bewertungen beantworten', 'Öffentlich unter jeder Bewertung'],
    ];
    $afterSteps = [
        'Wir prüfen den Eintrag – meist innerhalb eines Werktags.',
        'Dein Profil geht online und erscheint in Suche und Stadtseite.',
        'Anfragen kommen per SMS und E-Mail bei dir an.',
    ];
@endphp

@section('title', 'Firma eintragen – kostenlos | '.$portalName)
@section('meta_description', 'Trag deinen Elektrobetrieb kostenlos ein: gefunden werden, Anfragen aufs Handy bekommen und Bewertungen sammeln.')
@section('canonical', route('portal.companies.create'))
@section('footer_reduced', '1')

@section('content')
<div class="container-portal pt-8 pb-12 md:pt-12 md:pb-16">

  <div class="max-w-xl">
    <h1 class="text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">Trag deinen Betrieb ein</h1>
    <p class="mt-3 text-base md:text-lg leading-relaxed">Kostenlos, in zwei Minuten. Erst die Daten deines Betriebs, dann ein Konto zum Verwalten.</p>
  </div>

  <div class="mt-8 grid gap-8 grid-cols-[minmax(0,1fr)] xl:grid-cols-[minmax(0,32rem)_minmax(0,20rem)] xl:items-start">

    <livewire:portal.company-signup />

    <aside class="flex flex-col gap-4 xl:sticky xl:top-20 min-w-0">
      <div class="card p-5 md:p-6">
        <h2 class="text-lg font-semibold text-zinc-900">Danach im Panel</h2>
        <p class="mt-1 text-sm text-zinc-500">Diese Angaben ergänzt du jederzeit selbst – je vollständiger dein Profil, desto mehr Anfragen.</p>
        <ul class="mt-4 flex flex-col gap-3">
          @foreach($panelItems as [$title, $text])
            <li class="flex items-start gap-3 text-zinc-500"><span class="mt-0.5 shrink-0 text-zinc-400"><x-sun.icon name="lock" class="size-4 shrink-0" /></span><span class="min-w-0"><span class="font-medium text-zinc-700 block">{{ $title }}</span><span class="text-sm">{{ $text }}</span></span></li>
          @endforeach
        </ul>
      </div>
      <div class="card p-5 md:p-6">
        <h2 class="font-semibold text-zinc-900">Was danach passiert</h2>
        <ol class="mt-3 flex flex-col gap-3 text-sm text-zinc-700">
          @foreach($afterSteps as $number => $text)
            <li class="flex items-start gap-3"><span class="size-6 rounded-full bg-zinc-100 text-zinc-700 font-semibold text-xs flex items-center justify-center shrink-0">{{ $number + 1 }}</span><span class="min-w-0">{{ $text }}</span></li>
          @endforeach
        </ol>
      </div>
    </aside>
  </div>
</div>
@endsection
