{{--
    Stellenanzeige (Detail) im Theme sun-v2.
    Daten: PublicJobController::show() — $job, $relatedJobs, $similarJobs.
    Die Brotkrumen baut die View selbst (Texte aus portal.*), $breadcrumb des
    Controllers traegt feste Texte fuers Default-Theme.

    Bewerbung: POST portal.jobs.apply (multipart). Nach Erfolg setzt der
    Controller session('application_success'), Fehler kommen als session('error').
    Link kopieren: [data-copy] aus js/modules/copy-link.js.
    Alle festen Texte aus lang/de/portal.php (jobs.show.*, jobs.card.*, layout.*).
--}}
@extends('layouts.sun')

@php
    $company = $job->company;
    $portalName = $currentTenant->name ?? config('app.name');
    $jobUrl = route('portal.jobs.show', $job->slug);
    $applied = (bool) session('application_success');
    $canApply = ! $job->is_expired && ! $applied;
    $logoUrl = $company->getFirstMediaUrl('logo', 'thumb');
    $initials = \App\Themes\SunV2\ProfileViewComposer::initials($company->name);
    $deadlineOpen = $job->application_deadline && ! $job->application_deadline->isPast();

    $breadcrumb = [
        ['label' => __('portal.layout.breadcrumb.home'), 'url' => route('home')],
        ['label' => __('portal.layout.header.jobs'), 'url' => route('portal.jobs.index')],
        ['label' => $job->title, 'url' => $jobUrl],
    ];

    $sections = array_filter([
        'beschreibung' => $job->description ? [__('portal.jobs.show.description_heading'), $job->description] : null,
        'anforderungen' => $job->requirements ? [__('portal.jobs.show.requirements_heading'), $job->requirements] : null,
        'benefits' => $job->benefits ? [__('portal.jobs.show.benefits_heading', ['firma' => $company->name]), $job->benefits] : null,
    ]);

    $fieldHint = 'mt-1 text-sm';
    $label = 'block text-sm font-medium text-zinc-700 mb-1';
@endphp

@section('title', __('portal.jobs.show.meta_title', ['stelle' => $job->title, 'firma' => $company->name]).' | '.$portalName)
@section('meta_description', \Illuminate\Support\Str::limit(strip_tags((string) $job->description), 160))
@section('canonical', $jobUrl)
@if($canApply)
  @section('body_class', 'pb-24 lg:pb-0')
@endif

