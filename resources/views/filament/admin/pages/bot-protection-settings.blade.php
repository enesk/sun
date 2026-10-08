{{--
    Einstellungen › Bot-Schutz (#9, docs/turnstile.md §4). Formular über
    tenant_turnstile_settings des oben gewählten Portals; darunter die Werte
    aus der Serverkonfiguration als Anzeige, damit sichtbar ist, worauf ein
    leeres Feld zurückfällt.
--}}
<x-filament-panels::page>
    @if ($this->tenant() === null)
        <x-filament::section>
            <p class="text-sm text-gray-600 dark:text-gray-400" role="status">
                {{ __('Es ist noch kein Portal angelegt. Der Bot-Schutz wird je Portal eingestellt.') }}
            </p>
        </x-filament::section>
    @endif

    <form wire:submit="save" class="flex flex-col gap-6">
        {{ $this->form }}

        <div>
            <x-filament::button type="submit" :disabled="$this->tenant() === null">
                <x-filament::loading-indicator class="inline h-5 w-5" wire:loading wire:target="save" />
                {{ __('Speichern') }}
            </x-filament::button>
        </div>
    </form>

    @if ($this->configRows() !== [])
        <x-filament::section :heading="__('Aus der Serverkonfiguration')">
            <x-slot name="description">
                {{ __('Diese Werte gelten, solange oben nichts eingetragen ist. Geändert werden sie in config/turnstile.php und in der .env.') }}
            </x-slot>

            <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->configRows() as $row)
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">{{ $row['label'] }}</dt>
                        <dd class="mt-1 font-medium text-gray-950 dark:text-white">{{ $row['value'] }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-filament::section>
    @endif
</x-filament-panels::page>
