{{--
    Stellenanzeigen im Theme sun-v2 (Vorlage elektrikerportal-jobs.html).

    Vorerst reine Gestaltung: Stellen, Zahlen, Filter und Linklisten sind
    Beispielinhalte aus der Vorlage und NICHT an PublicJobController
    angebunden. Filter-Knoepfe, Job-Mail und Pagination haben noch keine
    Funktion — die Anbindung folgt in einem eigenen Schritt.
--}}
@extends('layouts.sun')

@php
    $portalName = $currentTenant->name ?? config('app.name');

    $jobs = [
        ['initials' => 'EM', 'title' => 'Elektroniker für Energie- und Gebäudetechnik (m/w/d)', 'company' => 'Elektro Müller GmbH', 'badge' => 'Top-Anzeige', 'top' => true, 'location' => '20251 Hamburg · 3 km', 'type' => 'Vollzeit, unbefristet', 'salary' => '3.400 – 4.100 € / Monat', 'tags' => ['Firmenwagen', 'Meisterbetrieb', 'Weiterbildung bezahlt'], 'age' => 'Vor 2 Tagen'],
        ['initials' => 'ES', 'title' => 'Obermonteur Elektrotechnik (m/w/d)', 'company' => 'Elbstrom Elektrotechnik', 'badge' => 'Neu', 'top' => false, 'location' => '22767 Hamburg · 5 km', 'type' => 'Vollzeit', 'salary' => 'ab 4.200 € / Monat', 'tags' => ['Führungsrolle', 'Firmenwagen', '30 Tage Urlaub'], 'age' => 'Vor 3 Tagen'],
        ['initials' => 'EN', 'title' => 'Ausbildung Elektroniker/in Energie- und Gebäudetechnik', 'company' => 'Elektro Nord Barmbek', 'badge' => null, 'top' => false, 'location' => '22305 Hamburg · 6 km', 'type' => 'Ausbildung ab 08/2027', 'salary' => '1.000 – 1.300 € / Monat', 'tags' => ['Ausbildung', 'Übernahme geplant', 'Fahrtkosten'], 'age' => 'Vor 1 Woche'],
        ['initials' => 'WB', 'title' => 'Servicetechniker Wallbox & Ladeinfrastruktur (m/w/d)', 'company' => 'Watt & Bolt GmbH', 'badge' => null, 'top' => false, 'location' => '22765 Hamburg · 7 km', 'type' => 'Vollzeit', 'salary' => '3.600 – 4.400 € / Monat', 'tags' => ['E-Mobilität', 'Servicewagen', 'Keine Montage auswärts'], 'age' => 'Vor 1 Woche'],
        ['initials' => 'LA', 'title' => 'Elektrohelfer (m/w/d) in Teilzeit', 'company' => 'Lichtwerk Altona', 'badge' => null, 'top' => false, 'location' => '22765 Hamburg · 6 km', 'type' => 'Teilzeit, 25 Std.', 'salary' => null, 'tags' => ['Quereinstieg möglich', 'Feste Arbeitszeiten'], 'age' => 'Vor 2 Wochen'],
        ['initials' => 'KS', 'title' => 'KNX-Programmierer / Systemintegrator (m/w/d)', 'company' => 'KNX Systeme Hamburg', 'badge' => null, 'top' => false, 'location' => '20537 Hamburg · 9 km', 'type' => 'Vollzeit, hybrid', 'salary' => '4.000 – 5.200 € / Monat', 'tags' => ['KNX', 'Homeoffice möglich', 'Zertifizierung bezahlt'], 'age' => 'Vor 2 Wochen'],
    ];

    $filters = [
        ['label' => 'Sortierung: Neueste', 'dropdown' => true],
        ['label' => 'Anstellungsart', 'dropdown' => true],
        ['label' => 'Mit Gehaltsangabe', 'dropdown' => false],
        ['label' => 'Ausbildung', 'dropdown' => false],
        ['label' => 'Quereinstieg', 'dropdown' => false],
        ['label' => 'Neu diese Woche', 'dropdown' => false],
    ];

    $professions = [
        ['Elektroniker EuG', 412], ['Obermonteur', 68], ['Servicetechniker', 144], ['Meister / Techniker', 91],
        ['Ausbildung', 176], ['Elektrohelfer', 83], ['KNX / Automation', 57], ['Bauleitung', 34],
    ];

    $cities = [
        ['Hamburg', 248], ['Berlin', 312], ['München', 207], ['Köln', 164],
        ['Frankfurt am Main', 131], ['Stuttgart', 128], ['Düsseldorf', 96], ['Leipzig', 74],
    ];

    $benefits = [
        '30 Tage Laufzeit, Verlängerung optional – keine Vertragsbindung',
        'Bewerbungen landen direkt in deinem Postfach, ohne Zwischenportal',
        'Anzeige wird automatisch an Google for Jobs übergeben',
        'Verlinkt mit deinem Firmenprofil – Bewerber sehen Bewertungen und Leistungen',
    ];

    $pageLink = 'size-11 rounded-full hover:bg-zinc-100 text-zinc-700 font-medium flex items-center justify-center';
