{{--
    Einstellungen › Verdächtige Accounts/Einträge (#10, docs/turnstile.md §7).

    Oben die markierten Konten über alle Portale (zentrale `users`), darunter
    die markierten Einträge des gewählten Portals (`companies` je Tenant-DB).
    Die untere Tabelle trägt den Portalschlüssel im wire:key — ein
    Portalwechsel baut sie neu auf, weil sie an der Datenbankverbindung des
    Portals hängt.
--}}
<x-filament-panels::page>
    <x-filament::section :heading="__('Bewertung')" collapsible collapsed>
        <x-slot name="description">
            {{ __('Ab :threshold von :max Punkten gilt ein Datensatz als sichtungswürdig. Die Gewichte stehen in config/antispam.php.', [
                'threshold' => $this->threshold(),
                'max' => \App\AntiSpam\BotScorer::MAX_SCORE,
            ]) }}
        </x-slot>

        <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->weights() as $code => $weight)
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">{{ $code }}</dt>
                    <dd class="mt-1 font-medium text-gray-950 dark:text-white">+{{ $weight }}</dd>
                </div>
            @endforeach
        </dl>
    </x-filament::section>

    @livewire(\App\Filament\Admin\Widgets\SuspectedAccountsTable::class)

    <div>
        <label for="suspected-bots-portal" class="text-sm font-medium text-gray-950 dark:text-white">
            {{ __('Portal für die Einträge') }}
        </label>

        <x-filament::input.wrapper class="mt-1 max-w-md">
            <x-filament::input.select id="suspected-bots-portal" wire:model.live="tenantId">
                <option value="">{{ __('Portal wählen') }}</option>
                @foreach ($this->portalOptions() as $id => $label)
                    <option value="{{ $id }}">{{ $label }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
    </div>

    @if ($this->tenantId === null)
        <x-filament::section>
            <p class="text-sm text-gray-600 dark:text-gray-400" role="status">
                {{ __('Einträge liegen je Portal in einer eigenen Datenbank. Bitte oben ein Portal wählen.') }}
            </p>
        </x-filament::section>
    @else
        @livewire(
            \App\Filament\Admin\Widgets\SuspectedListingsTable::class,
            ['tenantId' => $this->tenantId],
            key('suspected-listings-' . $this->tenantId)
        )
    @endif
</x-filament-panels::page>
