{{--
    Ausgabeschema einer Prompt-Vorlage, schreibgeschuetzt (#58, Bauvorgabe in
    design/content-dashboard.md §7b.1 Abschnitt 4 bis 6).

    Bewusst kein Filament-CodeEditor: der reicht `disabled` an CodeMirror
    durch, das Feld verliert contenteditable und faellt mit tabindex="-1" aus
    der Tabulatorfolge. Lesen ist aber der ganze Zweck des Feldes. Deshalb
    eine eigene Leseansicht: <pre> in einer fokussierbaren Scrollflaeche.

    Kein Formularzustand — das Schema wird nirgends dehydriert. Die neue
    Version uebernimmt es in EditPromptTemplate::storeVersion() direkt vom
    Datensatz.

    Erwartet:
      $part     'banners' (Baender ueber dem Editor) oder 'field' (Leseansicht)
      $case     'A' (Code-Vertrag), 'B' (kein Vertrag, kein Schema), 'C' (Schema ohne Leser)
      $json     hübsch gedrucktes Schema oder ''
      $class    Klassenname des Code-Vertrags oder null
      $stale    true = Fall A mit unpassendem oder fehlendem Schema
      $hasSchema
--}}
@php
    $fieldId = 'prompt-output-schema-label';
@endphp

@if ($part === 'banners')
    <div class="space-y-content-2">
        @if ($stale)
            {{-- Band 1: nur Fall A und isSatisfiedBy() === false. --}}
            <div
                class="rounded-content-sm border p-content-3"
                style="background: var(--color-status-failed-bg); border-color: color-mix(in srgb, var(--color-status-failed-fg) 20%, transparent); color: var(--color-status-failed-fg)"
            >
                <p class="text-content-table font-semibold">
                    @if ($hasSchema)
                        {{ __('Wird beim Lauf durch das Schema aus dem Code ersetzt.') }}
                    @else
                        {{ __('Diese Vorlage hat kein Ausgabeschema. Der Lauf verwendet das Schema aus dem Code.') }}
                    @endif
                </p>
            </div>
        @endif

        {{-- Band 2: Wirkungsband aus §7, zweiter Satz bei Fall A und C. --}}
        <div class="rounded-content-sm p-content-3" style="background: var(--color-surface-sunken)">
            <p class="text-content-label text-text-base">
                {{ __('Änderungen wirken auf alle Portale ab dem nächsten Lauf.') }}
                @if ($case !== 'B')
                    {{ __('Der Prompttext ist frei; Variablen und Ausgabeschema sind mit der Pipeline verdrahtet und nur im Code änderbar.') }}
                @endif
            </p>
        </div>
    </div>
@elseif ($case === 'B')
    {{-- Fall B: kein Vertrag, kein Schema. Kein Editor, kein leeres Feld,
         das man versehentlich befuellt. --}}
    <p class="text-content-label text-text-muted">
        {{ __('Diese Vorlage liefert kein strukturiertes Ergebnis.') }}
    </p>
@else
    <div
        role="group"
        aria-labelledby="{{ $fieldId }}"
        class="max-w-[720px]"
        x-data="{ note: '', failed: false, timer: null, copy(text) {
            clearTimeout(this.timer);
            const done = () => { this.failed = false; this.note = @js(__('Schema kopiert.')); };
            const fail = () => { this.failed = true; this.note = @js(__('Kopieren nicht möglich. Text markieren und kopieren.')); };
            try {
                navigator.clipboard.writeText(text).then(done).catch(fail);
            } catch (error) {
                fail();
            }
            this.timer = setTimeout(() => { this.note = ''; this.failed = false; }, 4000);
        } }"
    >
        {{-- Kopfzeile: Beschriftung mit Pille links, Kopierverweis rechts.
             Unter 1024 px bricht sie um, die Klickflaeche bleibt 44 px. --}}
        <div class="flex flex-wrap items-center justify-between gap-content-2">
            <span class="flex items-center gap-content-2">
                <span id="{{ $fieldId }}" class="text-content-label font-medium text-text-strong">
                    {{ __('Ausgabeschema (JSON Schema)') }}
                </span>
                {{-- Herkunftsangabe, kein Zustand: eine Klasse fuer
                     "abweichend" (§7a) und "nicht änderbar" (#64). --}}
                <span class="content-origin-pill">{{ __('nicht änderbar') }}</span>
            </span>
            <button
                type="button"
                class="-my-content-2 -me-content-2 inline-flex min-h-11 items-center px-content-2 text-content-label font-medium text-text-strong underline underline-offset-2"
                x-on:click="copy(@js($json))"
            >
                {{ __('Schema kopieren') }}
            </button>
        </div>

        {{-- Rueckmeldung am Ort der Handlung, kein Toast. --}}
        <p
            role="status"
            aria-live="polite"
            class="mt-content-1 min-h-[1rem] text-content-label"
            x-bind:style="failed ? 'color: var(--color-status-failed-fg)' : 'color: var(--color-text-muted)'"
            x-text="note"
        ></p>

        {{-- Herkunftszeile statt des bisherigen Hilfetexts. Klassenname als
             Klartext, nicht verlinkt: wer das Schema aendern will, weiss
             danach, wo. --}}
        <p class="mt-content-1 text-content-label text-text-muted">
            @if ($case === 'A')
                {{ __('Vom Code vorgegeben —') }}
                <span class="font-mono break-all">{{ $class }}</span>
            @else
                {{ __('Noch nicht mit der Pipeline verdrahtet — dieses Schema wird derzeit von keiner Stufe gelesen.') }}
            @endif
        </p>

        {{-- Scrollflaeche mit Tastaturfokus: ein scrollbarer Bereich ohne
             Fokus ist mit der Tastatur nicht lesbar. 12 Zeilen, das Schema
             von topic_discover ist 60 Zeilen lang. --}}
        <div
            tabindex="0"
            role="region"
            aria-label="{{ __('Ausgabeschema, schreibgeschützt') }}"
            class="mt-content-2 overflow-auto rounded-content-sm p-content-2"
            style="max-height: 18rem; background: var(--color-surface-sunken)"
        >
            <pre class="font-mono text-content-label whitespace-pre" style="color: var(--color-text-base)">{{ $json }}</pre>
        </div>
    </div>
@endif
