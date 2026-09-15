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
            'title' => __('portal.consent.necessary.title'),
            'text' => __('portal.consent.necessary.text'),
            'details' => [
                __('portal.consent.necessary.session'),
                __('portal.consent.necessary.csrf'),
                __('portal.consent.necessary.consent'),
            ],
        ],
        [
            'key' => 'statistics',
            'title' => __('portal.consent.statistics.title'),
            'text' => __('portal.consent.statistics.text'),
            'details' => [
                __('portal.consent.statistics.analytics'),
            ],
        ],
        [
            'key' => 'marketing',
            'title' => __('portal.consent.marketing.title'),
            'text' => __('portal.consent.marketing.text'),
            'details' => [],
        ],
    ];
@endphp

<div data-cookie-banner hidden class="fixed inset-x-0 bottom-0 z-50 p-4" role="region" aria-label="{{ __('portal.layout.cookies.region_label') }}">
  <div class="container-portal">
    <div class="card shadow-lg p-4 md:p-5 flex flex-col lg:flex-row lg:items-center gap-4">
      <p class="text-sm leading-relaxed text-zinc-700 flex-1">
        {{ __('portal.layout.cookies.banner') }}
        <a href="{{ route('portal.datenschutz') }}" class="text-brand font-medium hover:underline">{{ __('portal.layout.cookies.privacy_link') }}</a>
      </p>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 shrink-0">
        <button type="button" class="btn-secondary" data-cookie-action="settings">{{ __('portal.layout.cookies.settings') }}</button>
        <button type="button" class="btn-secondary" data-cookie-action="essential">{{ __('portal.consent.essential') }}</button>
        <button type="button" class="btn-primary" data-cookie-action="all">{{ __('portal.layout.cookies.accept_all') }}</button>
      </div>
    </div>
  </div>
</div>

<dialog data-cookie-modal class="card m-auto w-[calc(100%-2rem)] max-w-2xl p-0 backdrop:bg-zinc-900/50" aria-labelledby="cookie-modal-title">
  <div class="p-5 md:p-6 flex items-center justify-between gap-4 border-b border-zinc-200">
    <h2 id="cookie-modal-title" class="text-xl font-semibold text-zinc-900">{{ __('portal.layout.cookies.modal_title') }}</h2>
    <button type="button" class="btn-ghost px-3" data-cookie-action="close" aria-label="{{ __('portal.layout.close') }}">
      <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
    </button>
  </div>
  <div class="p-5 md:p-6 flex flex-col gap-4">
    <p class="text-sm leading-relaxed text-zinc-700">
      {{ __('portal.layout.cookies.modal_text') }}
      <a href="{{ route('portal.datenschutz') }}" class="text-brand font-medium hover:underline">{{ __('portal.layout.cookies.learn_more') }}</a>
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
            <span class="pill-brand shrink-0">{{ __('portal.layout.cookies.always_active') }}</span>
          @endif
        </div>
        @if($category['details'])
          <details class="mt-3 text-sm">
            <summary class="text-brand font-medium">{{ __('portal.layout.cookies.show_details') }}</summary>
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
    <button type="button" class="btn-secondary" data-cookie-action="essential">{{ __('portal.consent.essential') }}</button>
    <button type="button" class="btn-secondary" data-cookie-action="save">{{ __('portal.layout.cookies.save') }}</button>
    <button type="button" class="btn-primary" data-cookie-action="all">{{ __('portal.layout.cookies.accept_all') }}</button>
  </div>
</dialog>
