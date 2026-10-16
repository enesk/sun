{{--
    Einstellungen › Werbung (#10, docs/ad-slots.md 4.2). Formular über
    ad_settings des oben gewählten Portals; darunter der wirksame Zustand und
    die ads.txt, wie die Domain sie gerade ausliefert.
--}}
<x-filament-panels::page>
    @if ($this->tenant() === null)
        <x-filament::section>
            <p class="text-sm text-gray-600 dark:text-gray-400" role="status">
                {{ __('Es ist noch kein Portal angelegt. Werbung wird je Portal freigegeben.') }}
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

    @if ($this->stateRows() !== [])
        <x-filament::section :heading="__('Wirksamer Zustand')">
            <x-slot name="description">
                {{ __('So steht es nach dem letzten Speichern in der Datenbank — nicht der ungespeicherte Formularstand.') }}
            </x-slot>

            <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($this->stateRows() as $row)
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">{{ $row['label'] }}</dt>
                        <dd class="mt-1 font-medium text-gray-950 dark:text-white">{{ $row['value'] }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-filament::section>

        @php($adsTxt = $this->adsTxtPreview())

        <x-filament::section :heading="__('ads.txt dieser Domain')">
            <x-slot name="description">
                @if ($this->adsTxtUrl())
                    {{ __('Ausgeliefert unter :url', ['url' => $this->adsTxtUrl()]) }}
                @else
                    {{ __('Dieses Portal hat keine Domain hinterlegt.') }}
                @endif
            </x-slot>

            @if ($adsTxt === '')
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ __('Leer — die Adresse antwortet mit 404. Das ist der Zustand ohne Freigabe und ohne Zusatzzeilen.') }}
                </p>
            @else
                <pre class="overflow-x-auto rounded-lg bg-gray-50 p-4 text-xs leading-5 text-gray-950 dark:bg-gray-900 dark:text-gray-100">{{ $adsTxt }}</pre>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
