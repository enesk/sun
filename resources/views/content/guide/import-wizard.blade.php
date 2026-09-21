{{--
    Import-Wizard (#15, design/guide-dashboard.md §4), Reiter "Import".
    Vier Schritte im Filament-Wizard; nach "Import abschließen" der
    ImportReport der Liste und je Portal der Bericht der Zuweisung.
--}}
<x-filament-panels::page>
    @if ($result)
        @php
            $report = $result['report'];
            $portals = collect($result['portals']);
        @endphp

        <section class="flex flex-col gap-4 rounded-xl border border-line-strong bg-surface-card p-6">
            <div class="flex items-center gap-3">
                <x-filament::icon icon="heroicon-o-check-circle" class="h-8 w-8 text-status-published-dot" />
                <h2 class="text-content-h2 font-semibold text-text-strong">
                    {{ trans_choice('{0} Keine neuen Themen angelegt|{1} Ein Thema in :portals angelegt|[2,*] :count Themen in :portals angelegt', $result['created'], [
                        'count' => $result['created'],
                        'portals' => trans_choice('{0} keinem Portal|{1} einem Portal|[2,*] :n Portalen', $portals->count(), ['n' => $portals->count()]),
                    ]) }}
                </h2>
            </div>

            <div>
                <h3 class="text-content-h3 font-semibold text-text-strong">{{ __('Themenliste „:name“', ['name' => $result['list_name']]) }}</h3>
                <dl class="mt-2 grid grid-cols-2 gap-3 text-sm md:grid-cols-4">
                    <div><dt class="text-text-muted">{{ __('importiert') }}</dt><dd class="text-lg font-semibold tabular-nums text-text-strong">{{ $report['imported'] }}</dd></div>
                    <div><dt class="text-text-muted">{{ __('aktualisiert') }}</dt><dd class="text-lg font-semibold tabular-nums text-text-strong">{{ $report['updated'] }}</dd></div>
                    <div><dt class="text-text-muted">{{ __('Dubletten übersprungen') }}</dt><dd class="text-lg font-semibold tabular-nums text-text-strong">{{ $report['skipped_duplicates'] }}</dd></div>
                    <div><dt class="text-text-muted">{{ __('Fehlerzeilen') }}</dt><dd class="text-lg font-semibold tabular-nums {{ $report['errors'] !== [] ? 'text-status-failed-fg' : 'text-text-strong' }}">{{ count($report['errors']) }}</dd></div>
                </dl>

                @if ($report['errors'] !== [])
                    <details class="mt-3 text-sm">
                        <summary class="cursor-pointer text-text-base">{{ __('Übersprungene Zeilen anzeigen') }}</summary>
                        <ul class="mt-2 flex flex-col gap-1">
                            @foreach ($report['errors'] as $error)
                                <li class="text-text-base"><span class="tabular-nums">{{ __('Zeile :line', ['line' => $error['line']]) }}</span>: {{ $error['reason'] }}</li>
                            @endforeach
                        </ul>
                    </details>
                @endif
            </div>

            @if ($portals->isNotEmpty())
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-line-soft text-xs uppercase tracking-wide text-text-muted">
                        <tr>
                            <th class="py-2 pr-4 font-medium">Portal</th>
                            <th class="py-2 pr-4 text-right font-medium">{{ __('Angelegt') }}</th>
                            <th class="py-2 pr-4 text-right font-medium">{{ __('Aktualisiert') }}</th>
                            <th class="py-2 pr-4 text-right font-medium">{{ __('Übersprungen') }}</th>
                            <th class="py-2 text-right font-medium">{{ __('Fehler') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line-soft">
                        @foreach ($portals as $portal)
                            <tr>
                                <td class="py-2 pr-4 text-text-strong">
                                    {{ $portal['name'] }}
                                    @foreach ($portal['errors'] as $error)
                                        <span class="block text-xs text-status-failed-fg">{{ $error['line'] > 0 ? __('Position :line', ['line' => $error['line']]).': ' : '' }}{{ $error['reason'] }}</span>
                                    @endforeach
                                </td>
                                <td class="py-2 pr-4 text-right tabular-nums">{{ $portal['imported'] }}</td>
                                <td class="py-2 pr-4 text-right tabular-nums">{{ $portal['updated'] }}</td>
                                <td class="py-2 pr-4 text-right tabular-nums">{{ $portal['skipped_duplicates'] }}</td>
                                <td class="py-2 text-right tabular-nums">{{ count($portal['errors']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            <div class="flex flex-wrap items-center gap-4">
                @if ($result['outline_pending'] > 0)
                    <x-filament::button tag="a" :href="\App\Guide\Filament\Pages\ConfirmOutlines::getUrl()" icon="heroicon-o-list-bullet">
                        {{ __('Gliederungen bestätigen (:count)', ['count' => $result['outline_pending']]) }}
                    </x-filament::button>
                @endif
                <x-filament::link :href="\App\Guide\Filament\Resources\TopicResource::getUrl()">{{ __('Zur Themenliste') }}</x-filament::link>
                <x-filament::link tag="button" color="gray" wire:click="restart">{{ __('Weitere Liste importieren') }}</x-filament::link>
            </div>
        </section>
    @else
        <form wire:submit="finish">
            {{ $this->form }}
        </form>
    @endif
</x-filament-panels::page>