@endphp

@section('title', 'Jobs im Elektrohandwerk | '.$portalName)
@section('meta_description', '1.842 offene Stellen bei Elektrobetrieben in ganz Deutschland – direkt vom Betrieb, ohne Personalvermittler dazwischen.')

@section('content')

<!-- ===== HERO mit Job-Suche ===== -->
<section class="bg-brand-50 py-10 md:py-16">
  <div class="container-portal">
    <nav aria-label="Brotkrumen" class="text-sm text-zinc-500 flex items-center gap-1.5">
      <a href="{{ route('home') }}" class="hover:text-brand">Start</a><x-sun.icon name="chevron-right" class="size-4 text-zinc-400 shrink-0" /><span class="text-zinc-900">Stellenanzeigen</span>
    </nav>
    <div class="mt-4 max-w-2xl">
      <h1 class="text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">Jobs im Elektrohandwerk</h1>
      <p class="mt-3 text-base md:text-lg leading-relaxed text-zinc-700">1.842 offene Stellen bei Elektrobetrieben in ganz Deutschland – direkt vom Betrieb, ohne Personalvermittler dazwischen.</p>
    </div>

    <form action="{{ route('portal.jobs.index') }}" method="get" role="search" class="card shadow-lg p-4 md:p-5 mt-6">
      <div class="grid gap-3 lg:grid-cols-[1fr_1fr_auto_auto]">
        <div class="relative">
          <label class="sr-only" for="q">Welche Stelle?</label>
          <x-sun.icon name="search" class="icon absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400" />
          <input id="q" name="q" class="input pl-10" placeholder="Beruf, z. B. Elektroniker">
        </div>
        <div class="relative">
          <label class="sr-only" for="ort">Wo?</label>
          <x-sun.icon name="map-pin" class="icon absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400" />
          <input id="ort" name="ort" class="input pl-10" placeholder="Ort oder PLZ" autocomplete="postal-code">
        </div>
        <select name="umkreis" class="input hidden lg:block lg:w-32" aria-label="Umkreis"><option>10 km</option><option selected>25 km</option><option>50 km</option></select>
        <button type="submit" class="btn-primary">Jobs finden</button>
      </div>
    </form>

    <div class="mt-4 flex flex-wrap items-center gap-2">
      <span class="text-sm text-zinc-500 mr-1 w-full sm:w-auto">Beliebt:</span>
      @foreach(['Elektroniker' => 'Elektroniker EuG', 'Ausbildung' => 'Ausbildung', 'Meister' => 'Meister', 'Quereinstieg' => 'Quereinstieg'] as $query => $label)
        <a href="{{ route('portal.jobs.index', ['q' => $query]) }}" class="pill-link">{{ $label }}</a>
      @endforeach
    </div>
  </div>
</section>

