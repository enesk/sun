{{--
    Schalter Monatsbericht in den Einstellungen, Theme sun-v2 (#16).
    Logik: App\Livewire\Portal\Company\Dashboard\MonthlyReportSetting. Texte: portal.owner.statistics.report.*
--}}
<li id="monatsbericht" class="flex items-start justify-between gap-4 py-4">
  <div>
    <p class="font-medium text-zinc-900" id="notify-report">{{ __('portal.owner.statistics.report.setting_title') }}</p>
    <p class="text-sm text-zinc-500">{{ __('portal.owner.statistics.report.setting_text') }}</p>
    @unless($allowed)
      <p class="mt-1 text-sm"><span class="pill-brand text-xs">{{ __('portal.owner.statistics.locked.badge') }}</span> {{ __('portal.owner.statistics.report.setting_locked') }} <a href="{{ route('portal.owner.premium') }}" class="text-brand font-medium hover:underline ml-1">{{ __('portal.owner.settings.notifications.learn_more') }}</a></p>
    @endunless
  </div>
  <button type="button" role="switch" aria-checked="{{ $enabled ? 'true' : 'false' }}" aria-labelledby="notify-report" wire:click="toggle" wire:loading.attr="disabled" @disabled(! $allowed)
          class="group relative mt-1 inline-flex h-7 w-12 shrink-0 items-center rounded-full bg-zinc-300 p-0.5 transition-colors aria-checked:bg-brand focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-brand focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 motion-reduce:transition-none">
    <span class="size-6 rounded-full bg-white shadow-sm transition-transform group-aria-checked:translate-x-5 motion-reduce:transition-none"></span>
  </button>
</li>
