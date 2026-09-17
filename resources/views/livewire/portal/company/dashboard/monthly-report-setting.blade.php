{{--
    Schalter Monatsbericht, Default-/Starter-Theme (#16).
    Logik: App\Livewire\Portal\Company\Dashboard\MonthlyReportSetting. Texte: portal.owner.statistics.report.*
--}}
<div id="monatsbericht" class="flex items-start justify-between gap-4 py-3">
    <div>
        <p class="font-medium">{{ __('portal.owner.statistics.report.setting_title') }}</p>
        <p class="text-sm" style="color: var(--dash-text-secondary)">{{ $allowed ? __('portal.owner.statistics.report.setting_text') : __('portal.owner.statistics.report.setting_locked') }}</p>
    </div>
    <button type="button" role="switch" aria-checked="{{ $enabled ? 'true' : 'false' }}" wire:click="toggle" @disabled(! $allowed)
            class="dash-btn dash-btn-sm {{ $enabled ? 'dash-btn-primary' : 'dash-btn-secondary' }}">
        {{ $enabled ? __('An') : __('Aus') }}
    </button>
</div>
