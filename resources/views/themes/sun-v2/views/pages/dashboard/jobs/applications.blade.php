{{--
    Bewerbungen zu einer Stellenanzeige im Betriebsbereich, Theme sun-v2.
    Daten: OwnerJobController::applications(). Texte: lang/de/portal.php (owner.jobs.applications.*).
    Kein btn-primary: jede Bewerbung hat dieselben Aktionen, keine soll hervorstechen.
--}}
@extends('layouts.panel')

@php
    $total = $applications->total();
@endphp

@section('title', __('portal.owner.jobs.applications.page_title', ['titel' => $job->title]))

@section('content')
  <a href="{{ route('portal.owner.jobs.index') }}" class="btn-ghost -ml-3 px-3">
    <x-sun.icon name="arrow-left" class="icon" />{{ __('portal.owner.jobs.back') }}
  </a>
  <div class="mt-2">
    <h1 class="text-3xl md:text-4xl font-bold tracking-tight text-zinc-900">{{ __('portal.owner.jobs.applications.title') }}</h1>
    <p class="mt-1 text-base text-zinc-500 break-words">{{ $job->title }}, {{ trans_choice('portal.owner.jobs.applications.count', $total, ['anzahl' => number_format($total, 0, ',', '.')]) }}</p>
  </div>

  {{-- Stelle --}}
  <div class="card mt-6 flex flex-wrap items-center gap-2 p-4 md:p-5">
    <x-sun.icon name="briefcase" class="icon text-zinc-500" />
    <span class="font-medium text-zinc-900 min-w-0 break-words">{{ $job->title }}</span>
    <span class="pill text-xs">{{ __("portal.owner.jobs.employment_types.{$job->employment_type}") }}</span>
    @if($job->is_live)
      <span class="pill text-xs bg-emerald-50 text-emerald-700">{{ __('portal.owner.jobs.status.active') }}</span>
    @else
      <span class="pill text-xs">{{ __('portal.owner.jobs.status.inactive') }}</span>
    @endif
  </div>

  @if($applications->isEmpty())
    <div class="card mt-4 p-6 md:p-8 text-center">
      <span class="mx-auto flex size-12 items-center justify-center rounded-full bg-brand-50 text-brand-700" aria-hidden="true"><x-sun.icon name="users" class="size-6" /></span>
      <h2 class="mt-3 text-lg font-semibold text-zinc-900">{{ __('portal.owner.jobs.applications.empty.title') }}</h2>
      <p class="mx-auto mt-1 max-w-md text-base text-zinc-500">{{ __('portal.owner.jobs.applications.empty.text') }}</p>
    </div>
  @else
    <ul class="mt-4 space-y-4" aria-label="{{ __('portal.owner.jobs.applications.title') }}">
      @foreach($applications as $application)
        @php
            $statusUrl = route('portal.owner.jobs.applications.status', [$job->id, $application->id]);
            $cvUrl = $application->getFirstMediaUrl('cv');
            $replySubject = rawurlencode(__('portal.owner.jobs.applications.reply_subject', ['titel' => $job->title]));
        @endphp
        <li>
          <article class="card p-5 md:p-6" aria-label="{{ __('portal.owner.jobs.applications.aria', ['name' => $application->applicant_name]) }}">
            <div class="flex flex-wrap items-center gap-2">
              <h3 class="text-lg font-semibold text-zinc-900 min-w-0 break-words">{{ $application->applicant_name }}</h3>
              <span class="pill text-xs {{ $application->status === 'contacted' ? 'bg-emerald-50 text-emerald-700' : '' }}">{{ __("portal.owner.jobs.applications.statuses.{$application->status}") }}</span>
            </div>

            <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-base">
              <li>
                <a href="mailto:{{ $application->applicant_email }}" class="inline-flex min-h-11 items-center gap-1.5 text-brand break-all hover:underline md:min-h-0" aria-label="{{ __('portal.owner.jobs.applications.email_aria', ['name' => $application->applicant_name]) }}">
                  <x-sun.icon name="mail" class="size-4 shrink-0" />{{ $application->applicant_email }}
                </a>
              </li>
              @if($application->applicant_phone)
                <li>
                  <x-phone-link :number="$application->applicant_phone" class="inline-flex min-h-11 items-center gap-1.5 text-brand hover:underline md:min-h-0" fallback-class="inline-flex min-h-11 items-center text-zinc-700 md:min-h-0" aria-label="{{ __('portal.owner.jobs.applications.call_aria', ['name' => $application->applicant_name]) }}">
                    <x-sun.icon name="phone" class="size-4 shrink-0" />{{ \App\Support\PhoneNumber::display($application->applicant_phone) }}
                  </x-phone-link>
                </li>
              @endif
              <li class="inline-flex min-h-11 items-center text-sm text-zinc-500 md:min-h-0">
                <time datetime="{{ $application->created_at->toIso8601String() }}" title="{{ $application->created_at->format('d.m.Y H:i') }}">{{ $application->created_at->locale('de')->diffForHumans() }}</time>
              </li>
            </ul>

            @if($application->message)
              <details class="group mt-2">
                <summary class="inline-flex min-h-11 cursor-pointer list-none items-center gap-1.5 rounded text-base font-medium text-brand hover:underline focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 [&::-webkit-details-marker]:hidden">
                  <x-sun.icon name="chevron-right" class="size-4 shrink-0 transition-transform group-open:rotate-90 motion-reduce:transition-none" />
                  <span class="group-open:hidden">{{ __('portal.owner.jobs.applications.message_show') }}</span><span class="hidden group-open:inline">{{ __('portal.owner.jobs.applications.message_hide') }}</span>
                </summary>
                <div class="mt-2 rounded-xl bg-zinc-50 p-4 text-base leading-relaxed text-zinc-700 whitespace-pre-line break-words">{{ $application->message }}</div>
              </details>
            @endif

            @if($cvUrl)
              <a href="{{ $cvUrl }}" target="_blank" rel="noopener" class="btn-ghost mt-2 -ml-3 px-3">
                <x-sun.icon name="download" class="icon" />{{ __('portal.owner.jobs.applications.cv') }}
              </a>
            @endif

            @if($application->status !== 'rejected')
              <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-zinc-200 pt-4" role="group" aria-label="{{ __('portal.owner.jobs.applications.actions', ['name' => $application->applicant_name]) }}">
                @if(in_array($application->status, ['pending', 'reviewed']))
                  <a href="mailto:{{ $application->applicant_email }}?subject={{ $replySubject }}" class="btn-secondary px-4" aria-label="{{ __('portal.owner.jobs.applications.reply_aria', ['name' => $application->applicant_name]) }}">
                    <x-sun.icon name="mail" class="icon" />{{ __('portal.owner.jobs.applications.reply') }}
                  </a>
                @endif

                @if($application->status === 'pending')
                  <form action="{{ $statusUrl }}" method="POST">
                    @csrf
                    <input type="hidden" name="status" value="reviewed">
                    <button type="submit" class="btn-secondary px-4"><x-sun.icon name="eye" class="icon" />{{ __('portal.owner.jobs.applications.mark_reviewed') }}</button>
                  </form>
                @endif

                @if(in_array($application->status, ['pending', 'reviewed']))
                  <form action="{{ $statusUrl }}" method="POST">
                    @csrf
                    <input type="hidden" name="status" value="contacted">
                    <button type="submit" class="btn-secondary px-4"><x-sun.icon name="check" class="icon text-emerald-600" />{{ __('portal.owner.jobs.applications.mark_contacted') }}</button>
                  </form>
                @endif

                <form action="{{ $statusUrl }}" method="POST">
                  @csrf
                  <input type="hidden" name="status" value="rejected">
                  <button type="submit" class="btn-ghost px-4 text-red-600 hover:bg-red-50" aria-label="{{ __('portal.owner.jobs.applications.reject_aria', ['name' => $application->applicant_name]) }}">
                    <x-sun.icon name="x" class="icon" />{{ __('portal.owner.jobs.applications.reject') }}
                  </button>
                </form>
              </div>
            @endif
          </article>
        </li>
      @endforeach
    </ul>

    @if($applications->hasPages())
      <x-sun.pagination :paginator="$applications" :pages="\App\Themes\SunV2\PageWindow::for($applications)" />
    @endif
  @endif
@endsection
