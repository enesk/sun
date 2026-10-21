{{--
    Gemeinsamer Aufbau der Rechts- und Textseiten (Vorlage elektrikerportal-impressum.html).
    Erwartet $legal (App\Themes\SunV2\LegalPageViewComposer) sowie:
    $title, $subtitle, $crumbs (Liste [label, url|null]), $footerLinks (Liste [label, url]).
    Der Abschnittstext ist gepflegtes HTML aus den Portal-Einstellungen und wird ueber
    Utility-Varianten auf Kindelemente gestaltet (kein eigenes CSS).
    Tabellen im gepflegten Text sind breiter als 360 px (Dienstleister-Uebersicht in
    der Datenschutzerklaerung). Sie schieben deshalb in sich statt die Seite quer zu
    ziehen oder abgeschnitten zu werden (#18).
--}}
@php
    use Illuminate\Support\Facades\Route;

    $sections = $legal['sections'];
    $supportEmail = $legal['contact']['email'];
    // Bewusst kein mailto (#17): Aenderungs- und Loeschwuensche gehen ueber das
    // Formular am Eintrag, damit sie in der zentralen Inbox ankommen und dem
    // Eintrag zugeordnet werden koennen. Der Knopf fuehrt deshalb in die
    // Firmensuche, von dort auf den Eintrag und dort zum Dialog.
    $reportUrl = Route::has('portal.companies.index') ? route('portal.companies.index') : null;
    $prose = 'mt-3 [&_p]:mt-3 [&_p:first-child]:mt-0 [&_a]:text-brand [&_a]:break-words [&_a:hover]:underline [&_strong]:font-semibold [&_strong]:text-zinc-900 [&_ul]:mt-3 [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:mt-3 [&_ol]:list-decimal [&_ol]:pl-5 [&_li]:mt-1 [&_h3]:mt-5 [&_h3]:font-semibold [&_h3]:text-zinc-900 [&_table]:mt-3 [&_table]:block [&_table]:max-w-full [&_table]:overflow-x-auto [&_td]:py-1 [&_td]:pr-4';
@endphp
<div class="container-portal pt-6 pb-12 md:pb-16">
  <div class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_16rem] xl:items-start">

    <article class="min-w-0 max-w-3xl">
      <nav aria-label="{{ __('portal.layout.breadcrumb.label') }}" class="text-sm text-zinc-500 flex items-center gap-1.5">
        @foreach($crumbs as [$label, $url])
          @if($url)
            <a href="{{ $url }}" class="hover:text-brand">{{ $label }}</a><x-sun.icon name="chevron-right" class="size-4 text-zinc-400 shrink-0" />
          @else
            <span class="text-zinc-900">{{ $label }}</span>
          @endif
        @endforeach
      </nav>

      <h1 class="mt-4 text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">{{ $title }}</h1>
      <p class="mt-3 text-zinc-500">{{ implode(' · ', array_filter([$subtitle, $legal['stand'] ? __('portal.legal.as_of', ['datum' => $legal['stand']]) : null])) }}</p>

      <!-- Kontaktkarte -->
      @if($legal['contact']['address'] !== [] || $supportEmail !== '')
        <div class="card p-5 md:p-6 mt-6">
          <p class="font-semibold text-zinc-900">{{ $legal['contact']['name'] }}</p>
          @if($legal['contact']['address'] !== [])
            <address class="not-italic mt-1 text-zinc-700 leading-relaxed">{!! collect($legal['contact']['address'])->map(fn ($line) => e($line))->implode('<br>') !!}</address>
          @endif
          @if($supportEmail !== '' || $legal['contact']['phone'] !== '')
            <div class="mt-4 pt-4 border-t border-zinc-200 flex flex-col sm:flex-row sm:flex-wrap gap-3 sm:gap-x-8">
              @if($supportEmail !== '')
                <a href="mailto:{{ $supportEmail }}" class="flex items-center gap-2 min-h-11 sm:min-h-0 text-brand font-medium hover:underline"><x-sun.icon name="mail" class="size-4 shrink-0" />{{ $supportEmail }}</a>
              @endif
              @if($legal['contact']['phone'] !== '')
                <x-phone-link :number="$legal['contact']['phone']" class="flex items-center gap-2 min-h-11 sm:min-h-0 text-brand font-medium hover:underline" fallback-class="flex items-center gap-2 min-h-11 sm:min-h-0 font-medium"><x-sun.icon name="phone" class="size-4 shrink-0" />{{ $legal['contact']['phone'] }}</x-phone-link>
              @endif
            </div>
          @endif
        </div>
      @endif

      @if($sections !== [])
        <!-- Inhalt (nur Mobile/Tablet) -->
        <nav class="card p-5 mt-6 xl:hidden" aria-labelledby="toc">
          <h2 id="toc" class="font-semibold text-zinc-900">{{ __('portal.legal.toc') }}</h2>
          <ol class="mt-1 divide-y divide-zinc-100">
            @foreach($sections as $section)
              <li><a href="#{{ $section['id'] }}" class="flex items-start gap-2 min-h-11 py-2 hover:text-brand"><span class="text-zinc-400 tabular-nums">{{ $loop->iteration }}.</span><span>{{ $section['title'] }}</span></a></li>
            @endforeach
          </ol>
        </nav>
      @endif

      <div class="card p-5 md:p-8 mt-6 flex flex-col divide-y divide-zinc-200 text-base leading-relaxed">
        @if($legal['intro'] !== '')
          <div class="py-6 first:pt-0 last:pb-0 {{ $prose }}">{!! $legal['intro'] !!}</div>
        @endif

        @forelse($sections as $section)
          @php
              // Der Kasten steht im Abschnitt "Kontakt" und dort **vor** den
              // Kontaktdaten (#17): Wer eine Aenderung oder Loeschung will,
              // soll den Weg ueber den Eintrag sehen, bevor er die
              // E-Mail-Adresse abschreibt. Eine Mail laesst sich keinem
              // Eintrag zuordnen und erreicht die Bearbeitung nicht.
              $zeigtHinweis = $reportUrl
                  && ! isset($reportShown)
                  && str_starts_with(mb_strtolower($section['title']), 'kontakt');
          @endphp

          <section class="py-6 first:pt-0 last:pb-0" id="{{ $section['id'] }}" style="scroll-margin-top:6rem">
            <h2 class="text-2xl font-semibold text-zinc-900">{{ $section['title'] }}</h2>

            @if($zeigtHinweis)
              @php($reportShown = true)
              <div class="mt-4 rounded-2xl bg-brand-50 p-5">
                <p class="font-semibold text-zinc-900">{{ __('portal.legal.report_heading') }}</p>
                <p class="mt-1">{{ __('portal.legal.report_text') }}</p>
                <a href="{{ $reportUrl }}" class="mt-4 btn-secondary">{{ __('portal.legal.report') }}</a>
              </div>
            @endif

            <div class="{{ $prose }}">{!! $section['html'] !!}</div>
          </section>
        @empty
          @if($legal['intro'] === '')
            <p class="text-zinc-500">{{ __('portal.legal.pending') }}</p>
          @endif
        @endforelse
      </div>

      <div class="card p-5 md:p-6 mt-6 flex flex-wrap gap-x-6 gap-y-2 text-sm">
        @foreach($footerLinks as [$label, $url])
          <a href="{{ $url }}" class="text-brand hover:underline">{{ $label }}</a>
        @endforeach
      </div>
    </article>

    @if($sections !== [])
      <!-- Inhalt (Desktop) -->
      <aside class="hidden xl:block">
        <nav class="card p-5 sticky top-24" aria-label="{{ __('portal.legal.toc_sidebar_label') }}">
          <p class="font-semibold text-zinc-900">{{ __('portal.legal.toc_sidebar') }}</p>
          <ol class="mt-2 flex flex-col gap-1 text-sm">
            @foreach($sections as $section)
              <li><a href="#{{ $section['id'] }}" class="block py-1 hover:text-brand">{{ $section['title'] }}</a></li>
            @endforeach
          </ol>
        </nav>
      </aside>
    @endif
  </div>
</div>
