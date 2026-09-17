{{--
    Anfrage-Dialog auf dem Firmenprofil (Vorlage sun-v2--profil.html, Abschnitt ANFRAGE-DIALOG).
    Schritte und Fragen kommen aus dem Funnel-Snapshot des Portals (#26,
    App\Dto\Leads\FunnelDefinition ueber ProfileViewComposer, Token je Portal aus
    Tenant::leadFunnelToken()). Alle Schritte stehen im HTML, sichtbar ist nur der
    erste; welcher folgt, entscheidet lead-dialog.js nach der Serverantwort (#27).
    Je Fragetyp rendert components/sun/funnel/*. Die Systemfrage firmenprofil
    erscheint nicht, das Skript schickt sie aus data-company-key mit (id, slug,
    name, url, portal).
    Rahmentexte kommen aus portal.request.* bzw. portal.errors.request.*; was das
    Skript zur Laufzeit setzt, liegt fertig uebersetzt in data-texts.
    Exklusive Anfragen (#9): beim Oeffnen fragt das Skript data-route-url, ob
    die Anfrage nur an den Betrieb geht. Dann entfallen die Fragen mit
    data-marketplace-only, der Vertrauenshinweis erscheint, und der Kontaktschritt
    geht an data-exclusive-url statt an das Leadsystem.
    Erwartet $company und $funnel; ohne Funnel wird das Partial nicht eingebunden.
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
    $titles = ['done' => __('portal.request.title_done')];
    foreach ($funnel->steps as $step) {
        $titles[$step->position] = strtr($step->title, [':firma' => $company->name]);
    }
    $firstStep = $funnel->firstStep();
    // Bei Verzweigungen steht die Schrittzahl erst im Browser fest, :schritt setzt lead-dialog.js ein
    $leadTexts = [
        'titles' => $titles,
        'steps' => ['done' => __('portal.request.step_done', $firma)],
        'step' => __('portal.request.step', ['schritt' => ':schritt']),
        'next' => __('portal.request.next'),
        'submit' => __('portal.profile.request_cta'),
        'close' => __('portal.request.close'),
        'exclusive' => [
            'privacy' => __('portal.request.privacy_exclusive', $firma),
            'done' => __('portal.request.done_exclusive', $firma),
        ],
        'errors' => [
            'offline' => __('portal.errors.request.offline'),
            'rateLimited' => __('portal.errors.request.rate_limited'),
            'forbidden' => __('portal.errors.request.forbidden'),
            'unavailable' => __('portal.errors.request.unavailable'),
            'generic' => __('portal.errors.request.generic'),
        ],
    ];
@endphp
<div id="leadDialog" class="hidden fixed inset-0 z-50" role="dialog" aria-modal="true" aria-labelledby="leadTitle"
     data-lead-api="{{ config('leads.api_url') }}" data-lead-token="{{ $funnel->token }}" data-funnel-version="{{ $funnel->version }}" data-company="{{ $company->name }}" @if($funnel->contactStepPosition !== null) data-contact-step="{{ $funnel->contactStepPosition }}" @endif
     data-route-url="{{ route('portal.leads.route', ['company' => $company->id]) }}" data-exclusive-url="{{ route('portal.leads.store', ['company' => $company->id]) }}"
     data-company-key="{{ json_encode($leadCompanyKey, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}"
     data-texts="{{ json_encode($leadTexts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}">
  <div class="absolute inset-0 bg-zinc-900/40" data-close-lead></div>
  <div class="absolute inset-x-0 bottom-0 sm:inset-0 sm:flex sm:items-center sm:justify-center sm:p-4">
    <form class="bg-white rounded-t-2xl sm:rounded-2xl w-full sm:max-w-lg max-h-[92dvh] sm:max-h-[90vh] flex flex-col shadow-lg" id="leadForm" novalidate>

      <div class="flex items-start justify-between gap-4 p-5 border-b border-zinc-200">
        <div>
          <p class="text-sm text-zinc-500" id="leadStep">{{ __('portal.request.step', ['schritt' => 1]) }}</p>
          <h2 id="leadTitle" class="text-lg font-semibold text-zinc-900">{{ $titles[$firstStep->position] }}</h2>
        </div>
        <button type="button" class="btn-ghost px-3 -mr-2 -mt-1" data-close-lead aria-label="{{ __('portal.request.close') }}"><x-sun.icon name="x" class="icon" /></button>
      </div>

      <div class="p-5 overflow-y-auto flex-1" data-lead-body>
        {{-- Honigtopf: bleibt leer, gefuellt verwirft der Server die Anfrage still --}}
        <div class="hidden mb-5" data-exclusive-hint>
          <p class="flex items-start gap-2 rounded-xl bg-brand-50 p-3 text-sm text-zinc-900"><x-sun.icon name="check" class="icon shrink-0 text-brand" />{{ __('portal.request.exclusive_hint', $firma) }}</p>
        </div>
        <div class="sr-only" aria-hidden="true"><label for="lead-website">{{ __('portal.request.honeypot_label') }}</label><input id="lead-website" type="text" name="website" tabindex="-1" autocomplete="off"></div>

        @foreach($funnel->steps as $step)
          <fieldset data-step="{{ $step->position }}" @class(['space-y-5', 'hidden' => $step->position !== $firstStep->position])>
            <legend class="sr-only">{{ $titles[$step->position] }}</legend>
            @if($step->description)
              <p class="text-sm text-zinc-500">{{ strtr($step->description, [':firma' => $company->name]) }}</p>
            @endif
            @foreach($step->visibleQuestions() as $question)
              <x-sun.funnel.question :question="$question" :firma="$company->name" />
            @endforeach
          </fieldset>
        @endforeach

        <!-- Erfolg -->
        <div data-step="done" class="hidden text-center py-6 flex flex-col items-center gap-3">
          <span class="size-14 rounded-full bg-brand-50 text-brand flex items-center justify-center"><x-sun.icon name="check" class="size-7" /></span>
          <h3 class="text-lg font-semibold text-zinc-900">{{ __('portal.request.done.heading') }}</h3>
          <p class="text-zinc-500 max-w-sm" data-done-text>{{ __('portal.request.done.text', $firma) }}</p>
        </div>
      </div>

      <p class="px-5 pt-3 text-sm text-red-600 hidden" role="alert" data-lead-error></p>
      <div class="p-5 border-t border-zinc-200 flex gap-2" data-step-footer>
        <button type="button" class="btn-ghost hidden px-3" data-prev>{{ __('portal.request.back') }}</button>
        <button type="button" class="btn-primary flex-1 whitespace-nowrap" data-next>{{ $leadTexts['next'] }}</button>
      </div>
      <p class="px-5 pb-5 -mt-2 text-xs text-zinc-500" data-step-footer data-privacy><span data-privacy-text>{{ __('portal.request.privacy', $firma) }}</span> <a href="{{ route('portal.datenschutz') }}" class="underline">{{ __('portal.request.privacy_link') }}</a></p>
    </form>
  </div>
</div>
