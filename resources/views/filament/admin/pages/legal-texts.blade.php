{{--
    Einstellungen › Rechtliche Texte (Ticket #17). Eine Fassung für alle
    Portale; die portalspezifischen Angaben kommen beim Ausliefern über die
    Platzhalter dazu.

    Oben zwei Hinweiskästen, weil beide Zustände beim Speichern über die
    Richtigkeit der Pflichtangaben entscheiden:
      1. welche Textarten zentral noch leer sind (dort gilt weiter der
         Portaltext),
      2. welche Portale die Platzhalter nicht auflösen können (dort entstünde
         eine Lücke im Impressum).
--}}
<x-filament-panels::page>
    @php
        $fehlendeArten = $this->missingLabels();
        $offenePortale = $this->unresolvedPortals();
    @endphp

    @if ($fehlendeArten !== [])
        <x-filament::section>
            <div class="flex gap-3">
                <x-filament::icon
                    icon="heroicon-o-information-circle"
                    class="h-5 w-5 shrink-0 text-primary-500"
                />
                <div class="text-sm text-gray-700 dark:text-gray-300">
                    <p class="font-medium text-gray-950 dark:text-white">
                        {{ __('Noch nicht zentral gepflegt') }}
                    </p>
                    <p class="mt-1">
                        {{ __('Für diese Textarten liefert jedes Portal weiter seine eigene Fassung aus:') }}
                        <strong>{{ implode(', ', $fehlendeArten) }}</strong>.
                        {{ __('Sobald hier etwas gespeichert ist, gilt es für alle Portale.') }}
                    </p>
                </div>
            </div>
        </x-filament::section>
    @endif

    @if ($offenePortale !== [])
        <x-filament::section>
            <div class="flex gap-3">
                <x-filament::icon
                    icon="heroicon-o-exclamation-triangle"
                    class="h-5 w-5 shrink-0 text-warning-500"
                />
                <div class="text-sm text-gray-700 dark:text-gray-300">
                    <p class="font-medium text-gray-950 dark:text-white">
                        {{ __('Diese Portale können die Platzhalter nicht auflösen') }}
                    </p>
                    <p class="mt-1">
                        {{ __('Dort bliebe die Stelle im Text leer — bei Pflichtangaben ist das ein Fehler. Bitte die Angaben am Portal nachtragen, bevor Sie hier speichern.') }}
                    </p>
                    <ul class="mt-2 list-disc space-y-1 ps-5">
                        @foreach ($offenePortale as $zeile)
                            <li>
                                <strong>{{ $zeile['portal'] }}</strong> — {{ __('fehlt') }}: {{ $zeile['fehlt'] }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </x-filament::section>
    @endif

    <form wire:submit="save" class="flex flex-col gap-6">
        {{ $this->form }}

        <div>
            <x-filament::button type="submit">
                <x-filament::loading-indicator class="inline h-5 w-5" wire:loading wire:target="save" />
                {{ __('Speichern') }}
            </x-filament::button>
        </div>
    </form>

    <x-filament::section :heading="__('Platzhalter')" collapsible collapsed>
        <x-slot name="description">
            {{ __('Diese Angaben werden beim Ausliefern je Portal eingesetzt. So bleibt eine gemeinsame Fassung auf jeder Domain richtig.') }}
        </x-slot>

        <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
            @foreach ($this->placeholders() as $platzhalter => $erklaerung)
                <div>
                    <dt class="font-mono text-gray-950 dark:text-white">{{ $platzhalter }}</dt>
                    <dd class="mt-1 text-gray-600 dark:text-gray-400">{{ $erklaerung }}</dd>
                </div>
            @endforeach
        </dl>

        <p class="mt-4 text-sm text-warning-600 dark:text-warning-400">
            {{ __('Achtung: [BETREIBER_NAME] setzt den Namen des Portals ein, nicht den des Betreibers. Für die Angabe nach § 5 DDG gehört der Betreibername ausgeschrieben in den Text.') }}
        </p>
    </x-filament::section>
</x-filament-panels::page>
