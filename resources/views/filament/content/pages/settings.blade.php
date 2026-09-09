{{--
    Einstellungen (#20), Reiter "Portal". Alle Felder aus
    tenant_content_settings des oben gewaehlten Portals, dazu der
    Budget-Abschnitt als Anzeige aus der Serverkonfiguration.
--}}
<x-filament-panels::page>
    @include('content.partials.settings-tabs', ['active' => 'portal'])

    @unless ($this->hasPortal())
        <div
            class="mb-content-4 rounded-content-lg border-s-[3px] p-content-4"
            style="background: var(--color-status-review-bg); border-color: var(--color-status-review-dot)"
        >
            <p class="text-content-body text-text-base">
                {{ __('Diese Einstellungen gelten je Portal. Bitte oben in der Kopfzeile ein Portal auswählen.') }}
            </p>
        </div>
    @endunless

    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-content-6 flex gap-content-3">
            <x-filament::button type="submit" :disabled="! $this->hasPortal()">
                <x-filament::loading-indicator class="inline h-5 w-5" wire:loading wire:target="save" />
                {{ __('Speichern') }}
            </x-filament::button>
        </div>
    </form>

    {{-- Budget: Anzeige, kein Formular. Die Werte kommen aus .env und sind im
         Panel nicht änderbar — ein Feld, das aussieht wie änderbar und beim
         Speichern nichts tut, wäre schlimmer als ein gesperrtes Feld. --}}
    <section class="mt-content-8 rounded-content-lg bg-surface-card p-content-6 shadow-content-card">
        <h3 class="text-content-h3 font-semibold text-text-strong">{{ __('Budget') }}</h3>
        <p class="mt-content-1 text-content-label text-text-muted">
            {{ __('Gilt netzwerkweit und stammt aus der Serverkonfiguration. Änderung nur über die Umgebungsvariable.') }}
        </p>

        <table class="mt-content-3 w-full text-content-table">
            <tbody class="divide-y divide-line-soft">
                @foreach ($this->getBudgetRows() as $row)
                    <tr>
                        <td class="py-content-2 pe-content-3 text-text-base">{{ $row['label'] }}</td>
                        <td class="py-content-2 pe-content-3 font-semibold text-text-strong">{{ $row['value'] }}</td>
                        <td class="py-content-2 text-content-label text-text-muted">{{ $row['source'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    <x-filament-actions::modals />
</x-filament-panels::page>
