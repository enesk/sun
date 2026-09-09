{{--
    Pruefflaeche eines Artikels (#20), design/content-dashboard.md, §4.

    Alle Abschnitte offen untereinander — jeder Reiterwechsel kostet Sekunden,
    und das Ziel ist eine Entscheidung in unter 90 Sekunden:

      1. Artikelvorschau im echten Frontend-Template (signierte Vorschau-Route)
      1b. Bilder: Titelbild, Herkunft, Alt-Text, Infografik und Meldungen (#71)
      2. Qualitaetsreport: Gesamtscore, Rubriken, blockierende Befunde, SEO-Lint
      3. Faktencheck gegen die belegten Quellen
      4. Quellenliste
      5. Vergleich mit der stehenden Fassung, wenn es eine Aktualisierung ist

    Darunter klebend die Entscheidungsleiste.
--}}
@php
    $quality = $detail['quality'];
    $level = $quality['level'] ?? 'mid';

    // Budgetstand kommt aus ReviewQueue::render(); eine Abfrage je
    // Seitenaufbau, nicht je Schaltflaeche (#42, §4).
    $budget = $generationBudget ?? ['available' => true, 'reason' => null];
    $budgetBlocks = ! $budget['available'];
@endphp

<div class="space-y-content-6">
    {{-- Aktualisierungsstand (#99). Steht ueber allem anderen: die Frage
         "warum wurde dieser Artikel nicht aktualisiert?" beantwortet sich
         sonst nur ueber die Datenbank. Der Link auf die Kindfassung greift
         nur, solange sie selbst in der Warteschlange steht — sonst spraenge
         die Auswahl beim naechsten Aufbau zurueck. --}}
    @php
        $refreshState = $detail['refresh'] ?? null;
        $refreshChildKey = ($refreshState['child_status'] ?? null) === 'review' && ($refreshState['child_id'] ?? null)
            ? \App\Content\Support\PipelineCardKey::draft((int) $detail['tenant_id'], (int) $refreshState['child_id'])->toString()
            : null;
    @endphp

    @include('content.partials.refresh-strip', [
        'refresh' => $refreshState,
        'childClick' => $refreshChildKey === null ? null : "select('{$refreshChildKey}')",
    ])

    <header class="rounded-content-lg bg-surface-card p-content-6 shadow-content-card">
        <div class="flex flex-wrap items-center gap-content-2">
            <span class="content-status content-status--{{ $detail['status'] }}">{{ $detail['status_label'] }}</span>
            <span class="text-content-label text-text-muted">{{ $detail['tenant'] }}</span>
            <span class="text-content-label text-text-muted">{{ trans_choice('{1}:count Wort|[2,*]:count Wörter', $detail['word_count'], ['count' => $detail['word_count']]) }}</span>
            @if ($detail['attempt'] > 0)
                <span class="text-content-label text-text-muted">{{ __('Versuch :n', ['n' => $detail['attempt'] + 1]) }}</span>
            @endif
            @if ($detail['scheduled_for'])
                <span class="text-content-label text-text-muted">{{ __('geplant für :time', ['time' => $detail['scheduled_for']]) }}</span>
            @endif
        </div>

        {{-- Herkunftszeile (#99): Titel und Slug einer Aktualisierung sind
             mit der Elternfassung identisch. Der Link fuehrt auf die
             Gegenueberstellung weiter unten auf dieser Seite. --}}
        @if ($detail['origin'] ?? null)
            <p class="mt-content-3 text-content-label text-text-base">
                {{ __('Aktualisierung von') }}
                @if ($detail['diff'])
                    <a href="#fassungsvergleich" class="font-medium text-content-700 underline underline-offset-4">{{ $detail['origin']['title'] }}</a>
                @else
                    <span class="font-medium">{{ $detail['origin']['title'] }}</span>
                @endif
                @if ($detail['origin']['published_at'])
                    <span class="text-text-muted">{{ __('(veröffentlicht am :date)', ['date' => $detail['origin']['published_at']]) }}</span>
                @endif
            </p>
        @endif

        <h2 class="mt-content-2 text-content-h2 font-semibold text-text-strong">{{ $detail['title'] }}</h2>

        <dl class="mt-content-3 space-y-content-1 text-content-label text-text-muted">
            <div><dt class="inline">{{ __('Meta-Titel') }}:</dt> <dd class="inline text-text-base">{{ $detail['meta_title'] ?: '—' }}</dd></div>
            <div><dt class="inline">{{ __('Meta-Beschreibung') }}:</dt> <dd class="inline text-text-base">{{ $detail['meta_description'] ?: '—' }}</dd></div>
            <div><dt class="inline">{{ __('Adresse') }}:</dt> <dd class="inline text-text-base">/ratgeber/{{ $detail['slug'] }}</dd></div>
        </dl>
    </header>

    {{-- 1. Artikelvorschau --}}
    <section class="rounded-content-lg bg-surface-card shadow-content-card">
        <header class="flex items-center justify-between gap-content-2 border-b border-line-soft px-content-6 py-content-3">
            <h3 class="text-content-h3 font-semibold text-text-strong">{{ __('Artikelvorschau') }}</h3>
            @if ($detail['preview_url'])
                <a
                    href="{{ $detail['preview_url'] }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-content-table font-medium text-content-700 underline underline-offset-4"
                >{{ __('Ganze Seite ansehen') }}</a>
            @endif
        </header>

        @if ($detail['preview_url'])
            {{-- Der Rahmen zeigt die echte Portalseite, nicht eine Nachbildung
                 im Panel: ohne die echte Typografie beurteilt der Pruefer einen
                 anderen Text als der Leser. --}}
            <iframe
                src="{{ $detail['preview_url'] }}"
                title="{{ __('Vorschau: :title', ['title' => $detail['title']]) }}"
                loading="lazy"
                referrerpolicy="no-referrer"
                class="h-[640px] w-full rounded-b-content-lg border-0 bg-white"
            ></iframe>
        @else
            <p class="p-content-6 text-content-body text-text-base"
               style="background: var(--color-status-review-bg)">
                {{ __('Für dieses Portal ist keine Domain hinterlegt — die Vorschau lässt sich nicht erzeugen.') }}
            </p>
        @endif
    </section>

    {{-- 1b. Assets (#16/#71): Titelbild, Herkunft, Alt-Text, Infografik --}}
    @php
        $assets = $detail['assets'];
    @endphp
    <section class="rounded-content-lg bg-surface-card p-content-6 shadow-content-card">
        <header class="flex flex-wrap items-center gap-content-3">
            <h3 class="text-content-h3 font-semibold text-text-strong">{{ __('Bilder') }}</h3>
            <span class="text-content-label text-text-muted">{{ __('Herkunft: :source', ['source' => $assets['source_label']]) }}</span>
            @if ($assets['is_fallback'])
                {{-- Ein Standardbild ist kein Fehler, aber es sieht auf jedem
                     Artikel gleich aus — die Redaktion muss es erkennen,
                     bevor sie freigibt. --}}
                <span
                    class="rounded-content-sm px-content-2 py-content-1 text-content-label font-semibold"
                    style="background: var(--color-status-failed-bg); color: var(--color-status-failed-fg)"
                >{{ $assets['hero'] ? __('Standardbild') : __('Kein Titelbild') }}</span>
            @endif
        </header>

        <div class="mt-content-4 grid gap-content-4 md:grid-cols-2">
            <div>
                @if ($assets['hero'])
                    <img
                        src="{{ $assets['hero']['url'] }}"
                        alt="{{ $assets['alt'] ?: __('Titelbild ohne Alt-Text') }}"
                        width="{{ $assets['hero']['width'] }}"
                        height="{{ $assets['hero']['height'] }}"
                        loading="lazy"
                        class="w-full rounded-content-lg bg-surface-sunken"
                    >
                @else
                    <p class="rounded-content-lg bg-surface-sunken p-content-4 text-content-body text-text-base">
                        {{ __('Für diesen Entwurf wurde kein Titelbild erzeugt.') }}
                    </p>
                @endif
            </div>

            <dl class="space-y-content-2 text-content-label text-text-muted">
                <div>
                    <dt>{{ __('Alt-Text') }}</dt>
                    <dd class="text-content-body text-text-base">{{ $assets['alt'] ?: '—' }}</dd>
                </div>
                <div>
                    <dt>{{ __('Bildnachweis') }}</dt>
                    <dd class="text-content-body text-text-base">{{ $assets['credit'] ?: '—' }}</dd>
                </div>
                <div>
                    <dt>{{ __('Infografik') }}</dt>
                    <dd class="text-content-body text-text-base">
                        @if ($assets['infographic'])
                            <a
                                href="{{ $assets['infographic']['url'] }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-content-700 underline underline-offset-4"
                            >{{ __('SVG ansehen') }}</a>
                        @else
                            {{ __('keine — der Artikel hat keine tabellarischen Fakten') }}
                        @endif
                    </dd>
                </div>
            </dl>
        </div>

        @if ($assets['errors'] !== [])
            <div
                class="mt-content-4 rounded-content-lg border-s-[3px] p-content-3"
                style="background: var(--color-status-failed-bg); border-color: var(--color-status-failed-dot)"
            >
                <p class="text-content-table font-semibold" style="color: var(--color-status-failed-fg)">
                    {{ trans_choice('{1}1 Meldung aus dem Bildlauf|[2,*]:count Meldungen aus dem Bildlauf', count($assets['errors']), ['count' => count($assets['errors'])]) }}
                </p>
                <ul class="mt-content-2 space-y-content-1 text-content-body text-text-base">
                    @foreach ($assets['errors'] as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>

    {{-- 2. Qualitaetsreport --}}
    <section class="rounded-content-lg bg-surface-card p-content-6 shadow-content-card">
        <header class="flex flex-wrap items-baseline gap-content-3">
            <h3 class="text-content-h3 font-semibold text-text-strong">{{ __('Qualitätsreport') }}</h3>
            @if ($quality['score'] !== null)
                <span class="text-content-metric font-semibold" style="color: var(--color-score-{{ $level }})">
                    {{ number_format($quality['score'], 0, ',', '.') }}
                </span>
                <span class="text-content-label text-text-muted">
                    {{ __('von 100, Freigabegrenze :threshold', ['threshold' => $quality['threshold']]) }}
                </span>
            @else
                <span class="text-content-label text-text-muted">{{ __('Noch nicht bewertet.') }}</span>
            @endif
            @if ($quality['checked_at'])
                <span class="content-asof ms-auto">{{ __('Stand: :time', ['time' => \Illuminate\Support\Carbon::parse($quality['checked_at'])->translatedFormat('d.m.Y H:i')]) }}</span>
            @endif
        </header>

        @php
            // Vorgeschichte, kein Zustand: der Korrekturlauf steht als Satz unter
            // Note und Schwelle, ohne Zaehler und ohne Statusfarbe (#74). Aeltere
            // Berichte ohne `quality` liefern null — dann entfaellt die Zeile.
            $fixRuns = $quality['fix_runs'];
            $gateApproved = $quality['decision'] === 'approved';
            $fixNote = $fixRuns === null || $quality['decision'] === null ? null : match (true) {
                $gateApproved && $fixRuns < 1 => __('Ohne automatische Korrektur freigegeben.'),
                $gateApproved => __('Nach einem automatischen Korrekturlauf freigegeben.'),
                $fixRuns < 1 => __('Kein automatischer Korrekturlauf möglich.'),
                default => __('Ein automatischer Korrekturlauf hat nicht gereicht.'),
            };
        @endphp

        @if ($fixNote !== null)
            <p class="mt-content-1 text-content-label" style="color: var(--color-text-base)">{{ $fixNote }}</p>
        @endif

        @if ($quality['blocking_issues'] !== [])
            {{-- Blockierende Befunde stehen oben: sie schlagen den Gesamtscore. --}}
            <div
                class="mt-content-4 rounded-content-lg border-s-[3px] p-content-3"
                style="background: var(--color-status-failed-bg); border-color: var(--color-status-failed-dot)"
            >
                <p class="text-content-table font-semibold" style="color: var(--color-status-failed-fg)">
                    {{ trans_choice('{1}1 blockierender Befund|[2,*]:count blockierende Befunde', count($quality['blocking_issues']), ['count' => count($quality['blocking_issues'])]) }}
                </p>
                <ul class="mt-content-2 space-y-content-1 text-content-body text-text-base">
                    @foreach ($quality['blocking_issues'] as $issue)
                        <li>
                            {{ $issue['text'] }}
                            @if ($issue['anchor'] && $detail['preview_url'])
                                <a
                                    href="{{ $detail['preview_url'] }}#{{ $issue['anchor'] }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="text-content-700 underline underline-offset-4"
                                >{{ __('Stelle ansehen') }}</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="mt-content-4 space-y-content-3">
            @forelse ($quality['criteria'] as $criterion)
                <div>
                    <div class="flex items-center justify-between text-content-table text-text-base">
                        <span class="font-medium">{{ $criterion['label'] }}</span>
                        <span class="font-semibold">
                            {{ number_format($criterion['score'], 0, ',', '.') }}
                            @if ($criterion['weight'] !== null)
                                <span class="text-content-label font-normal text-text-muted">
                                    {{ __('Gewicht :weight', ['weight' => number_format($criterion['weight'], 0, ',', '.')]) }}
                                </span>
                            @endif
                        </span>
                    </div>
                    <div class="mt-content-1 h-1.5 w-full rounded-content-sm bg-surface-sunken">
                        <div
                            class="h-1.5 rounded-content-sm"
                            style="width: {{ min(100, max(0, $criterion['score'])) }}%; background: var(--color-score-{{ $criterion['below'] ? 'poor' : 'good' }})"
                        ></div>
                    </div>
                    @if ($criterion['reason'])
                        <p class="mt-content-1 text-content-label text-text-muted">{{ $criterion['reason'] }}</p>
                    @endif
                </div>
            @empty
                <p class="text-content-body text-text-muted">
                    {{ __('Das Qualitätsgate hat für diesen Artikel keine Rubrikbewertung hinterlegt.') }}
                </p>
            @endforelse
        </div>

        @if ($quality['seo_lint'] !== [])
            <h4 class="mt-content-6 text-content-table font-semibold text-text-strong">{{ __('SEO-Prüfung') }}</h4>
            <ul class="mt-content-2 space-y-content-1 text-content-body">
                @foreach ($quality['seo_lint'] as $lint)
                    @php
                        $lintDot = match ($lint['status']) {
                            'ok' => 'var(--color-status-published-dot)',
                            'warn' => 'var(--color-status-review-dot)',
                            'skipped' => 'var(--color-line-strong)',
                            default => 'var(--color-status-failed-dot)',
                        };
                        $lintPrefix = match ($lint['status']) {
                            'ok' => __('Bestanden: '),
                            'warn' => __('Hinweis: '),
                            'skipped' => __('Nicht geprüft: '),
                            default => __('Fehler: '),
                        };
                    @endphp
                    <li class="flex items-start gap-content-2">
                        <span
                            class="mt-1.5 h-2 w-2 flex-none rounded-full"
                            style="background: {{ $lintDot }}"
                            aria-hidden="true"
                        ></span>
                        <span class="{{ $lint['status'] === 'skipped' ? 'text-text-muted' : 'text-text-base' }}">
                            <span class="sr-only">{{ $lintPrefix }}</span>
                            @if ($lint['blocking'] ?? false)
                                <span class="text-content-label font-bold" style="color: var(--color-status-failed-fg)">{{ __('Blockiert') }}</span>
                            @endif
                            {{ $lint['check'] }}@if ($lint['message']) — {{ $lint['message'] }}@endif
                            @if ($lint['status'] === 'skipped') ({{ __('nicht geprüft') }})@endif
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($quality['fix_instructions'] !== [])
            <h4 class="mt-content-6 text-content-table font-semibold text-text-strong">{{ __('Korrekturhinweise des Qualitätsgates') }}</h4>
            <p class="mt-content-1 text-content-label text-text-muted">
                {{ $fixRuns !== null && $fixRuns > 0
                    ? __('Diese Hinweise wurden in einem automatischen Korrekturlauf auf den Text angewandt.')
                    : __('Diese Hinweise sind nur ein Vorschlag: ein automatischer Korrekturlauf hat sie nicht angewandt.') }}
            </p>
            <ol class="mt-content-2 list-inside list-decimal space-y-content-1 text-content-body text-text-base">
                @foreach ($quality['fix_instructions'] as $instruction)
                    <li>{{ $instruction }}</li>
                @endforeach
            </ol>
        @endif
    </section>

    {{-- 3. Faktencheck --}}
    <section class="rounded-content-lg bg-surface-card p-content-6 shadow-content-card">
        <h3 class="text-content-h3 font-semibold text-text-strong">{{ __('Faktencheck') }}</h3>

        @if ($quality['fact_checks'] === [])
            <p class="mt-content-2 rounded-content-lg border-s-[3px] p-content-3 text-content-body text-text-base"
               style="background: var(--color-status-review-bg); border-color: var(--color-status-review-dot)">
                {{ __('Für diesen Artikel ist kein Faktencheck hinterlegt. Zahlen bitte selbst gegen die Quellen prüfen.') }}
            </p>
        @else
            <div class="mt-content-3 overflow-x-auto">
                <table class="w-full text-content-table">
                    <thead>
                        <tr class="border-b border-line-strong text-start text-content-label text-text-muted">
                            <th class="py-content-2 pe-content-3 text-start font-medium">{{ __('Aussage') }}</th>
                            <th class="py-content-2 pe-content-3 text-start font-medium">{{ __('Befund') }}</th>
                            <th class="py-content-2 pe-content-3 text-start font-medium">{{ __('Wert') }}</th>
                            <th class="py-content-2 text-start font-medium">{{ __('Quelle') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line-soft">
                        @foreach ($quality['fact_checks'] as $fact)
                            <tr>
                                <td class="py-content-2 pe-content-3 align-top text-text-base">{{ $fact['statement'] }}</td>
                                <td class="py-content-2 pe-content-3 align-top">
                                    <span
                                        class="text-content-label font-semibold"
                                        style="color: var(--color-status-{{ $fact['stale'] ? 'review' : 'published' }}-fg)"
                                    >{{ $fact['verdict'] ?? '—' }}</span>
                                </td>
                                <td class="py-content-2 pe-content-3 align-top text-text-muted">{{ $fact['note'] ?? '—' }}</td>
                                <td class="py-content-2 align-top">
                                    @if ($fact['url'])
                                        <a href="{{ $fact['url'] }}" target="_blank" rel="noopener noreferrer"
                                           class="text-content-700 underline underline-offset-4">{{ $fact['source'] ?: $fact['url'] }}</a>
                                    @else
                                        <span class="text-text-muted">{{ $fact['source'] ?: '—' }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- 4. Quellen --}}
    <section class="rounded-content-lg bg-surface-card p-content-6 shadow-content-card">
        <h3 class="text-content-h3 font-semibold text-text-strong">{{ __('Quellen') }}</h3>

        @if ($detail['sources'] === [])
            <p class="mt-content-2 rounded-content-lg border-s-[3px] p-content-3 text-content-body text-text-base"
               style="background: var(--color-status-review-bg); border-color: var(--color-status-review-dot)">
                {{ __('Dieser Artikel führt keine Quellen. Ohne Beleg ist er nicht freigabefähig.') }}
            </p>
        @else
            <div class="mt-content-3 overflow-x-auto">
                <table class="w-full text-content-table">
                    <thead>
                        <tr class="border-b border-line-strong text-content-label text-text-muted">
                            <th class="py-content-2 pe-content-3 text-start font-medium">{{ __('Quelle') }}</th>
                            <th class="py-content-2 pe-content-3 text-start font-medium">{{ __('Herausgeber') }}</th>
                            <th class="py-content-2 pe-content-3 text-start font-medium">{{ __('Datum') }}</th>
                            <th class="py-content-2 text-start font-medium">{{ __('Im Artikel') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line-soft">
                        @foreach ($detail['sources'] as $source)
                            <tr>
                                <td class="py-content-2 pe-content-3 align-top">
                                    @if ($source['url'])
                                        <a href="{{ $source['url'] }}" target="_blank" rel="noopener noreferrer"
                                           class="text-content-700 underline underline-offset-4">{{ $source['title'] }}</a>
                                    @else
                                        <span class="text-text-base">{{ $source['title'] }}</span>
                                    @endif
                                    @if ($source['snippet'])
                                        <p class="text-content-label text-text-muted">{{ $source['snippet'] }}</p>
                                    @endif
                                </td>
                                <td class="py-content-2 pe-content-3 align-top text-text-muted">{{ $source['publisher'] ?: '—' }}</td>
                                <td class="py-content-2 pe-content-3 align-top text-text-muted">
                                    {{ $source['published_at'] ?: '—' }}
                                    @if ($source['stale'])
                                        <span class="text-content-label" style="color: var(--color-status-review-fg)">{{ __('älter als 12 Monate') }}</span>
                                    @endif
                                </td>
                                <td class="py-content-2 align-top text-text-muted">
                                    {{ $source['is_cited'] ? __('zitiert') : __('nur recherchiert') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- 5. Vergleich bei einer Aktualisierung --}}
    @if ($detail['diff'])
        @php($diff = $detail['diff'])
        @php($renderer = \App\Content\Services\ArticleDiffRenderer::class)
        {{-- Sprungziel der Herkunftszeile oben (#99). --}}
        <section id="fassungsvergleich" class="rounded-content-lg bg-surface-card p-content-6 shadow-content-card">
            <div class="flex flex-wrap items-baseline justify-between gap-content-2">
                <h3 class="text-content-h3 font-semibold text-text-strong">{{ __('Vergleich mit der stehenden Fassung') }}</h3>
                @if ($diff['compared_to'] ?? null)
                    <span class="content-asof">{{ __('gegen die Fassung vom :date', ['date' => $diff['compared_to']]) }}</span>
                @endif
            </div>

            @if ($diff['identical'])
                <p class="mt-content-3 text-content-body text-text-muted">
                    {{ __('Der Text ist unverändert — eine Aktualisierung ohne Änderung bringt nichts.') }}
                </p>
            @else
                <p class="mt-content-1 text-content-label text-text-muted">
                    {{ __(':changed geänderte, :added neue, :removed entfallene Absätze', [
                        'changed' => $diff['changed'],
                        'added' => $diff['added'],
                        'removed' => $diff['removed'],
                    ]) }}
                </p>

                @if ($diff['added'] === 0 && $diff['changed'] === 0 && $diff['removed'] > 0)
                    <p class="mt-content-3 rounded-content-md px-content-3 py-content-2 text-content-label"
                       style="background: var(--color-status-review-bg); color: var(--color-status-review-fg)">
                        {{ __('Die neue Fassung ist kürzer und bringt keinen neuen Inhalt.') }}
                    </p>
                @endif

                <div class="mt-content-3">
                    <table class="content-diff-table w-full text-content-table">
                        <thead>
                            <tr class="border-b border-line-strong text-content-label text-text-muted">
                                <th class="w-1/2 py-content-2 pe-content-3 text-start font-medium">{{ __('Bisher') }}</th>
                                <th class="w-1/2 py-content-2 text-start font-medium">{{ __('Neu') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line-soft align-top">
                            @foreach ($diff['rows'] as $row)
                                @if ($row['type'] === $renderer::COLLAPSED)
                                    <tr>
                                        <td colspan="2" class="content-diff-collapsed">
                                            <span>{{ trans_choice('{1}1 unveränderter Absatz|[2,*]:count unveränderte Absätze', $row['count'], ['count' => $row['count']]) }}</span>
                                        </td>
                                    </tr>
                                @continue
                                @endif

                                <tr class="content-diff-row">
                                    <td
                                        class="content-diff-side text-text-base"
                                        data-side="{{ __('Bisher') }}"
                                        @if ($row['type'] === $renderer::REMOVED)
                                            style="background: var(--color-status-failed-bg)"
                                        @elseif ($row['type'] === $renderer::CHANGED)
                                            style="background: var(--color-surface-sunken)"
                                        @endif
                                    >
                                        @if ($row['before'] !== null)
                                            <div class="content-diff-cell">
                                                <span class="content-diff-gutter" aria-hidden="true">{{ $row['type'] === $renderer::REMOVED ? '−' : ($row['type'] === $renderer::CHANGED ? '~' : '') }}</span>
                                                <span class="content-diff-text">
                                                    @if ($row['type'] === $renderer::REMOVED)
                                                        <span class="sr-only">{{ __('Entfernt: ') }}</span>
                                                    @elseif ($row['type'] === $renderer::CHANGED)
                                                        <span class="sr-only">{{ __('Geändert: ') }}</span>
                                                    @endif
                                                    @if ($row['type'] === $renderer::CHANGED && $row['before_html'] !== null)
                                                        {!! $row['before_html'] !!}
                                                    @else
                                                        {{ $row['before'] }}
                                                    @endif
                                                </span>
                                            </div>
                                        @endif
                                    </td>
                                    <td
                                        class="content-diff-side text-text-base"
                                        data-side="{{ __('Neu') }}"
                                        @if ($row['type'] === $renderer::ADDED)
                                            style="background: var(--color-status-published-bg)"
                                        @elseif ($row['type'] === $renderer::CHANGED)
                                            style="background: var(--color-surface-sunken)"
                                        @endif
                                    >
                                        @if ($row['after'] !== null)
                                            <div class="content-diff-cell">
                                                <span class="content-diff-gutter" aria-hidden="true">{{ $row['type'] === $renderer::ADDED ? '+' : ($row['type'] === $renderer::CHANGED ? '~' : '') }}</span>
                                                <span class="content-diff-text">
                                                    @if ($row['type'] === $renderer::ADDED)
                                                        <span class="sr-only">{{ __('Neu: ') }}</span>
                                                    @elseif ($row['type'] === $renderer::CHANGED)
                                                        <span class="sr-only">{{ __('Geändert: ') }}</span>
                                                    @endif
                                                    @if ($row['type'] === $renderer::CHANGED && $row['after_html'] !== null)
                                                        {!! $row['after_html'] !!}
                                                    @else
                                                        {{ $row['after'] }}
                                                    @endif
                                                </span>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif

    {{-- Entscheidungsleiste --}}
    <div
        class="sticky bottom-0 z-10 flex flex-wrap items-center gap-content-3 border-t border-line-strong bg-surface-card px-content-4 py-content-3"
        x-on:keydown.window="
            if ($event.target.matches('input, textarea, select, [contenteditable]')) return;
            if ($event.key === 'f') { $event.preventDefault(); $wire.mountAction('approve') }
            @unless ($budgetBlocks)
            if ($event.key === 'n') { $event.preventDefault(); $wire.mountAction('regenerate') }
            @endunless
            if ($event.key === 'v') { $event.preventDefault(); $wire.mountAction('discard') }
        "
    >
        <button
            type="button"
            wire:click="mountAction('approve')"
            class="h-11 rounded-content-md bg-content-600 px-content-4 text-content-table font-medium text-white"
        >{{ __('Freigeben') }}</button>

        {{-- Ohne Budget bleibt der Ausloeser sichtbar und fokussierbar; die
             Begruendung steht als Zeile darunter (#42, §6). --}}
        <button
            type="button"
            @if ($budgetBlocks)
                aria-disabled="true"
                aria-describedby="generate-budget-note"
            @else
                wire:click="mountAction('regenerate')"
            @endif
            @class([
                'h-11 rounded-content-md border border-content-600 px-content-4 text-content-table font-medium text-content-700',
                'opacity-50 cursor-not-allowed' => $budgetBlocks,
            ])
        >{{ __('Mit Hinweis neu generieren') }}</button>

        <button
            type="button"
            wire:click="mountAction('discard')"
            class="h-11 rounded-content-md px-content-4 text-content-table font-medium"
            style="color: var(--color-status-failed-fg)"
        >{{ __('Verwerfen') }}</button>

        <p class="ms-auto text-content-label text-text-muted">
            {{ __('Tastatur: J/K blättern · F freigeben · N neu erzeugen · V verwerfen') }}
        </p>

        @if ($budgetBlocks)
            <p id="generate-budget-note" class="mt-content-2 w-full text-content-label" style="color: var(--color-status-failed-fg)">
                {{ $budget['reason'] }}
            </p>
        @endif
    </div>
</div>