<div class="container-portal pt-8 pb-12 md:pb-16">

  <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-2 sm:gap-4">
    <h2 class="text-2xl font-semibold text-zinc-900">1.842 Stellen in ganz Deutschland</h2>
    <p class="text-sm text-zinc-500">Sortiert nach Aktualität</p>
  </div>

  <div class="mt-5 -mx-4 px-4 sm:mx-0 sm:px-0 flex gap-2 overflow-x-auto scroll-snap pb-1" role="group" aria-label="Filter">
    @foreach($filters as $filter)
      <button type="button" class="pill-link whitespace-nowrap">{{ $filter['label'] }}@if($filter['dropdown']) <x-sun.icon name="chevron-down" class="size-4" />@endif</button>
    @endforeach
  </div>

  <div class="mt-6 grid gap-8 xl:grid-cols-[1fr_18rem]">
    <div class="flex flex-col gap-4 min-w-0">

      @foreach($jobs as $job)
        @if($loop->index === 3)
          <x-ad-slot position="listing_between_results" />
        @endif
        <article class="card-interactive p-5 flex flex-col lg:flex-row lg:items-start gap-4 {{ $job['top'] ? 'border-l-4 border-l-brand' : '' }}">
          <div class="flex-1 min-w-0 flex flex-col gap-3">
            <div class="flex items-start gap-3">
              <span class="size-12 rounded-2xl bg-brand-50 text-brand-700 font-bold flex items-center justify-center shrink-0" aria-hidden="true">{{ $job['initials'] }}</span>
              <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-start justify-between gap-2">
                  <h2 class="text-lg font-semibold text-zinc-900 leading-snug"><a href="#" class="hover:text-brand">{{ $job['title'] }}</a></h2>
                  @if($job['badge'])
                    <span class="pill-brand shrink-0">{{ $job['badge'] }}</span>
                  @endif
                </div>
                <p class="mt-1 text-zinc-700">{{ $job['company'] }}</p>
              </div>
            </div>
            <div class="flex-1 min-w-0">
              <dl class="flex flex-wrap gap-x-5 gap-y-1.5 text-sm text-zinc-500">
                <div class="flex items-center gap-1.5"><dt class="sr-only">Ort</dt><x-sun.icon name="map-pin" class="size-4 shrink-0" /><dd>{{ $job['location'] }}</dd></div>
                <div class="flex items-center gap-1.5"><dt class="sr-only">Anstellungsart</dt><x-sun.icon name="clock" class="size-4 shrink-0" /><dd>{{ $job['type'] }}</dd></div>
                <div class="flex items-center gap-1.5"><dt class="sr-only">Gehalt</dt><x-sun.icon name="euro" class="size-4 shrink-0" /><dd class="{{ $job['salary'] ? 'text-zinc-900 font-medium' : '' }}">{{ $job['salary'] ?? 'Keine Angabe' }}</dd></div>
              </dl>
              <div class="mt-3 flex flex-wrap gap-2">
                @foreach($job['tags'] as $tag)
                  <span class="pill">{{ $tag }}</span>
                @endforeach
              </div>
              <p class="mt-3 text-sm text-zinc-500">{{ $job['age'] }}</p>
            </div>
          </div>
          <div class="flex lg:flex-col gap-2 lg:w-44 shrink-0">
            <a href="#" class="btn-primary flex-1">Bewerben</a>
            <a href="#" class="btn-secondary flex-1">Details</a>
          </div>
        </article>
      @endforeach

      <nav aria-label="Seiten" class="mt-4 flex flex-wrap items-center justify-center sm:justify-between gap-3">
        <span class="btn-ghost opacity-40 pointer-events-none" aria-disabled="true">Zurück</span>
        <ul class="order-first sm:order-none w-full sm:w-auto flex items-center justify-center gap-1">
          <li><a href="#" aria-current="page" class="size-11 rounded-full bg-brand text-white font-semibold flex items-center justify-center">1</a></li>
          <li><a href="#" class="{{ $pageLink }}">2</a></li>
          <li class="hidden sm:block"><a href="#" class="{{ $pageLink }}">3</a></li>
          <li class="text-zinc-400 px-1">…</li>
          <li><a href="#" class="{{ $pageLink }}">92</a></li>
        </ul>
        <a href="#" class="btn-ghost">Weiter</a>
      </nav>
    </div>

    <!-- ===== RECHTE SPALTE: Arbeitgeber-Verkauf ===== -->
    <aside class="hidden xl:block">
      <div class="sticky top-20 flex flex-col gap-4">
        <div class="card p-5 bg-brand-50 border-brand-100">
          <h2 class="text-lg font-semibold text-zinc-900">Du suchst Personal?</h2>
          <p class="mt-1 text-zinc-700 leading-relaxed">Deine Anzeige erreicht Elektriker, die ohnehin auf diesem Portal unterwegs sind.</p>
          <a href="#" class="mt-4 btn-primary w-full">Stelle ausschreiben</a>
          <p class="mt-2 text-sm text-zinc-500 text-center">30 Tage online · ab 99 €</p>
        </div>
        <div class="card p-5">
          <h2 class="font-semibold text-zinc-900">Job-Mail</h2>
          <p class="mt-1 text-sm text-zinc-500">Neue Stellen in deiner Nähe, einmal pro Woche. Jederzeit abbestellbar.</p>
          <form class="mt-3 grid gap-2" onsubmit="return false">
            <label class="sr-only" for="alert-mail">E-Mail</label>
            <input id="alert-mail" type="email" class="input" placeholder="deine@mail.de">
            <button type="button" class="btn-secondary w-full">Job-Mail aktivieren</button>
          </form>
        </div>
        <x-ad-slot position="sidebar_sticky" />
      </div>
    </aside>
  </div>

  <!-- ===== ARBEITGEBER-CTA ===== -->
  <section class="mt-12 md:mt-16 card p-5 md:p-8 bg-brand-50 border-brand-100">
    <div class="lg:flex lg:items-start lg:gap-10">
      <div class="flex-1 max-w-prose">
        <h2 class="text-2xl font-semibold text-zinc-900">Du suchst Elektriker fürs eigene Team?</h2>
        <p class="mt-2 text-zinc-700 leading-relaxed">Auf {{ $portalName }} sind Menschen unterwegs, die sich ohnehin für Elektrohandwerk interessieren – Gesellen, die den Betrieb wechseln, und Azubis, die den ersten suchen. Deine Anzeige läuft 30 Tage, erscheint bei Google for Jobs und ist mit deinem Firmenprofil verknüpft.</p>
        <ul class="mt-4 space-y-2 text-zinc-700">
          @foreach($benefits as $benefit)
            <li class="flex items-start gap-2"><x-sun.icon name="check" class="icon text-brand mt-0.5" /><span>{{ $benefit }}</span></li>
          @endforeach
        </ul>
      </div>
      <div class="mt-6 lg:mt-0 lg:w-72 shrink-0 card p-5">
        <p class="text-sm text-zinc-500">Einzelanzeige</p>
        <p class="mt-1 text-3xl font-bold tracking-tight text-zinc-900">99 €<span class="text-base font-normal text-zinc-500"> / 30 Tage</span></p>
        <p class="mt-1 text-sm text-zinc-500">zzgl. MwSt. Ab 3 Anzeigen günstiger.</p>
        <a href="#" class="mt-4 btn-primary w-full">Stelle ausschreiben</a>
        <a href="#" class="mt-2 btn-ghost w-full">Preise ansehen</a>
        <p class="mt-3 pt-3 border-t border-zinc-200 text-sm text-zinc-500">Fragen? <a href="mailto:info@widimedia.com" class="text-brand hover:underline">Schreib uns</a> – Antwort werktags am selben Tag.</p>
      </div>
    </div>
  </section>

  <!-- ===== JOB-MAIL (Mobile/Tablet) ===== -->
  <section class="mt-12 md:mt-16 card p-5 md:p-8 xl:hidden">
    <h2 class="text-2xl font-semibold text-zinc-900">Job-Mail einrichten</h2>
    <p class="mt-2 text-zinc-700 leading-relaxed">Neue Stellen in deiner Nähe, einmal pro Woche per Mail. Jederzeit mit einem Klick abbestellbar.</p>
    <form class="mt-4 grid gap-3 sm:grid-cols-[1fr_1fr_auto]" onsubmit="return false">
      <label class="sr-only" for="alert-ort">Ort oder PLZ</label>
      <input id="alert-ort" class="input" placeholder="Ort oder PLZ">
      <label class="sr-only" for="alert-mail-m">E-Mail</label>
      <input id="alert-mail-m" type="email" class="input" placeholder="deine@mail.de">
      <button type="button" class="btn-primary">Aktivieren</button>
    </form>
  </section>

  <!-- ===== SEO-LINKLISTEN ===== -->
  @foreach(['Jobs nach Beruf' => $professions, 'Jobs nach Stadt' => $cities] as $heading => $links)
    <section class="mt-12 md:mt-16">
      <h2 class="text-2xl font-semibold text-zinc-900">{{ $heading }}</h2>
      <ul class="mt-4 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-x-6 gap-y-1">
        @foreach($links as [$label, $count])
          <li><a href="#" class="flex justify-between items-center min-h-11 gap-4 hover:text-brand"><span class="font-medium text-zinc-900">{{ $label }}</span><span class="text-sm text-zinc-500">{{ $count }}</span></a></li>
        @endforeach
      </ul>
    </section>
  @endforeach

  <!-- ===== SEO-TEXT ===== -->
  <section class="mt-12 md:mt-16 max-w-prose">
    <h2 class="text-2xl font-semibold text-zinc-900">Als Elektriker den Betrieb wechseln</h2>
    <p class="mt-4 text-base leading-relaxed">Elektrofachkräfte werden überall gesucht – die Frage ist nicht, ob du eine Stelle findest, sondern welche. Achte deshalb weniger auf das Gehalt allein und mehr auf die Punkte darum herum: Wie weit ist die Baustelle im Schnitt weg? Gibt es einen Servicewagen, den du mit nach Hause nimmst? Wer zahlt Weiterbildungen, etwa die KNX-Zertifizierung oder den Meister?</p>
    <p class="mt-4 text-base leading-relaxed">Anzeigen ohne Gehaltsangabe sind kein Ausschlusskriterium, aber ein Grund nachzufragen – über den Filter „Mit Gehaltsangabe“ siehst du zuerst die Betriebe, die Farbe bekennen. Jede Anzeige hier ist mit dem Firmenprofil verknüpft: Ein Blick auf die Bewertungen zeigt dir oft mehr über den Betrieb als der Anzeigentext.</p>
  </section>

</div>
@endsection