@push('scripts')
<script type="application/ld+json">
{!! json_encode(array_filter([
    '@'.'context' => 'https://schema.org',
    '@type' => 'JobPosting',
    'title' => $job->title,
    'description' => strip_tags((string) $job->description),
    'datePosted' => $job->published_at?->toIso8601String(),
    'validThrough' => $job->expires_at?->toIso8601String(),
    'employmentType' => match ($job->employment_type) {
        'vollzeit' => 'FULL_TIME',
        'teilzeit', 'minijob' => 'PART_TIME',
        'ausbildung', 'praktikum' => 'INTERN',
        default => 'OTHER',
    },
    'hiringOrganization' => [
        '@type' => 'Organization',
        'name' => $company->name,
        'sameAs' => $company->portal_url,
        'logo' => $company->getFirstMediaUrl('logo') ?: null,
    ],
    'jobLocation' => $job->location_display ? [
        '@type' => 'Place',
        'address' => [
            '@type' => 'PostalAddress',
            'addressLocality' => $job->location_display,
            'addressCountry' => 'DE',
        ],
    ] : null,
    'baseSalary' => ($job->salary_min || $job->salary_max) ? [
        '@type' => 'MonetaryAmount',
        'currency' => 'EUR',
        'value' => [
            '@type' => 'QuantitativeValue',
            'minValue' => $job->salary_min,
            'maxValue' => $job->salary_max,
            'unitText' => match ($job->salary_type) {
                'hourly' => 'HOUR',
                'yearly' => 'YEAR',
                default => 'MONTH',
            },
        ],
    ] : null,
]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}
</script>
@endpush

@section('content')
<div class="container-portal pt-6 pb-12 md:pb-16">

  <x-sun.breadcrumb :items="$breadcrumb" />

  <!-- Hinweise: abgelaufen, gesendet, Fehler -->
  @if($job->is_expired)
    <div class="mt-4 card p-4 md:p-5 flex items-start gap-3" role="alert">
      <x-sun.icon name="clock" class="icon text-amber-500 mt-0.5" />
      <div>
        <p class="font-semibold text-zinc-900">{{ __('portal.jobs.show.expired_heading') }}</p>
        <p class="mt-1 text-sm text-zinc-500">{{ __('portal.jobs.show.expired_text', ['datum' => $job->expires_at->format('d.m.Y')]) }} <a href="{{ route('portal.jobs.index') }}" class="text-brand font-medium hover:underline">{{ __('portal.jobs.show.expired_link') }}</a></p>
      </div>
    </div>
  @endif

  @if($applied)
    <div class="mt-4 card p-4 md:p-5 flex items-start gap-3" role="status">
      <x-sun.icon name="check" class="icon text-emerald-600 mt-0.5" />
      <div>
        <p class="font-semibold text-zinc-900">{{ __('portal.jobs.show.success_heading') }}</p>
        <p class="mt-1 text-sm text-zinc-500">{{ __('portal.jobs.show.success_text', ['firma' => $company->name]) }}</p>
      </div>
    </div>
  @endif

  @if(session('error'))
    <p class="mt-4 card p-4 md:p-5 text-base text-red-600" role="alert">{{ session('error') }}</p>
  @endif

  <div class="mt-4 grid gap-6 lg:grid-cols-[1fr_20rem] xl:grid-cols-[1fr_22rem] lg:items-start">

    <!-- ================= HAUPTSPALTE ================= -->
    <div class="flex flex-col gap-6 min-w-0">

      <!-- Kopf-Karte -->
      <section class="card p-5 md:p-8" aria-labelledby="stelle">
        <div class="flex flex-col sm:flex-row sm:items-start gap-4 md:gap-6">
          @if($logoUrl)
            <img src="{{ $logoUrl }}" alt="{{ $company->name }}" width="96" height="96" class="size-20 md:size-24 rounded-2xl border border-zinc-200 object-cover shrink-0">
          @else
            <span class="size-20 md:size-24 rounded-2xl bg-brand-50 text-brand-700 font-bold text-2xl md:text-3xl flex items-center justify-center shrink-0" aria-hidden="true">{{ $initials }}</span>
          @endif
          <div class="min-w-0 flex-1">
            <span class="pill-brand">{{ $job->employment_type_label }}</span>
            <h1 id="stelle" class="mt-2 text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">{{ $job->title }}</h1>
            <a href="{{ $company->portal_url }}" class="mt-1 inline-flex items-center min-h-11 text-brand font-medium hover:underline">{{ $company->name }}</a>
          </div>
        </div>

        <dl class="mt-6 pt-6 border-t border-zinc-200 grid gap-3 sm:grid-cols-2 text-zinc-700">
          @if($job->location_display)
            <div class="flex items-center gap-2"><dt class="sr-only">{{ __('portal.jobs.card.location') }}</dt><x-sun.icon name="map-pin" class="icon text-zinc-400" /><dd>{{ $job->location_display }}</dd></div>
          @endif
          <div class="flex items-center gap-2"><dt class="sr-only">{{ __('portal.jobs.card.salary') }}</dt><x-sun.icon name="euro" class="icon text-zinc-400" /><dd @class(['text-zinc-900 font-medium' => $job->salary_display, 'text-zinc-500' => ! $job->salary_display])>{{ $job->salary_display ?? __('portal.jobs.card.salary_none') }}</dd></div>
          @if($job->published_at)
            <div class="flex items-center gap-2"><dt class="sr-only">{{ __('portal.jobs.show.published_label') }}</dt><x-sun.icon name="calendar" class="icon text-zinc-400" /><dd>{{ __('portal.jobs.show.published', ['datum' => $job->published_at->locale('de')->translatedFormat('j. F Y')]) }}</dd></div>
          @endif
          @if($deadlineOpen)
            <div class="flex items-center gap-2"><dt class="sr-only">{{ __('portal.jobs.show.deadline_label') }}</dt><x-sun.icon name="clock" class="icon text-amber-500" /><dd class="text-zinc-900 font-medium">{{ __('portal.jobs.show.deadline', ['datum' => $job->application_deadline->format('d.m.Y')]) }}</dd></div>
          @endif
        </dl>
      </section>

      <!-- Beschreibung, Anforderungen, Benefits -->
      @foreach($sections as $id => [$heading, $text])
        <section class="card p-5 md:p-8" aria-labelledby="{{ $id }}">
          <h2 id="{{ $id }}" class="text-2xl font-semibold text-zinc-900">{{ $heading }}</h2>
          <div class="mt-4 max-w-prose text-base leading-relaxed text-zinc-700">{!! nl2br(e($text)) !!}</div>
        </section>
      @endforeach

      <!-- Über die Firma -->
      <section class="card p-5 md:p-8" aria-labelledby="firma">
        <h2 id="firma" class="text-2xl font-semibold text-zinc-900">{{ __('portal.jobs.show.company_heading', ['firma' => $company->name]) }}</h2>
        @if($company->description)
          <p class="mt-4 max-w-prose text-base leading-relaxed text-zinc-700 line-clamp-3">{{ trim(strip_tags(\Illuminate\Support\Str::markdown((string) $company->description))) }}</p>
        @endif
        <a href="{{ $company->portal_url }}" class="mt-4 btn-secondary">{{ __('portal.layout.card.profile') }}</a>
      </section>

    </div>

    <!-- ================= RECHTE SPALTE (auf Mobile unter dem Inhalt) ================= -->
    <aside class="flex flex-col gap-4" aria-label="{{ __('portal.jobs.show.aside_label') }}">

      @if($canApply)
        <!-- Bewerbung -->
        <section class="card p-5 scroll-mt-20" id="bewerben" aria-labelledby="bewerben-titel">
          <h2 id="bewerben-titel" class="text-lg font-semibold text-zinc-900">{{ __('portal.jobs.show.apply.heading') }}</h2>
          <p class="mt-1 text-sm text-zinc-500">{{ __('portal.jobs.show.apply.text', ['firma' => $company->name]) }}</p>

          <form action="{{ route('portal.jobs.apply', $job->slug) }}" method="POST" enctype="multipart/form-data" class="mt-5 flex flex-col gap-4" novalidate>
            @csrf

            <div>
              <label for="name" class="{{ $label }}">{{ __('portal.jobs.show.apply.name_label') }}</label>
              <input type="text" id="name" name="name" value="{{ old('name') }}" @class(['input', 'border-red-500' => $errors->has('name')]) placeholder="{{ __('portal.jobs.show.apply.name_placeholder') }}" autocomplete="name" required @error('name') aria-invalid="true" aria-describedby="name-fehler" @enderror>
              @error('name')<p id="name-fehler" class="{{ $fieldHint }} text-red-600" role="alert">{{ $message }}</p>@enderror
            </div>

            <div>
              <label for="email" class="{{ $label }}">{{ __('portal.jobs.alert.email_label') }}</label>
              <input type="email" id="email" name="email" value="{{ old('email') }}" @class(['input', 'border-red-500' => $errors->has('email')]) placeholder="{{ __('portal.jobs.alert.email_placeholder') }}" autocomplete="email" inputmode="email" required @error('email') aria-invalid="true" aria-describedby="email-fehler" @enderror>
              @error('email')<p id="email-fehler" class="{{ $fieldHint }} text-red-600" role="alert">{{ $message }}</p>@enderror
            </div>

            <div>
              <label for="phone" class="{{ $label }}">{{ __('portal.jobs.show.apply.phone_label') }} <span class="font-normal text-zinc-500">{{ __('portal.layout.optional') }}</span></label>
              <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" @class(['input', 'border-red-500' => $errors->has('phone')]) autocomplete="tel" inputmode="tel" @error('phone') aria-invalid="true" aria-describedby="phone-fehler" @enderror>
              @error('phone')<p id="phone-fehler" class="{{ $fieldHint }} text-red-600" role="alert">{{ $message }}</p>@enderror
            </div>

            <div>
              <label for="message" class="{{ $label }}">{{ __('portal.jobs.show.apply.message_label') }}</label>
              <textarea id="message" name="message" rows="5" minlength="20" @class(['input py-3 resize-y', 'border-red-500' => $errors->has('message')]) placeholder="{{ __('portal.jobs.show.apply.message_placeholder') }}" aria-describedby="message-hinweis" required @error('message') aria-invalid="true" @enderror>{{ old('message') }}</textarea>
              <p id="message-hinweis" @class([$fieldHint, 'text-red-600' => $errors->has('message'), 'text-zinc-500' => ! $errors->has('message')]) @error('message') role="alert" @enderror>{{ $errors->first('message') ?: __('portal.jobs.show.apply.message_hint') }}</p>
            </div>

            <div>
              <label for="cv" class="{{ $label }}">{{ __('portal.jobs.show.apply.cv_label') }} <span class="font-normal text-zinc-500">{{ __('portal.layout.optional') }}</span></label>
              <input type="file" id="cv" name="cv" accept=".pdf,.doc,.docx" aria-describedby="cv-hinweis" class="block w-full text-sm text-zinc-500 file:mr-3 file:min-h-11 file:px-4 file:rounded-xl file:border-0 file:bg-zinc-100 file:text-sm file:font-semibold file:text-zinc-900 hover:file:bg-zinc-200 file:cursor-pointer">
              <p id="cv-hinweis" @class([$fieldHint, 'text-red-600' => $errors->has('cv'), 'text-zinc-500' => ! $errors->has('cv')]) @error('cv') role="alert" @enderror>{{ $errors->first('cv') ?: __('portal.jobs.show.apply.cv_hint') }}</p>
            </div>

            <button type="submit" class="btn-primary w-full">{{ __('portal.jobs.show.apply.submit') }}</button>

            {{-- Text escapen, erst danach den Link einsetzen: der Satz ist per tenant_texts ueberschreibbar --}}
            <p class="text-sm text-zinc-500 text-center">{!! strtr(e(__('portal.jobs.show.apply.privacy', ['datenschutz' => '[[datenschutz]]'])), [
              '[[datenschutz]]' => '<a href="'.e(route('portal.datenschutz')).'" class="underline hover:text-brand">'.e(__('portal.jobs.show.apply.privacy_link')).'</a>',
            ]) !!}</p>
          </form>
        </section>
      @elseif($applied)
        <section class="card p-5 text-center" role="status">
          <span class="mx-auto size-12 rounded-full bg-brand-50 text-emerald-600 flex items-center justify-center"><x-sun.icon name="check" class="size-6" /></span>
          <h2 class="mt-3 text-lg font-semibold text-zinc-900">{{ __('portal.jobs.show.success_heading') }}</h2>
          <p class="mt-1 text-sm text-zinc-500">{{ __('portal.jobs.show.success_text', ['firma' => $company->name]) }}</p>
        </section>
      @endif

      <!-- Kontakt -->
      @if($company->full_address || $company->tel || $company->email)
        <section class="card p-5" aria-labelledby="kontakt">
          <h2 id="kontakt" class="font-semibold text-zinc-900">{{ __('portal.jobs.show.contact_heading') }}</h2>
          <ul class="mt-3 flex flex-col gap-2 text-sm">
            @if($company->full_address)
              <li class="flex items-start gap-2"><x-sun.icon name="map-pin" class="size-4 shrink-0 mt-0.5 text-zinc-400" /><span class="text-zinc-700">{{ $company->full_address }}</span></li>
            @endif
            @if($company->tel)
              <li class="flex items-center gap-2"><x-sun.icon name="phone" class="size-4 shrink-0 text-zinc-400" /><x-phone-link :number="$company->tel" class="inline-flex items-center min-h-11 text-brand font-medium hover:underline" fallback-class="text-zinc-700" /></li>
            @endif
            @if($company->email)
              <li class="flex items-center gap-2 min-w-0"><x-sun.icon name="mail" class="size-4 shrink-0 text-zinc-400" /><a href="mailto:{{ $company->email }}" class="inline-flex items-center min-h-11 min-w-0 text-brand font-medium hover:underline"><span class="truncate">{{ $company->email }}</span></a></li>
            @endif
          </ul>
        </section>
      @endif

      <!-- Teilen -->
      <section class="card p-5" aria-labelledby="teilen">
        <h2 id="teilen" class="font-semibold text-zinc-900">{{ __('portal.jobs.show.share.heading') }}</h2>
        <label for="stellenlink" class="sr-only">{{ __('portal.jobs.show.share.link_label') }}</label>
        <div class="mt-3 flex gap-2">
          <input id="stellenlink" type="text" value="{{ $jobUrl }}" class="input text-sm min-w-0" readonly>
          <button type="button" class="btn-secondary shrink-0 px-3" data-copy data-copied="{{ __('portal.jobs.show.share.copied') }}" aria-controls="stellenlink" aria-label="{{ __('portal.jobs.show.share.copy') }}"><x-sun.icon name="link" class="icon" /></button>
        </div>
        <p class="mt-2 text-sm text-emerald-600 empty:hidden" role="status" data-copy-status></p>
        <a href="mailto:?subject={{ rawurlencode(__('portal.jobs.show.meta_title', ['stelle' => $job->title, 'firma' => $company->name])) }}&amp;body={{ rawurlencode(__('portal.jobs.show.share.mail_body', ['url' => $jobUrl])) }}" class="mt-2 btn-ghost w-full"><x-sun.icon name="mail" class="icon" />{{ __('portal.jobs.show.share.mail') }}</a>
      </section>

    </aside>
  </div>

  <!-- Weitere Stellen dieser Firma / ähnliche Stellen -->
  @foreach([
      'weitere-stellen' => [__('portal.jobs.show.related_heading', ['firma' => $company->name]), $relatedJobs, false],
      'aehnliche-stellen' => [__('portal.jobs.show.similar_heading'), $similarJobs, true],
  ] as $listId => [$listHeading, $list, $showCompany])
    @if($list->isNotEmpty())
      <section class="mt-12 md:mt-16" aria-labelledby="{{ $listId }}">
        <h2 id="{{ $listId }}" class="text-2xl font-semibold text-zinc-900">{{ $listHeading }}</h2>
        <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          @foreach($list as $other)
            <article class="card-interactive p-5 flex flex-col gap-3">
              <div class="min-w-0">
                <h3 class="text-lg font-semibold text-zinc-900 leading-snug"><a href="{{ route('portal.jobs.show', $other->slug) }}" class="hover:text-brand">{{ $other->title }}</a></h3>
                @if($showCompany && $other->company)
                  <p class="mt-1 text-zinc-700">{{ $other->company->name }}</p>
                @endif
              </div>
              <dl class="flex flex-wrap gap-x-5 gap-y-1.5 text-sm text-zinc-500">
                @if($other->location_display)
                  <div class="flex items-center gap-1.5"><dt class="sr-only">{{ __('portal.jobs.card.location') }}</dt><x-sun.icon name="map-pin" class="size-4 shrink-0" /><dd>{{ $other->location_display }}</dd></div>
                @endif
                <div class="flex items-center gap-1.5"><dt class="sr-only">{{ __('portal.jobs.card.type') }}</dt><x-sun.icon name="clock" class="size-4 shrink-0" /><dd>{{ $other->employment_type_label }}</dd></div>
                @if($other->salary_display)
                  <div class="flex items-center gap-1.5"><dt class="sr-only">{{ __('portal.jobs.card.salary') }}</dt><x-sun.icon name="euro" class="size-4 shrink-0" /><dd class="text-zinc-900 font-medium">{{ $other->salary_display }}</dd></div>
                @endif
              </dl>
              <a href="{{ route('portal.jobs.show', $other->slug) }}" class="btn-secondary mt-auto" aria-label="{{ __('portal.jobs.card.details') }}: {{ $other->title }}">{{ __('portal.jobs.card.details') }}</a>
            </article>
          @endforeach
        </div>
      </section>
    @endif
  @endforeach

</div>

<!-- ================= MOBILE: STICKY BOTTOM BAR ================= -->
@if($canApply)
<div class="fixed bottom-0 inset-x-0 z-30 bg-white border-t border-zinc-200 p-3 flex lg:hidden" style="padding-bottom:max(.75rem,env(safe-area-inset-bottom))">
  <a href="#bewerben" class="btn-primary flex-1 whitespace-nowrap">{{ __('portal.jobs.card.apply') }}</a>
</div>
@endif
@endsection
