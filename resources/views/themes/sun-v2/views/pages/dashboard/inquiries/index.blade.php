{{--
    Anfragen im Betriebsbereich, Theme sun-v2 (#33). Kein Mockup: Aufbau wie die
    Bewertungsseite (Filter-Pills, Karten-Liste). Daten: OwnerInquiryController::index().
    Texte: lang/de/portal.php (owner.inquiries.*).

    Liste: mobil Karten, ab md eine Tabelle mit Datum, Name, Anliegen, Status.
    Anliegen = erste inhaltliche Antwort (Kontaktfelder zaehlen nicht).
    Neue Anfragen stehen fett mit Punkt in der Markenfarbe.
    Oben stehen die exklusiven Anfragen (#9, Livewire Portal\Company\Dashboard\Leads).
--}}
@extends('layouts.panel')

@php
    use App\Constants\CompanyInquiryStatus;

    $total = array_sum($counts);
    $subject = function ($inquiry): ?string {
        foreach ((array) $inquiry->answers as $answer) {
            $key = mb_strtolower((string) ($answer['key'] ?? ''));
            if (preg_match('/name|mail|tel|phone|plz|postleitzahl|firmenprofil/u', $key)) {
                continue;
            }
            $value = $answer['value_label'] ?? $answer['value'] ?? null;
            $value = is_array($value) ? implode(', ', array_filter(array_map('strval', $value))) : trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    };
    $statusClass = fn (CompanyInquiryStatus $status): string => match ($status) {
        CompanyInquiryStatus::NEW => 'pill-brand',
        CompanyInquiryStatus::DONE => 'pill bg-emerald-50 text-emerald-700',
        default => 'pill',
    };
@endphp

@section('title', __('portal.owner.inquiries.title'))

@section('content')
  <div class="flex flex-wrap items-start justify-between gap-3">
    <div class="min-w-0">
      <h1 class="text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">{{ __('portal.owner.inquiries.title') }}</h1>
      <p class="mt-1 text-base text-zinc-500">{{ __('portal.owner.inquiries.intro', ['firma' => $company->name]) }}</p>
    </div>
  </div>

  {{-- Exklusive Anfragen (#9), darunter die Anfragen aus dem Leadsystem --}}
  @livewire('portal.company.dashboard.leads')

  @if($total > 0)
    <nav aria-label="{{ __('portal.owner.inquiries.filter_label') }}" class="mt-6 -mx-4 px-4 flex gap-2 overflow-x-auto pb-1 md:mx-0 md:px-0">
      <a href="{{ route('portal.owner.inquiries.index') }}"
         class="{{ $filter === null ? 'pill-brand' : 'pill hover:bg-zinc-200' }} whitespace-nowrap min-h-11 px-4"
         @if($filter === null) aria-current="page" @endif>{{ __('portal.owner.inquiries.filter_all') }} · {{ $total }}</a>
      @foreach(CompanyInquiryStatus::cases() as $status)
        <a href="{{ route('portal.owner.inquiries.index', ['status' => $status->value]) }}"
           class="{{ $filter === $status ? 'pill-brand' : 'pill hover:bg-zinc-200' }} whitespace-nowrap min-h-11 px-4"
           @if($filter === $status) aria-current="page" @endif>{{ __("portal.owner.inquiries.statuses.{$status->value}") }} · {{ $counts[$status->value] ?? 0 }}</a>
      @endforeach
    </nav>
  @endif

  @if($inquiries->isEmpty())
    <section class="mt-6 card p-6 md:p-8 text-center">
      <span class="mx-auto inline-flex size-12 items-center justify-center rounded-2xl bg-brand-50 text-brand"><x-sun.icon name="inbox" class="size-6" /></span>
      @if($total === 0)
        <h2 class="mt-3 text-lg font-semibold text-zinc-900">{{ __('portal.owner.inquiries.empty_title') }}</h2>
        <p class="mt-1 mx-auto max-w-prose text-base text-zinc-500">{{ __('portal.owner.inquiries.empty_text') }}</p>
        <a href="{{ $company->portal_url }}" class="btn-secondary mt-4" target="_blank" rel="noopener"><x-sun.icon name="external" class="icon" />{{ __('portal.owner.inquiries.view_company') }}</a>
      @else
        <p class="mt-3 text-base text-zinc-500">{{ __('portal.owner.inquiries.empty_filter') }}</p>
      @endif
    </section>
  @else
    <section class="mt-4 card overflow-hidden" aria-label="{{ __('portal.owner.inquiries.title') }}">
      {{-- Kopfzeile nur ab md --}}
      <div class="hidden md:grid md:grid-cols-[7rem_minmax(0,1fr)_minmax(0,1.5fr)_6rem] gap-4 border-b border-zinc-200 bg-zinc-50 px-6 py-3 text-sm font-medium text-zinc-500" aria-hidden="true">
        <span>{{ __('portal.owner.inquiries.columns.date') }}</span>
        <span>{{ __('portal.owner.inquiries.columns.name') }}</span>
        <span>{{ __('portal.owner.inquiries.columns.subject') }}</span>
        <span>{{ __('portal.owner.inquiries.columns.status') }}</span>
      </div>
      <ul class="divide-y divide-zinc-200">
        @foreach($inquiries as $inquiry)
          @php
              $isNew = $inquiry->status === CompanyInquiryStatus::NEW;
              $name = $inquiry->contact_visible && filled($inquiry->contact_name) ? $inquiry->contact_name : __('portal.owner.inquiries.unknown_name');
              $received = $inquiry->received_at ?? $inquiry->created_at;
          @endphp
          <li>
            <a href="{{ route('portal.owner.inquiries.show', $inquiry->id) }}"
               class="grid grid-cols-[1fr_auto] gap-x-3 gap-y-1 px-5 py-4 md:grid-cols-[7rem_minmax(0,1fr)_minmax(0,1.5fr)_6rem] md:items-center md:gap-4 md:px-6 hover:bg-zinc-50 focus-visible:outline-hidden focus-visible:bg-brand-50"
               aria-label="{{ __('portal.owner.inquiries.open', ['name' => $name]) }}">
              <span class="order-3 col-span-2 text-sm text-zinc-500 md:order-none md:col-span-1">
                <time datetime="{{ $received->toIso8601String() }}" title="{{ $received->format('d.m.Y H:i') }}">{{ $received->diffInMinutes() < 1 ? __('portal.owner.inquiries.just_now') : $received->locale('de')->diffForHumans() }}</time>
              </span>
              <span class="order-1 flex min-w-0 items-center gap-2 md:order-none {{ $isNew ? 'font-semibold text-zinc-900' : 'text-zinc-700' }}">
                @if($isNew)<span class="size-2 shrink-0 rounded-full bg-brand" aria-hidden="true"></span><span class="sr-only">{{ __('portal.owner.inquiries.new_badge') }}:</span>@endif
                <span class="truncate">{{ $name }}</span>
              </span>
              <span class="order-4 col-span-2 truncate text-base md:order-none md:col-span-1 {{ $isNew ? 'font-semibold text-zinc-900' : 'text-zinc-700' }}">{{ $subject($inquiry) ?? __('portal.owner.inquiries.no_subject') }}</span>
              <span class="order-2 md:order-none"><span class="{{ $statusClass($inquiry->status) }} text-xs whitespace-nowrap">{{ __("portal.owner.inquiries.statuses.{$inquiry->status->value}") }}</span></span>
            </a>
          </li>
        @endforeach
      </ul>
    </section>

    @if($inquiries->hasPages())
      <div class="mt-6">
        <x-sun.pagination :paginator="$inquiries" :pages="\App\Themes\SunV2\PageWindow::for($inquiries)" />
      </div>
    @endif
  @endif
@endsection
