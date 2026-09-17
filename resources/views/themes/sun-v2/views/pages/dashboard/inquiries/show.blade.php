{{--
    Einzelne Anfrage im Betriebsbereich, Theme sun-v2 (#33).
    Daten: OwnerInquiryController::show() (setzt beim ersten Oeffnen "gelesen").
    Texte: lang/de/portal.php (owner.inquiries.*).

    - Alle Antworten als Label/Wert-Liste, Mehrfachauswahl kommagetrennt.
    - Kontakt mit tel: (E.164, deutsche Nummern ohne Laendervorwahl bekommen +49)
      und mailto:. contact_visible ist fuer ein spaeteres Premium-Gate vorbereitet.
    - Nach Ablauf der Aufbewahrung (leads:inquiries:purge-contacts) sind Kontakt
      und Antworten leer; statt der Leer-Hinweise steht dann der Loeschhinweis.
    - Statuswechsel als Formular ohne JavaScript, ein Knopf je Status.
--}}
@extends('layouts.panel')

@php
    use App\Constants\CompanyInquiryStatus;

    $received = $inquiry->received_at ?? $inquiry->created_at;
    $showContact = (bool) $inquiry->contact_visible;
    $purgedNote = $inquiry->isPurged()
        ? __('portal.owner.inquiries.show.purged', ['monate' => (int) config('leads.exclusive.retention_months', 12)])
        : null;
    $name = $showContact && filled($inquiry->contact_name) ? $inquiry->contact_name : __('portal.owner.inquiries.unknown_name');

    $display = function (array $answer): string {
        $value = $answer['value_label'] ?? $answer['value'] ?? '';
        if (is_bool($value)) {
            return $value ? __('portal.owner.inquiries.show.yes') : __('portal.owner.inquiries.show.no');
        }

        return is_array($value) ? implode(', ', array_filter(array_map('strval', $value))) : trim((string) $value);
    };
    $answers = collect((array) $inquiry->answers)
        ->map(fn ($answer) => ['label' => (string) ($answer['label'] ?? $answer['key'] ?? ''), 'value' => $display((array) $answer)])
        ->filter(fn ($answer) => $answer['label'] !== '' && $answer['value'] !== '')
        ->values();

    $phone = (string) $inquiry->contact_phone;
    $phoneHref = null;
    if ($showContact && $phone !== '') {
        $digits = preg_replace('/[^\d+]/', '', $phone);
        $digits = str_starts_with($digits, '00') ? '+'.substr($digits, 2) : $digits;
        $digits = str_starts_with($digits, '0') ? '+49'.substr($digits, 1) : $digits;
        $phoneHref = str_starts_with($digits, '+') ? $digits : null;
    }
    $mailHref = $showContact && filled($inquiry->contact_email)
        ? 'mailto:'.$inquiry->contact_email.'?subject='.rawurlencode(__('portal.owner.inquiries.show.reply_subject', ['firma' => $company->name]))
        : null;
@endphp

@section('title', __('portal.owner.inquiries.show.title', ['name' => $name]))

@section('content')
  <a href="{{ route('portal.owner.inquiries.index') }}" class="btn-ghost -ml-3 px-3">
    <x-sun.icon name="arrow-left" class="icon" />{{ __('portal.owner.inquiries.show.back') }}
  </a>

  <div class="mt-2 flex flex-wrap items-start justify-between gap-3">
    <div class="min-w-0">
      <h1 class="text-3xl md:text-4xl font-bold tracking-tight text-zinc-900 break-words">{{ __('portal.owner.inquiries.show.title', ['name' => $name]) }}</h1>
      <p class="mt-1 text-base text-zinc-500">
        <time datetime="{{ $received->toIso8601String() }}">{{ __('portal.owner.inquiries.show.received', ['datum' => $received->format('d.m.Y'), 'uhrzeit' => $received->format('H:i')]) }}</time>
      </p>
    </div>
    <span class="{{ $inquiry->status === CompanyInquiryStatus::DONE ? 'pill bg-emerald-50 text-emerald-700' : ($inquiry->status === CompanyInquiryStatus::NEW ? 'pill-brand' : 'pill') }}">{{ __("portal.owner.inquiries.statuses.{$inquiry->status->value}") }}</span>
  </div>

  <div class="mt-6 grid lg:grid-cols-[1fr_20rem] gap-4 md:gap-6 items-start">

    {{-- Angaben --}}
    <section class="card p-5 md:p-6 min-w-0" aria-labelledby="sec-angaben">
      <h2 id="sec-angaben" class="text-2xl font-semibold text-zinc-900">{{ __('portal.owner.inquiries.show.answers') }}</h2>
      @if($answers->isEmpty())
        <p class="mt-3 text-base text-zinc-500">{{ $purgedNote ?? __('portal.owner.inquiries.show.no_answers') }}</p>
      @else
        <dl class="mt-4 divide-y divide-zinc-200">
          @foreach($answers as $answer)
            <div class="py-3 grid gap-1 sm:grid-cols-[minmax(0,2fr)_minmax(0,3fr)] sm:gap-4">
              <dt class="text-sm text-zinc-500">{{ $answer['label'] }}</dt>
              <dd class="text-base text-zinc-900 whitespace-pre-line break-words">{{ $answer['value'] }}</dd>
            </div>
          @endforeach
        </dl>
      @endif
    </section>

    <aside class="space-y-4 md:space-y-6 lg:sticky lg:top-24">

      {{-- Kontakt --}}
      <section class="card p-5 md:p-6" aria-labelledby="sec-kontakt">
        <h2 id="sec-kontakt" class="text-lg font-semibold text-zinc-900">{{ __('portal.owner.inquiries.show.contact') }}</h2>
        @if($purgedNote)
          <p class="mt-2 text-base text-zinc-500">{{ $purgedNote }}</p>
        @elseif(! $showContact)
          <p class="mt-2 text-base text-zinc-500">{{ __('portal.owner.inquiries.show.contact_hidden') }}</p>
        @elseif(blank($inquiry->contact_name) && blank($inquiry->contact_email) && blank($inquiry->contact_phone))
          <p class="mt-2 text-base text-zinc-500">{{ __('portal.owner.inquiries.show.no_contact') }}</p>
        @else
          <dl class="mt-3 space-y-3 text-base">
            @if(filled($inquiry->contact_name))
              <div><dt class="text-sm text-zinc-500">{{ __('portal.owner.inquiries.show.name') }}</dt><dd class="text-zinc-900 break-words">{{ $inquiry->contact_name }}</dd></div>
            @endif
            @if($phone !== '')
              <div><dt class="text-sm text-zinc-500">{{ __('portal.owner.inquiries.show.phone') }}</dt><dd class="text-zinc-900">{{ $phone }}</dd></div>
            @endif
            @if(filled($inquiry->contact_email))
              <div><dt class="text-sm text-zinc-500">{{ __('portal.owner.inquiries.show.email') }}</dt><dd class="text-zinc-900 break-all">{{ $inquiry->contact_email }}</dd></div>
            @endif
          </dl>
          <div class="mt-4 flex flex-col gap-2">
            @if($phoneHref)
              <a href="tel:{{ $phoneHref }}" class="btn-primary w-full"><x-sun.icon name="phone" class="icon" />{{ __('portal.owner.inquiries.show.call') }}</a>
            @endif
            @if($mailHref)
              <a href="{{ $mailHref }}" class="{{ $phoneHref ? 'btn-secondary' : 'btn-primary' }} w-full"><x-sun.icon name="mail" class="icon" />{{ __('portal.owner.inquiries.show.write') }}</a>
            @endif
          </div>
        @endif
      </section>

      {{-- Status --}}
      <section class="card p-5 md:p-6" aria-labelledby="sec-status">
        <h2 id="sec-status" class="text-lg font-semibold text-zinc-900">{{ __('portal.owner.inquiries.show.status') }}</h2>
        <form method="POST" action="{{ route('portal.owner.inquiries.status', $inquiry->id) }}" class="mt-3 flex flex-wrap gap-2">
          @csrf
          @method('PATCH')
          @foreach(CompanyInquiryStatus::cases() as $status)
            @if($status === $inquiry->status)
              <span class="pill-brand min-h-11 px-4" aria-current="true">
                <x-sun.icon name="check" class="size-4 shrink-0" />{{ __("portal.owner.inquiries.statuses.{$status->value}") }}
                <span class="sr-only">({{ __('portal.owner.inquiries.show.status_current') }})</span>
              </span>
            @else
              <button type="submit" name="status" value="{{ $status->value }}" class="pill min-h-11 px-4 hover:bg-zinc-200 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2"
                      aria-label="{{ __('portal.owner.inquiries.show.status_set', ['status' => __("portal.owner.inquiries.statuses.{$status->value}")]) }}">
                {{ __("portal.owner.inquiries.statuses.{$status->value}") }}
              </button>
            @endif
          @endforeach
        </form>
        @error('status') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        <p class="mt-3 text-sm text-zinc-500">{{ __('portal.owner.inquiries.show.status_hint') }}</p>
      </section>
    </aside>
  </div>
@endsection
