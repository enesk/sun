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

        {{-- Abgeschaltete Grenzen (#114): eine 0 laesst den BudgetGuard die
             Pruefung ueberspringen. Der Kasten erscheint nur dann, damit im
             Normalzustand keine Dauerwarnung steht. role="status", weil er
             einen Zustand beschreibt und nicht auf eine Eingabe antwortet.
             Flaeche, Rahmen und Symbol tragen die Warnfarbe, der Fliesstext
             bleibt Textfarbe — #d97706 verfehlt auf hellem Grund 4,5:1. --}}
        @php($disabledBudgets = $this->getDisabledBudgetSources())

        @if ($disabledBudgets !== [])
            <div
                class="mt-content-4 flex items-start gap-content-3 rounded-content-lg border p-content-4 text-content-body"
                style="background: var(--color-status-review-bg); border-color: var(--color-status-review-fill)"
                role="status"
            >
                <svg
                    class="h-5 w-5 shrink-0"
                    style="color: var(--color-status-review-fill)"
                    fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z" />
                </svg>

                <div class="text-text-base">
                    <p>{{ __('Mindestens eine Budgetgrenze ist abgeschaltet. Ein Wert von 0 bedeutet keine Grenze, nicht kein Budget.') }}</p>

                    <ul class="mt-content-2 list-disc ps-content-4">
                        @foreach ($disabledBudgets as $source)
                            <li>{{ $source }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <table class="mt-content-3 w-full text-content-table">
            <tbody class="divide-y divide-line-soft">
                @foreach ($this->getBudgetRows() as $row)
                    <tr>
                        <td class="py-content-2 pe-content-3 text-text-base">{{ $row['label'] }}</td>
                        <td class="py-content-2 pe-content-3">
                            @if ($row['unlimited'])
                                {{-- Kein Geldbetrag daneben: zwei Angaben, die
                                     einander widersprechen, waeren schlimmer als
                                     gar keine. Der Klartext traegt die Aussage,
                                     nicht die Farbe. --}}
                                <span class="font-semibold" style="color: var(--color-status-review-fg)">{{ __('Ohne Grenze') }}</span>
                                <span class="text-text-base">{{ __('(0 schaltet die Prüfung ab)') }}</span>
                            @else
                                <span class="font-semibold text-text-strong">{{ $row['value'] }}</span>
                            @endif
                        </td>
                        <td class="py-content-2 text-content-label text-text-muted">{{ $row['source'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    {{-- Search Console je Portal (#116, Vorgabe §3): dieselben sechs
         Zustände wie oben, aber nur als Pille und über alle Portale.
         Schlechtester Zustand zuerst — die offenen Portale sind der Grund,
         warum es diese Tabelle gibt. --}}
    <section class="mt-content-8 rounded-content-lg bg-surface-card p-content-6 shadow-content-card">
        <h3 class="text-content-h3 font-semibold text-text-strong">{{ __('Search Console je Portal') }}</h3>
        <p class="mt-content-1 text-content-label text-text-muted">
            {{ __('Zustand aller Portale, schlechtester zuerst. Geprüft wird je Portal in der Sektion „Messung (Search Console)“.') }}
        </p>

        <table class="mt-content-3 w-full text-content-table">
            <thead>
                <tr class="text-content-label text-text-muted">
                    <th scope="col" class="py-content-2 pe-content-3 text-start">Portal</th>
                    <th scope="col" class="py-content-2 pe-content-3 text-start">{{ __('Search Console') }}</th>
                    <th scope="col" class="py-content-2 text-start">{{ __('Property') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line-soft">
                @foreach ($this->getGscOverviewRows() as $row)
                    <tr>
                        <td class="py-content-2 pe-content-3 text-text-base">{{ $row['tenant']->name }}</td>
                        <td class="py-content-2 pe-content-3">
                            <span class="content-status content-status--{{ $row['state']['class'] }}">{{ $row['state']['label'] }}</span>
                        </td>
                        <td class="py-content-2 text-content-label text-text-muted">{{ $row['property'] ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    <x-filament-actions::modals />
</x-filament-panels::page>
