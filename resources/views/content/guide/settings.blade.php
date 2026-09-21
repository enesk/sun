{{--
    Einstellungen › Portal (#16, design/guide-dashboard.md §9). Formular über
    tenant_guide_settings des oben gewählten Portals; darunter die Werte aus
    der Serverkonfiguration als Anzeige (kein folgenloses Feld).
--}}
<x-filament-panels::page>
    @if ($this->tenant() === null)
        <div class="rounded-content-md border-s-[3px] px-content-4 py-content-3 text-content-body text-text-base" style="background: var(--color-status-review-bg); border-color: var(--color-status-review-dot)" role="status">
            {{ __('Diese Einstellungen gelten je Portal. Bitte oben in der Kopfzeile ein Portal auswählen.') }}
        </div>
    @endif

    <form wire:submit="save" class="flex flex-col gap-content-6">
        {{ $this->form }}

        <div>
            <x-filament::button type="submit" :disabled="$this->tenant() === null">
                <x-filament::loading-indicator class="inline h-5 w-5" wire:loading wire:target="save" />
                {{ __('Speichern') }}
            </x-filament::button>
        </div>
    </form>

    <section class="rounded-content-lg bg-surface-card p-content-6 shadow-content-card">
        <h2 class="text-content-h3 font-semibold text-text-strong">{{ __('Aus der Serverkonfiguration') }}</h2>
        <p class="mt-content-1 text-content-table text-text-base">{{ __('Änderungen an diesen Werten erfolgen in der Serverkonfiguration.') }}</p>
        <dl class="mt-content-4 grid grid-cols-1 gap-content-3 text-content-table sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->configValues() as $row)
                <div>
                    <dt class="flex items-center gap-content-2 text-text-muted">
                        {{ $row['label'] }}
                        <span class="content-origin-pill">{{ __('aus der Serverkonfiguration') }}</span>
                    </dt>
                    <dd class="mt-content-1 font-medium tabular-nums text-text-strong">{{ $row['value'] }}</dd>
                </div>
            @endforeach
        </dl>
    </section>
</x-filament-panels::page>
