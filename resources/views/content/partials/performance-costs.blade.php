{{--
    Leistung, Reiter "Kosten" (#25) — nur fuer die Rolle `owner`.

    Kosten aus llm_usage_logs, Ertrag aus den AdSense-Spalten der
    Metrikzeilen, beides in USD. Ein Portal ohne AdSense-Zuordnung zeigt
    "nicht gemessen" und keine Deckung: null heisst nicht "kein Ertrag".

    Erwartete Variablen: $costs, $days, $int, $dec.
--}}
<div class="mt-content-8 overflow-hidden rounded-content-lg bg-surface-card">
    @if ($costs === [])
        <p class="p-content-8 text-center text-content-body text-text-muted">
            {{ __('Für diesen Zeitraum sind keine Kosten gebucht.') }}
        </p>
    @else
        <table class="w-full table-fixed border-collapse text-content-table">
            <thead>
                <tr class="bg-surface-sunken">
                    <th scope="col" class="h-10 px-content-3 text-start text-content-label font-medium text-text-base">
                        {{ __('Portal') }}
                    </th>
                    <th scope="col" class="h-10 w-[130px] px-content-3 text-end text-content-label font-medium text-text-base">
                        {{ __('Kosten (USD)') }}
                    </th>
                    <th scope="col" class="h-10 w-[130px] px-content-3 text-end text-content-label font-medium text-text-base">
                        {{ __('Ertrag (USD)') }}
                    </th>
                    <th scope="col" class="h-10 w-[110px] px-content-3 text-end text-content-label font-medium text-text-base">
                        {{ __('Aufrufe') }}
                    </th>
                    <th scope="col" class="h-10 w-[200px] px-content-3 text-start text-content-label font-medium text-text-base">
                        {{ __('Deckung') }}
                    </th>
                </tr>
            </thead>

            <tbody>
                @foreach ($costs as $row)
                    <tr wire:key="cost-{{ $row['tenant_id'] }}" class="h-11 border-t border-line-soft">
                        <td class="max-w-0 px-content-3">
                            <span class="block truncate text-text-strong" title="{{ $row['portal'] }}">{{ $row['portal'] }}</span>
                        </td>
                        <td class="px-content-3 text-end tabular-nums text-text-base">{{ $dec($row['cost_usd'], 2) }}</td>
                        <td class="px-content-3 text-end tabular-nums text-text-base">
                            {{ $row['revenue_usd'] === null ? __('nicht gemessen') : $dec($row['revenue_usd'], 2) }}
                        </td>
                        <td class="px-content-3 text-end tabular-nums text-text-base">{{ $int($row['requests']) }}</td>
                        <td class="px-content-3 text-text-base">
                            @if ($row['coverage'] === null)
                                <span class="text-text-muted">{{ __('nicht bewertbar') }}</span>
                            @else
                                <span style="color: {{ $row['coverage'] >= 1.0 ? 'var(--color-score-good)' : 'var(--color-score-mid)' }}">
                                    {{ __(':percent % der Kosten gedeckt', [
                                        'percent' => number_format($row['coverage'] * 100, 0, ',', '.'),
                                    ]) }}
                                </span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<p class="mt-content-3 text-content-label text-text-muted">
    {{ __('Kosten aus den Modellaufrufen der letzten :days Tage (llm_usage_logs), Ertrag aus den AdSense-Zahlen derselben Erhebung.', ['days' => $days]) }}
</p>
