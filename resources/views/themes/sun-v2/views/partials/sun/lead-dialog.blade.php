{{--
    Anfrage-Dialog "Angebot anfragen" (Vorlage sun-v2--profil.html, Abschnitt ANFRAGE-DIALOG).
    Headless an die Funnel-Runtime-API angebunden (Leadsystem, Ticket #17 dort):
    Skript resources/views/themes/sun-v2/js/modules/lead-dialog.js, Basis-URL und
    oeffentlicher Funnel-Token aus config('themes.sun-v2.lead'). Option-Werte sind die
    Schluessel aus der Funnel-Vorlage "elektrikerportal", die Beschriftung bleibt wie im Design.
    Jede Anfrage traegt zusaetzlich den Antwortschluessel firmenprofil (id, slug, name, url,
    portal); das Leadsystem speichert unbekannte Schluessel 1:1 als lead_answers.
    Erwartet $company.
--}}
@php
    $leadCompanyKey = [
        'id' => $company->id,
        'slug' => $company->slug,
        'name' => $company->name,
        'url' => $company->portal_url,
        'portal' => $currentTenant->name ?? config('app.name'),
    ];
@endphp
<div id="leadDialog" class="hidden fixed inset-0 z-50" role="dialog" aria-modal="true" aria-labelledby="leadTitle"
     data-lead-api="{{ config('themes.sun-v2.lead.api_url') }}" data-lead-token="{{ config('themes.sun-v2.lead.funnel_token') }}" data-company="{{ $company->name }}"
     data-company-key="{{ json_encode($leadCompanyKey, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}">
  <div class="absolute inset-0 bg-zinc-900/40" data-close-lead></div>
  <div class="absolute inset-x-0 bottom-0 sm:inset-0 sm:flex sm:items-center sm:justify-center sm:p-4">
    <form class="bg-white rounded-t-2xl sm:rounded-2xl w-full sm:max-w-lg max-h-[92dvh] sm:max-h-[90vh] flex flex-col shadow-lg" id="leadForm" novalidate>

      <div class="flex items-start justify-between gap-4 p-5 border-b border-zinc-200">
        <div>
          <p class="text-sm text-zinc-500" id="leadStep">Schritt 1 von 3</p>
          <h2 id="leadTitle" class="text-lg font-semibold text-zinc-900">Was brauchst du?</h2>
        </div>
        <button type="button" class="btn-ghost px-3 -mr-2 -mt-1" data-close-lead aria-label="Schließen"><x-sun.icon name="x" class="icon" /></button>
      </div>

      <div class="p-5 overflow-y-auto flex-1" data-lead-body>
        {{-- Honigtopf: bleibt leer, gefuellt verwirft der Server die Anfrage still --}}
        <div class="sr-only" aria-hidden="true"><label for="lead-website">Website</label><input id="lead-website" type="text" name="website" tabindex="-1" autocomplete="off"></div>

        <!-- Schritt 1: Was -->
        <fieldset data-step="1">
          <legend class="sr-only">Leistung</legend>
          <div class="flex flex-wrap gap-2">
            <label class="cursor-pointer"><input type="radio" name="leistung" value="elektroinstallation" class="peer sr-only"><span class="pill-link peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:border-brand peer-focus-visible:ring-2 peer-focus-visible:ring-brand">Elektroinstallation</span></label><label class="cursor-pointer"><input type="radio" name="leistung" value="reparatur" class="peer sr-only"><span class="pill-link peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:border-brand peer-focus-visible:ring-2 peer-focus-visible:ring-brand">Reparatur / Störung</span></label><label class="cursor-pointer"><input type="radio" name="leistung" value="smart_home" class="peer sr-only"><span class="pill-link peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:border-brand peer-focus-visible:ring-2 peer-focus-visible:ring-brand">Smart Home / KNX</span></label><label class="cursor-pointer"><input type="radio" name="leistung" value="e_check" class="peer sr-only"><span class="pill-link peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:border-brand peer-focus-visible:ring-2 peer-focus-visible:ring-brand">E-Check</span></label><label class="cursor-pointer"><input type="radio" name="leistung" value="wallbox" class="peer sr-only"><span class="pill-link peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:border-brand peer-focus-visible:ring-2 peer-focus-visible:ring-brand">Wallbox</span></label><label class="cursor-pointer"><input type="radio" name="leistung" value="hausgeraet" class="peer sr-only"><span class="pill-link peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:border-brand peer-focus-visible:ring-2 peer-focus-visible:ring-brand">Hausgerät</span></label><label class="cursor-pointer"><input type="radio" name="leistung" value="sicherungskasten" class="peer sr-only"><span class="pill-link peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:border-brand peer-focus-visible:ring-2 peer-focus-visible:ring-brand">Sicherungskasten</span></label><label class="cursor-pointer"><input type="radio" name="leistung" value="sonstiges" class="peer sr-only"><span class="pill-link peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:border-brand peer-focus-visible:ring-2 peer-focus-visible:ring-brand">Sonstiges</span></label>
          </div>
          <p class="mt-1 text-sm text-red-600 hidden" data-error-for="leistung"></p>
          <label for="beschreibung" class="block mt-5 text-sm font-medium text-zinc-700 mb-1">Beschreib kurz dein Anliegen</label>
          <textarea id="beschreibung" name="beschreibung" rows="4" class="input py-3 h-auto" placeholder="z. B. Im Bad fliegt seit gestern die Sicherung, sobald der Föhn läuft."></textarea>
          <p class="mt-1 text-sm text-zinc-500" data-hint-for="beschreibung">Je konkreter, desto besser kann der Betrieb einschätzen, was zu tun ist.</p>
        </fieldset>

        <!-- Schritt 2: Wo und wann -->
        <fieldset data-step="2" class="hidden">
          <legend class="sr-only">Ort und Zeitpunkt</legend>
          <label for="plz" class="block text-sm font-medium text-zinc-700 mb-1">Postleitzahl des Einsatzorts</label>
          <input id="plz" name="plz" class="input max-w-40" inputmode="numeric" pattern="[0-9]{5}" placeholder="85376" autocomplete="postal-code">
          <p class="mt-1 text-sm text-red-600 hidden" data-error-for="plz"></p>
          <p class="block mt-5 text-sm font-medium text-zinc-700 mb-2">Wann soll es losgehen?</p>
          <div class="flex flex-wrap gap-2">
            <label class="cursor-pointer"><input type="radio" name="wann" value="notfall" class="peer sr-only"><span class="pill-link peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:border-brand peer-focus-visible:ring-2 peer-focus-visible:ring-brand">Notfall – heute</span></label><label class="cursor-pointer"><input type="radio" name="wann" value="diese_woche" class="peer sr-only" checked><span class="pill-link peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:border-brand peer-focus-visible:ring-2 peer-focus-visible:ring-brand">Diese Woche</span></label><label class="cursor-pointer"><input type="radio" name="wann" value="vier_wochen" class="peer sr-only"><span class="pill-link peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:border-brand peer-focus-visible:ring-2 peer-focus-visible:ring-brand">In den nächsten 4 Wochen</span></label><label class="cursor-pointer"><input type="radio" name="wann" value="flexibel" class="peer sr-only"><span class="pill-link peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:border-brand peer-focus-visible:ring-2 peer-focus-visible:ring-brand">Bin flexibel</span></label>
          </div>
          <p class="mt-1 text-sm text-red-600 hidden" data-error-for="wann"></p>
          <p class="block mt-5 text-sm font-medium text-zinc-700 mb-2">Um welches Objekt geht es?</p>
          <div class="flex flex-wrap gap-2">
            <label class="cursor-pointer"><input type="radio" name="objekt" value="wohnung" class="peer sr-only"><span class="pill-link peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:border-brand peer-focus-visible:ring-2 peer-focus-visible:ring-brand">Wohnung</span></label><label class="cursor-pointer"><input type="radio" name="objekt" value="haus" class="peer sr-only"><span class="pill-link peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:border-brand peer-focus-visible:ring-2 peer-focus-visible:ring-brand">Haus</span></label><label class="cursor-pointer"><input type="radio" name="objekt" value="gewerbe" class="peer sr-only"><span class="pill-link peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:border-brand peer-focus-visible:ring-2 peer-focus-visible:ring-brand">Gewerbe</span></label>
          </div>
          <p class="mt-1 text-sm text-red-600 hidden" data-error-for="objekt"></p>
        </fieldset>

        <!-- Schritt 3: Kontakt -->
        <fieldset data-step="3" class="hidden">
          <legend class="sr-only">Kontaktdaten</legend>
          <label for="name" class="block text-sm font-medium text-zinc-700 mb-1">Dein Name</label>
          <input id="name" name="name" class="input" autocomplete="name" required>
          <p class="mt-1 text-sm text-red-600 hidden" data-error-for="name"></p>
          <label for="tel" class="block mt-4 text-sm font-medium text-zinc-700 mb-1">Telefonnummer</label>
          <input id="tel" name="tel" type="tel" class="input" autocomplete="tel" inputmode="tel" required>
          <p class="mt-1 text-sm text-zinc-500" id="telHint" data-hint-for="telefon">Der Betrieb ruft dich zurück – darum brauchen wir die Nummer.</p>
          <p class="block mt-4 text-sm font-medium text-zinc-700 mb-2">Wann erreicht man dich am besten?</p>
          <div class="flex flex-wrap gap-2">
            <label class="cursor-pointer"><input type="checkbox" name="erreichbar[]" value="vormittags" class="peer sr-only"><span class="pill-link peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:border-brand peer-focus-visible:ring-2 peer-focus-visible:ring-brand">Vormittags</span></label><label class="cursor-pointer"><input type="checkbox" name="erreichbar[]" value="nachmittags" class="peer sr-only"><span class="pill-link peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:border-brand peer-focus-visible:ring-2 peer-focus-visible:ring-brand">Nachmittags</span></label><label class="cursor-pointer"><input type="checkbox" name="erreichbar[]" value="ab_17_uhr" class="peer sr-only"><span class="pill-link peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:border-brand peer-focus-visible:ring-2 peer-focus-visible:ring-brand">Ab 17 Uhr</span></label>
          </div>
          <p class="mt-1 text-sm text-red-600 hidden" data-error-for="erreichbar"></p>
          <label for="email" class="block mt-4 text-sm font-medium text-zinc-700 mb-1">E-Mail <span class="text-zinc-500 font-normal">(optional)</span></label>
          <input id="email" name="email" type="email" class="input" autocomplete="email">
          <p class="mt-1 text-sm text-red-600 hidden" data-error-for="email"></p>
          <label class="mt-5 flex items-start gap-3 cursor-pointer">
            <input type="checkbox" name="weitere" value="1" class="mt-1 size-5 rounded border-zinc-300 text-brand focus:ring-brand" checked>
            <span class="text-sm text-zinc-700">Falls {{ $company->name }} keine Kapazität hat: bis zu zwei weitere {{ config('themes.sun-v2.search.branch_plural') }} in meiner Nähe dürfen sich ebenfalls melden.</span>
          </label>
          <p class="mt-1 text-sm text-red-600 hidden" data-error-for="weitere_betriebe"></p>
        </fieldset>

        <!-- Erfolg -->
        <div data-step="done" class="hidden text-center py-6 flex flex-col items-center gap-3">
          <span class="size-14 rounded-full bg-brand-50 text-brand flex items-center justify-center"><x-sun.icon name="check" class="size-7" /></span>
          <h3 class="text-lg font-semibold text-zinc-900">Anfrage gesendet</h3>
          <p class="text-zinc-500 max-w-sm">{{ $company->name }} meldet sich bei dir – meist am selben Werktag. Du bekommst eine SMS, sobald der Betrieb deine Anfrage gesehen hat.</p>
        </div>
      </div>

      <p class="px-5 pt-3 text-sm text-red-600 hidden" role="alert" data-lead-error></p>
      <div class="p-5 border-t border-zinc-200 flex gap-2" data-step-footer>
        <button type="button" class="btn-ghost hidden px-3" data-prev>Zurück</button>
        <button type="button" class="btn-primary flex-1 whitespace-nowrap" data-next>Weiter</button>
      </div>
      <p class="px-5 pb-5 -mt-2 text-xs text-zinc-500" data-step-footer data-privacy>Deine Daten gehen nur an den Betrieb, der dich zurückruft. <a href="{{ route('portal.datenschutz') }}" class="underline">Datenschutz</a></p>
    </form>
  </div>
</div>
