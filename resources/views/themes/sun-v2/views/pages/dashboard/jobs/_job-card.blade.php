{{--
    Karte einer Stellenanzeige im Betriebsbereich, Theme sun-v2.
    Parameter: $job (App\Models\Portal\Job), $expired (optional, bool). Texte: lang/de/portal.php (owner.jobs.card.*).
--}}
@php
    $isExpired = isset($expired) && $expired;
    $isRunning = $job->is_active && ! $isExpired;
    $applicationsCount = (int) $job->applications_count;
@endphp
<article class="card p-5 md:p-6" aria-label="{{ $isExpired ? __('portal.owner.jobs.card.aria_expired', ['titel' => $job->title]) : $job->title }}">
  <div class="flex flex-wrap items-center gap-2">
    <h3 class="text-lg font-semibold text-zinc-900 min-w-0 break-words">{{ $job->title }}</h3>
    <span class="pill text-xs">{{ __("portal.owner.jobs.employment_types.{$job->employment_type}") }}</span>
    @if($isExpired)
      <span class="pill text-xs">{{ __('portal.owner.jobs.status.expired') }}</span>
    @elseif(! $job->is_active)
      <span class="pill text-xs">{{ __('portal.owner.jobs.status.inactive') }}</span>
    @else
      <span class="pill text-xs bg-emerald-50 text-emerald-700">{{ __('portal.owner.jobs.status.active') }}</span>
    @endif
  </div>

  <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-zinc-500">
    @if($job->location_display)
      <li class="flex items-center gap-1.5"><x-sun.icon name="map-pin" class="size-4 shrink-0" />{{ $job->location_display }}</li>
    @endif
    @if($job->salary_display)
      <li class="flex items-center gap-1.5"><x-sun.icon name="euro" class="size-4 shrink-0" />{{ $job->salary_display }}</li>
    @endif
    @if($job->is_live)
      <li class="flex items-center gap-1.5"><x-sun.icon name="clock" class="size-4 shrink-0" />{{ trans_choice('portal.owner.jobs.card.days_remaining', $job->days_remaining, ['anzahl' => $job->days_remaining]) }}</li>
    @elseif($job->expires_at)
      <li class="flex items-center gap-1.5"><x-sun.icon name="clock" class="size-4 shrink-0" />{{ __('portal.owner.jobs.card.expired_on', ['datum' => $job->expires_at->format('d.m.Y')]) }}</li>
    @endif
  </ul>

  <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-base">
    <span class="flex items-center gap-1.5 text-zinc-700"><x-sun.icon name="eye" class="size-4 shrink-0 text-zinc-500" />{{ trans_choice('portal.owner.jobs.card.views', (int) $job->views_count, ['anzahl' => number_format((int) $job->views_count, 0, ',', '.')]) }}</span>
    <a href="{{ route('portal.owner.jobs.applications', $job->id) }}" class="inline-flex min-h-11 items-center gap-1.5 font-medium text-brand hover:underline md:min-h-0 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 rounded">
      <x-sun.icon name="users" class="size-4 shrink-0" />{{ trans_choice('portal.owner.jobs.card.applications', $applicationsCount, ['anzahl' => number_format($applicationsCount, 0, ',', '.')]) }}
    </a>
  </div>

  <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-zinc-200 pt-4" role="group" aria-label="{{ __('portal.owner.jobs.card.actions', ['titel' => $job->title]) }}">
    <div class="flex flex-wrap items-center gap-2">
      @unless($isExpired)
        <a href="{{ route('portal.owner.jobs.edit', $job->id) }}" class="btn-secondary px-4" aria-label="{{ __('portal.owner.jobs.card.edit_aria', ['titel' => $job->title]) }}">
          <x-sun.icon name="pencil" class="icon" />{{ __('portal.owner.jobs.card.edit') }}
        </a>
      @endunless

      <form action="{{ route('portal.owner.jobs.toggle', $job->id) }}" method="POST">
        @csrf
        <button type="submit" class="btn-secondary px-4" aria-label="{{ $isRunning ? __('portal.owner.jobs.card.deactivate_aria', ['titel' => $job->title]) : __('portal.owner.jobs.card.reactivate_aria', ['titel' => $job->title]) }}">
          <x-sun.icon :name="$isRunning ? 'pause' : 'play'" class="icon" />{{ $isRunning ? __('portal.owner.jobs.card.deactivate') : __('portal.owner.jobs.card.reactivate') }}
        </button>
      </form>

      {{-- Rückfrage ohne JavaScript: "Löschen" klappt die Bestätigung auf, ein zweiter Klick schließt sie wieder --}}
      <details class="open:w-full">
        <summary class="btn-ghost list-none cursor-pointer px-4 text-red-600 hover:bg-red-50 [&::-webkit-details-marker]:hidden" aria-label="{{ __('portal.owner.jobs.card.delete_aria', ['titel' => $job->title]) }}">
          <x-sun.icon name="trash" class="icon" />{{ __('portal.owner.jobs.card.delete') }}
        </summary>
        <div class="mt-3 flex flex-wrap items-center gap-2 rounded-xl bg-zinc-50 p-4" role="alertdialog" aria-label="{{ __('portal.owner.jobs.card.confirm_label') }}">
        <p class="w-full text-base text-zinc-700 sm:w-auto sm:flex-1">{{ __('portal.owner.jobs.card.confirm_text') }}</p>
        <form action="{{ route('portal.owner.jobs.destroy', $job->id) }}" method="POST">
          @csrf
          @method('DELETE')
          <button type="submit" class="btn-secondary px-4 border-red-600 text-red-600 hover:bg-red-50">
            <x-sun.icon name="trash" class="icon" />{{ __('portal.owner.jobs.card.confirm_delete') }}
          </button>
        </form>
        </div>
      </details>
    </div>
  </div>
</article>
