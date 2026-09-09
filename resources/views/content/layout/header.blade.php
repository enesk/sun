{{--
    Kopfzeile des Content-Panels: globaler Portal-Umschalter (#4).
    Wird per Render-Hook `panels::topbar.start` eingehaengt und von
    App\Content\Livewire\TenantSwitcher gerendert.

    Farben ausschliesslich ueber die Token aus resources/css/content/theme.css.
--}}
<div class="flex items-center gap-2">
    <label
        for="content-tenant-switcher"
        class="hidden text-xs font-medium text-text-muted sm:block"
    >
        {{ __('Portal') }}
    </label>

    <div class="relative">
        <select
            id="content-tenant-switcher"
            wire:model.live="selected"
            @class([
                'h-9 min-w-44 max-w-64 truncate rounded-lg border border-line-strong bg-surface-card',
                'py-0 ps-3 pe-8 text-sm font-medium text-text-strong shadow-sm',
                'focus:border-content-600 focus:ring-1 focus:ring-content-600',
            ])
            aria-label="{{ __('Portal auswählen') }}"
        >
            <option value="{{ \App\Content\Livewire\TenantSwitcher::ALL_PORTALS }}">
                {{ __('Alle Portale') }}
            </option>

            @foreach ($tenants as $tenant)
                <option value="{{ $tenant->getKey() }}">{{ $tenant->name }}</option>
            @endforeach
        </select>

        <span
            wire:loading
            wire:target="selected"
            class="absolute -end-6 top-1/2 -translate-y-1/2 text-xs text-text-muted"
        >
            …
        </span>
    </div>
</div>
