{{--
    Anfrage-Dialog auf dem Firmenprofil (Vorlage sun-v2--profil.html, Abschnitt ANFRAGE-DIALOG).
    Headless an die Funnel-Runtime-API angebunden (Leadsystem, Ticket #17 dort):
    Skript resources/views/themes/sun-v2/js/modules/lead-dialog.js, Basis-URL und
    oeffentlicher Funnel-Token aus config('themes.sun-v2.lead'). Option-Werte sind die
    Schluessel aus der Funnel-Vorlage "elektrikerportal", deren Beschriftung ist Inhalt je Branche
    und bleibt hier. Alle uebrigen Texte kommen aus portal.request.* bzw. portal.errors.request.*;
    was das Skript zur Laufzeit setzt, liegt fertig uebersetzt in data-texts.
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
    $firma = ['firma' => $company->name];
    $leadTexts = [
        'titles' => [
            1 => __('portal.request.title_what'),
            2 => __('portal.request.title_where'),
            3 => __('portal.request.title_contact'),
            'done' => __('portal.request.title_done'),
        ],
        'steps' => [
            1 => __('portal.request.step', ['schritt' => 1]),
            2 => __('portal.request.step', ['schritt' => 2]),
            3 => __('portal.request.step', ['schritt' => 3]),
            'done' => __('portal.request.step_done', $firma),
        ],
        'next' => __('portal.request.next'),
        'submit' => __('portal.profile.request_cta'),
        'close' => __('portal.request.close'),
        'telHint' => __('portal.request.contact.phone_hint', $firma),
        'errors' => [
            'offline' => __('portal.errors.request.offline'),
            'rateLimited' => __('portal.errors.request.rate_limited'),
            'forbidden' => __('portal.errors.request.forbidden'),
            'unavailable' => __('portal.errors.request.unavailable'),
            'generic' => __('portal.errors.request.generic'),
            'contactMissing' => __('portal.errors.request.contact_missing', $firma),
        ],
    ];
@endphp
<div id="leadDialog" class="hidden fixed inset-0 z-50" role="dialog" aria-modal="true" aria-labelledby="leadTitle"
     data-lead-api="{{ config('themes.sun-v2.lead.api_url') }}" data-lead-token="{{ config('themes.sun-v2.lead.funnel_token') }}" data-company="{{ $company->name }}"
     data-company-key="{{ json_encode($leadCompanyKey, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}"
     data-texts="{{ json_encode($leadTexts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}">
  <div class="absolute inset-0 bg-zinc-900/40" data-close-lead></div>
  <div class="absolute inset-x-0 bottom-0 sm:inset-0 sm:flex sm:items-center sm:justify-center sm:p-4">
    <form class="bg-white rounded-t-2xl sm:rounded-2xl w-full sm:max-w-lg max-h-[92dvh] sm:max-h-[90vh] flex flex-col shadow-lg" id="leadForm" novalidate>

      <div class="flex items-start justify-between gap-4 p-5 border-b border-zinc-200">
        <div>
          <p class="text-sm text-zinc-500" id="leadStep">{{ $leadTexts['steps'][1] }}</p>
          <h2 id="leadTitle" class="text-lg font-semibold text-zinc-900">{{ $leadTexts['titles'][1] }}</h2>
        </div>
        <button type="button" class="btn-ghost px-3 -mr-2 -mt-1" data-close-lead aria-label="{{ __('portal.request.close') }}"><x-sun.icon name="x" class="icon" /></button>
      </div>

      <div class="p-5 overflow-y-auto flex-1" data-lead-body>
        {{-- Honigtopf: bleibt leer, gefuellt verwirft der Server die Anfrage still --}}
        <div class="sr-only" aria-hidden="true"><label for="lead-website">{{ __('portal.request.honeypot_label') }}</label><input id="lead-website" type="text" name="website" tabindex="-1" autocomplete="off"></div>

        <!-- Schritt 1: Was -->
        <fieldset data-step="1">
          <legend class="sr-only">{{ __('portal.request.what.legend') }}</legend>
          <div class="flex flex-wrap gap-2">
            <label class="cursor-pointer"><input type="radio" name="leistung" value="elektroinstallation" class="peer sr-only"><span class="pill-link peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:border-brand peer-focus-visible:ring-2 peer-focus-visible:ring-brand">{{ __('portal.request.options.leistung.elektroinstallation') }}</span></label><label class="cursor-pointer"><input type="radio" name="leistung" value="reparatur" class="peer sr-only"><span class="pill-link peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:border-brand peer-focus-visible:ring-2 peer-focus-visible:ring-brand">{{ __('portal.request.options.leistung.reparatur') }}</span></label><label class="cursor-pointer"><input type="radio" name="leistung" value="smart_home" class="peer sr-only"><span class="pill-link peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:border-brand peer-focus-visible:ring-2 peer-focus-visible:ring-brand">{{ __('portal.request.options.leistung.smart_home') }}</span></label><label class="cursor-pointer"><input type="radio" name="leistung" value="e_check" class="peer sr-only"><span class="pill-link peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:border-brand peer-focus-visible:ring-2 peer-focus-visible:ring-brand">{{ __('portal.request.options.leistung.e_check') }}</span></label><label class="cursor-pointer"><input type="radio" name="leistung" value="wallbox" class="peer sr-only"><span class="pill-link peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:border-brand peer-focus-visible:ring-2 peer-focus-visible:ring-brand">{{ __('portal.request.options.leistung.wallbox') }}</span></label><label class="cursor-pointer"><input type="radio" name="leistung" value="hausgeraet" class="peer sr-only"><span class="pill-link peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:border-brand peer-focus-visible:ring-2 peer-focus-visible:ring-brand">{{ __('portal.request.options.leistung.hausgeraet') }}</span></label><label class="cursor-pointer"><input type="radio" name="leistung" value="sicherungskasten" class="peer sr-only"><span class="pill-link peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:border-brand peer-focus-visible:ring-2 peer-focus-visible:ring-brand">{{ __('portal.request.options.leistung.sicherungskasten') }}</span></label><label class="cursor-pointer"><input type="radio" name="leistung" value="sonstiges" class="peer sr-only"><span class="pill-link peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:border-brand peer-focus-visible:ring-2 peer-focus-visible:ring-brand">{{ __('portal.request.options.leistung.sonstiges') }}</span></label>
          </div>
          <p class="mt-1 text-sm text-red-600 hidden" data-error-for="leistung"></p>
          <label for="beschreibung" class="block mt-5 text-sm font-medium text-zinc-700 mb-1">{{ __('portal.request.what.description_label') }}</label>
          <textarea id="beschreibung" name="beschreibung" rows="4" class="input py-3 h-auto" placeholder="{{ __('portal.request.what.description_placeholder') }}"></textarea>
          <p class="mt-1 text-sm text-zinc-500" data-hint-for="beschreibung">{{ __('portal.request.what.description_hint', $firma) }}</p>
        </fieldset>

        <!-- Schritt 2: Wo und wann -->
        <fieldset data-step="2" class="hidden">
          <legend class="sr-only">{{ __('portal.request.where.legend') }}</legend>
          <label for="plz" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.request.where.zip_label') }}</label>
          <input id="plz" name="plz" class="input max-w-40" inputmode="numeric" pattern="[0-9]{5}" placeholder="85376" autocomplete="postal-code">
          <p class="mt-1 text-sm text-red-600 hidden" data-error-for="plz"></p>
          <p class="block mt-5 text-sm font-medium text-zinc-700 mb-2">{{ __('portal.request.where.when_label') }}</p>
          <div class="flex flex-wrap gap-2">
            <label class="cursor-pointer"><input type="radio" name="wann" value="notfall" class="peer sr-only"><span class="pill-link peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:border-brand peer-focus-visible:ring-2 peer-focus-visible:ring-brand">{{ __('portal.request.options.wann.notfall') }}</span></label><label class="cursor-pointer"><input type="radio" name="wann" value="diese_woche" class="peer sr-only" checked><span class="pill-link peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:border-brand peer-focus-visible:ring-2 peer-focus-visible:ring-brand">{{ __('portal.request.options.wann.diese_woche') }}</span></label><label class="cursor-pointer"><input type="radio" name="wann" value="vier_wochen" class="peer sr-only"><span class="pill-link peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:border-brand peer-focus-visible:ring-2 peer-focus-visible:ring-brand">{{ __('portal.request.options.wann.vier_wochen') }}</span></label><label class="cursor-pointer"><input type="radio" name="wann" value="flexibel" class="peer sr-only"><span class="pill-link peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:border-brand peer-focus-visible:ring-2 peer-focus-visible:ring-brand">{{ __('portal.request.options.wann.flexibel') }}</span></label>
          </div>
          <p class="mt-1 text-sm text-red-600 hidden" data-error-for="wann"></p>
          <p class="block mt-5 text-sm font-medium text-zinc-700 mb-2">{{ __('portal.request.where.object_label') }}</p>
          <div class="flex flex-wrap gap-2">
            <label class="cursor-pointer"><input type="radio" name="objekt" value="wohnung" class="peer sr-only"><span class="pill-link peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:border-brand peer-focus-visible:ring-2 peer-focus-visible:ring-brand">{{ __('portal.request.options.objekt.wohnung') }}</span></label><label class="cursor-pointer"><input type="radio" name="objekt" value="haus" class="peer sr-only"><span class="pill-link peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:border-brand peer-focus-visible:ring-2 peer-focus-visible:ring-brand">{{ __('portal.request.options.objekt.haus') }}</span></label><label class="cursor-pointer"><input type="radio" name="objekt" value="gewerbe" class="peer sr-only"><span class="pill-link peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:border-brand peer-focus-visible:ring-2 peer-focus-visible:ring-brand">{{ __('portal.request.options.objekt.gewerbe') }}</span></label>
          </div>
          <p class="mt-1 text-sm text-red-600 hidden" data-error-for="objekt"></p>
        </fieldset>

        <!-- Schritt 3: Kontakt -->
        <fieldset data-step="3" class="hidden">
          <legend class="sr-only">{{ __('portal.request.contact.legend') }}</legend>
          <label for="name" class="block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.request.contact.name_label') }}</label>
          <input id="name" name="name" class="input" autocomplete="name" required>
          <p class="mt-1 text-sm text-red-600 hidden" data-error-for="name"></p>
          <label for="tel" class="block mt-4 text-sm font-medium text-zinc-700 mb-1">{{ __('portal.request.contact.phone_label') }}</label>
          <input id="tel" name="tel" type="tel" class="input" autocomplete="tel" inputmode="tel" required>
          <p class="mt-1 text-sm text-zinc-500" id="telHint" data-hint-for="telefon">{{ $leadTexts['telHint'] }}</p>
          <p class="block mt-4 text-sm font-medium text-zinc-700 mb-2">{{ __('portal.request.contact.reachable_label') }}</p>
          <div class="flex flex-wrap gap-2">
            <label class="cursor-pointer"><input type="checkbox" name="erreichbar[]" value="vormittags" class="peer sr-only"><span class="pill-link peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:border-brand peer-focus-visible:ring-2 peer-focus-visible:ring-brand">{{ __('portal.request.options.erreichbar.vormittags') }}</span></label><label class="cursor-pointer"><input type="checkbox" name="erreichbar[]" value="nachmittags" class="peer sr-only"><span class="pill-link peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:border-brand peer-focus-visible:ring-2 peer-focus-visible:ring-brand">{{ __('portal.request.options.erreichbar.nachmittags') }}</span></label><label class="cursor-pointer"><input type="checkbox" name="erreichbar[]" value="ab_17_uhr" class="peer sr-only"><span class="pill-link peer-checked:bg-brand-50 peer-checked:text-brand-700 peer-checked:border-brand peer-focus-visible:ring-2 peer-focus-visible:ring-brand">{{ __('portal.request.options.erreichbar.ab_17_uhr') }}</span></label>
          </div>
          <p class="mt-1 text-sm text-red-600 hidden" data-error-for="erreichbar"></p>
          <label for="email" class="block mt-4 text-sm font-medium text-zinc-700 mb-1">{{ __('portal.request.contact.email_label') }} <span class="text-zinc-500 font-normal">{{ __('portal.layout.optional') }}</span></label>
          <input id="email" name="email" type="email" class="input" autocomplete="email">
          <p class="mt-1 text-sm text-red-600 hidden" data-error-for="email"></p>
          <label class="mt-5 flex items-start gap-3 cursor-pointer">
            <input type="checkbox" name="weitere" value="1" class="mt-1 size-5 rounded border-zinc-300 text-brand focus:ring-brand" checked>
            <span class="text-sm text-zinc-700">{{ __('portal.request.contact.more_businesses', $firma) }}</span>
          </label>
          <p class="mt-1 text-sm text-red-600 hidden" data-error-for="weitere_betriebe"></p>
        </fieldset>

        <!-- Erfolg -->
        <div data-step="done" class="hidden text-center py-6 flex flex-col items-center gap-3">
          <span class="size-14 rounded-full bg-brand-50 text-brand flex items-center justify-center"><x-sun.icon name="check" class="size-7" /></span>
          <h3 class="text-lg font-semibold text-zinc-900">{{ __('portal.request.done.heading') }}</h3>
          <p class="text-zinc-500 max-w-sm">{{ __('portal.request.done.text', $firma) }}</p>
        </div>
      </div>

      <p class="px-5 pt-3 text-sm text-red-600 hidden" role="alert" data-lead-error></p>
      <div class="p-5 border-t border-zinc-200 flex gap-2" data-step-footer>
        <button type="button" class="btn-ghost hidden px-3" data-prev>{{ __('portal.request.back') }}</button>
        <button type="button" class="btn-primary flex-1 whitespace-nowrap" data-next>{{ $leadTexts['next'] }}</button>
      </div>
      <p class="px-5 pb-5 -mt-2 text-xs text-zinc-500" data-step-footer data-privacy>{{ __('portal.request.privacy') }} <a href="{{ route('portal.datenschutz') }}" class="underline">{{ __('portal.request.privacy_link') }}</a></p>
    </form>
  </div>
</div>
