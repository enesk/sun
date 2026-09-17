{{--
    Bewertungs-Tools im Betriebsbereich, Theme sun-v2 (#12).
    Logik: App\Livewire\Portal\Company\Dashboard\Reviews. Texte: portal.owner.reviews.tools.*
    Kopieren uebernimmt copy-link.js ([data-copy]); die Komponente rendert nicht neu.
--}}
<div class="space-y-4 md:space-y-6">
  <section class="card p-5 md:p-6" aria-labelledby="sec-bewertung-qr">
    <h2 id="sec-bewertung-qr" class="text-lg font-semibold text-zinc-900">{{ __('portal.owner.reviews.tools.qr_title') }}</h2>
    <p class="mt-1 text-base text-zinc-700">{{ __('portal.owner.reviews.tools.qr_text') }}</p>
    <img class="mt-3 mx-auto w-40 h-40 rounded-lg border border-zinc-200 bg-white"
         src="data:image/svg+xml;base64,{{ base64_encode($qrSvg) }}"
         alt="{{ __('portal.owner.reviews.tools.qr_alt', ['firma' => $company->name]) }}" width="160" height="160">
    <a href="{{ route('portal.owner.reviews.qr-code') }}" class="btn-secondary mt-3 w-full" download>
      <x-sun.icon name="download" class="icon" />{{ __('portal.owner.reviews.tools.qr_download') }}
    </a>
  </section>

  <section id="widget" class="card p-5 md:p-6" aria-labelledby="sec-bewertung-widget">
    <h2 id="sec-bewertung-widget" class="text-lg font-semibold text-zinc-900 flex flex-wrap items-center gap-2">
      {{ __('portal.owner.reviews.tools.widget_title') }}
      @unless($widgetAllowed)
        <span class="pill-brand text-xs">{{ __('portal.owner.reviews.premium.badge') }}</span>
      @endunless
    </h2>
    <p class="mt-1 text-base text-zinc-700">{{ __('portal.owner.reviews.tools.widget_text') }}</p>

    @if($widgetAllowed)
      <label for="widget-code" class="mt-3 block text-sm font-medium text-zinc-700 mb-1">{{ __('portal.owner.reviews.tools.widget_code') }}</label>
      <textarea id="widget-code" class="input py-2 font-mono text-xs" rows="3" readonly>{{ $embedCode }}</textarea>
      <button type="button" class="btn-secondary mt-2 w-full" data-copy data-copied="{{ __('portal.owner.reviews.tools.widget_copied') }}" aria-controls="widget-code">
        <x-sun.icon name="link" class="icon" />{{ __('portal.owner.reviews.tools.widget_copy') }}
      </button>
      <p class="mt-2 text-sm text-zinc-500">{{ __('portal.owner.reviews.tools.widget_cache') }}</p>

      <p class="mt-4 text-sm font-medium text-zinc-700">{{ __('portal.owner.reviews.tools.widget_preview') }}</p>
      <iframe src="{{ $widgetUrl }}" title="{{ __('portal.widget.iframe_title', ['firma' => $company->name]) }}"
              class="mt-1 block w-full h-[22rem] border-0" loading="lazy"></iframe>
    @else
      <p class="mt-2 text-sm text-zinc-500">{{ __('portal.owner.reviews.tools.widget_locked') }}</p>
      <a href="{{ route('portal.owner.premium') }}" class="btn-secondary mt-3 w-full"><x-sun.icon name="sparkles" class="icon" />{{ __('portal.owner.reviews.premium.cta') }}</a>
    @endif
  </section>
</div>
