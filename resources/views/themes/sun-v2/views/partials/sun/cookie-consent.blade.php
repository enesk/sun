{{--
    LEGAL-3: Cookie-Hinweis im Stil von sun-v2. Inhalt und Speicherlogik wie
    partials/cookie-consent im Default-Theme, Verhalten in
    resources/views/themes/sun-v2/js/modules/cookie-consent.js (ohne Alpine).
    DSGVO: alle drei Knoepfe gleich gross, Statistik und Marketing vorab aus.
--}}
@php
    $cookieCategories = [
        [
            'key' => null,
            'title' => 'Notwendig',
            'text' => 'Technisch erforderliche Cookies für Login, Formulare und Sicherheit. Ohne diese funktioniert die Website nicht.',
            'details' => [
                'Session-Cookie — hält deine Sitzung aktiv (Ablauf: Sitzungsende)',
                'CSRF-Token — schützt vor Cross-Site-Angriffen (Ablauf: Sitzungsende)',
                'Cookie-Einstellungen — speichert deine Auswahl (Ablauf: 12 Monate)',
            ],
        ],
        [
            'key' => 'statistics',
            'title' => 'Statistik',
            'text' => 'Hilft uns zu verstehen, wie Besucher die Website nutzen. Die Daten werden anonymisiert erhoben (Google Analytics).',
            'details' => [
                'Google Analytics (_ga, _ga_*) — anonymisierte Besucherstatistiken (Ablauf: 2 Jahre)',
            ],
        ],
        [
            'key' => 'marketing',
            'title' => 'Marketing',
            'text' => 'Werden genutzt, um dir relevante Werbung und Inhalte anzuzeigen. Aktuell setzen wir keine Marketing-Cookies ein.',
            'details' => [],
        ],
    ];
@endphp

<div data-cookie-banner hidden class="fixed inset-x-0 bottom-0 z-50 p-4" role="region" aria-label="Cookie-Hinweis">
  <div class="container-portal">
    <div class="card shadow-lg p-4 md:p-5 flex flex-col lg:flex-row lg:items-center gap-4">
      <p class="text-sm leading-relaxed text-zinc-700 flex-1">
        Wir verwenden Cookies, damit das Portal zuverlässig funktioniert, und – nur mit deiner Zustimmung – für anonyme Statistik.
        <a href="{{ route('portal.datenschutz') }}" class="text-brand font-medium hover:underline">Datenschutzerklärung</a>
      </p>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 shrink-0">
        <button type="button" class="btn-secondary" data-cookie-action="settings">Einstellungen</button>
        <button type="button" class="btn-secondary" data-cookie-action="essential">Nur Notwendige</button>
        <button type="button" class="btn-primary" data-cookie-action="all">Alle akzeptieren</button>
      </div>
    </div>
  </div>
</div>

<dialog data-cookie-modal class="card m-auto w-[calc(100%-2rem)] max-w-2xl p-0 backdrop:bg-zinc-900/50" aria-labelledby="cookie-modal-title">
  <div class="p-5 md:p-6 flex items-center justify-between gap-4 border-b border-zinc-200">
    <h2 id="cookie-modal-title" class="text-xl font-semibold text-zinc-900">Datenschutz-Einstellungen</h2>
    <button type="button" class="btn-ghost px-3" data-cookie-action="close" aria-label="Schließen">
      <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
    </button>
  </div>
  <div class="p-5 md:p-6 flex flex-col gap-4">
    <p class="text-sm leading-relaxed text-zinc-700">
      Notwendige Cookies brauchen wir für den Betrieb der Website. Statistik- und Marketing-Cookies helfen uns, das Portal zu verbessern. Du kannst deine Auswahl jederzeit ändern.
      <a href="{{ route('portal.datenschutz') }}" class="text-brand font-medium hover:underline">Mehr erfahren</a>
    </p>
    @foreach($cookieCategories as $category)
      <div class="rounded-xl border border-zinc-200 p-4">
        <div class="flex items-start justify-between gap-4">
          <div class="min-w-0">
            <h3 class="font-semibold text-zinc-900">{{ $category['title'] }}</h3>
            <p class="mt-1 text-sm text-zinc-500">{{ $category['text'] }}</p>
          </div>
          @if($category['key'])
            <button type="button" role="switch" aria-checked="false" aria-label="{{ $category['title'] }}" data-cookie-toggle="{{ $category['key'] }}"
                    class="group relative inline-flex h-7 w-12 shrink-0 items-center rounded-full bg-zinc-300 transition-colors aria-checked:bg-brand focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-offset-2">
              <span class="inline-block size-5 translate-x-1 rounded-full bg-white shadow-lg transition-transform group-aria-checked:translate-x-6"></span>
            </button>
          @else
            <span class="pill-brand shrink-0">Immer aktiv</span>
          @endif
        </div>
        @if($category['details'])
          <details class="mt-3 text-sm">
            <summary class="text-brand font-medium">Details anzeigen</summary>
            <ul class="mt-2 space-y-1 text-zinc-500">
              @foreach($category['details'] as $detail)
                <li>{{ $detail }}</li>
              @endforeach
            </ul>
          </details>
        @endif
      </div>
    @endforeach
  </div>
  <div class="p-5 md:p-6 grid grid-cols-1 sm:grid-cols-3 gap-2 border-t border-zinc-200">
    <button type="button" class="btn-secondary" data-cookie-action="essential">Nur Notwendige</button>
    <button type="button" class="btn-secondary" data-cookie-action="save">Auswahl speichern</button>
    <button type="button" class="btn-primary" data-cookie-action="all">Alle akzeptieren</button>
  </div>
</dialog>
