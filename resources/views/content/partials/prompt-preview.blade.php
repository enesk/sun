{{--
    Vorschau einer Prompt-Vorlage (#43, zuvor der Testlauf-Dialog aus #20).

    Zweite Spalte des Editors (design/content-dashboard.md, §7): derselbe
    Text mit eingesetzten Beispielwerten, die eingesetzten Stellen markiert.
    Es wird ausdruecklich kein Modell aufgerufen — die Vorschau beantwortet,
    ob die Platzhalter aufgehen, nicht, ob die Antwort gut ist. Genau das ist
    der Fehler, der in der Praxis passiert, und er kostet hier nichts.
--}}
<div class="space-y-content-4">
    <div>
        <h3 class="text-content-h3 font-semibold text-text-strong">{{ __('Vorschau mit Beispielwerten') }}</h3>
        <p class="mt-content-1 text-content-label text-text-muted">
            {{ __('Es wird kein Modell aufgerufen und nichts berechnet. Eingesetzte Werte sind markiert.') }}
        </p>
    </div>

    @if ($result['missing'] !== [])
        <div
            class="rounded-content-lg border-s-[3px] p-content-3"
            style="background: var(--color-status-failed-bg); border-color: var(--color-status-failed-dot)"
        >
            <p class="text-content-table font-semibold" style="color: var(--color-status-failed-fg)">
                {{ __('Für diese Platzhalter fehlt ein Beispielwert:') }}
            </p>
            <p class="mt-content-1 font-mono text-content-label text-text-base">
                {{ implode(', ', $result['missing']) }}
            </p>
            <p class="mt-content-2 text-content-label text-text-muted">
                {{ __('Beispielwerte stehen im Feld "Erwartete Variablen".') }}
            </p>
        </div>
    @elseif ($result['error'])
        <div
            class="rounded-content-lg border-s-[3px] p-content-3"
            style="background: var(--color-status-failed-bg); border-color: var(--color-status-failed-dot)"
        >
            <p class="text-content-body" style="color: var(--color-status-failed-fg)">{{ $result['error'] }}</p>
        </div>
    @else
        @if ($result['system'])
            <section>
                <h4 class="text-content-label font-semibold uppercase text-text-muted">{{ __('System-Prompt') }}</h4>
                <pre class="mt-content-2 max-h-64 overflow-auto whitespace-pre-wrap rounded-content-md bg-surface-sunken p-content-3 font-mono text-content-label text-text-base">{{ $result['system'] }}</pre>
            </section>
        @endif

        <section>
            <h4 class="text-content-label font-semibold uppercase text-text-muted">{{ __('User-Prompt') }}</h4>
            <pre class="mt-content-2 max-h-96 overflow-auto whitespace-pre-wrap rounded-content-md bg-surface-sunken p-content-3 font-mono text-content-label text-text-base">{{ $result['user'] }}</pre>
        </section>

        <p class="text-content-label text-text-muted">
            {{ trans_choice(
                '{0}Ohne Beispielwerte eingesetzt.|{1}Ein Beispielwert eingesetzt.|[2,*]:count Beispielwerte eingesetzt.',
                count($result['variables']),
                ['count' => count($result['variables'])],
            ) }}
        </p>
    @endif
</div>
