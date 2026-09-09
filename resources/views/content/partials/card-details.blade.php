{{--
    Detailblatt einer Board-Karte (#19): Scores, Quellen und die erlaubten
    Handlungen. Blatt von rechts statt eigener Seite — der Board-Kontext
    bleibt erhalten (design/content-dashboard.md, §2).
--}}
@php
    use App\Content\Enums\DisplayStatus;
    use App\Content\Support\PipelineCardKey;

    $status = isset($card['status']) ? DisplayStatus::from($card['status']) : null;
    $isTopic = ($card['type'] ?? null) === PipelineCardKey::TYPE_TOPIC;
    $canPromote = $isTopic && $status === DisplayStatus::IDEA;
    // In Erzeugung ist ausdruecklich ausgeschlossen: ein zweiter Anstoss
    // wuerde vom ShouldBeUnique des Generators still verworfen (#42, §0.2).
    $canGenerate = $status !== DisplayStatus::GENERATING && ($isTopic
        ? $status !== DisplayStatus::ARCHIVED
        : in_array($status, [DisplayStatus::FAILED, DisplayStatus::REVIEW, DisplayStatus::PUBLISHED], true));

    $budget = $generationBudget ?? ['available' => true, 'reason' => null];
    $budgetBlocks = $canGenerate && ! $budget['available'];
    $noteId = 'generate-budget-note-'.($card['key'] ?? '');
    $canReject = $isTopic ? $status !== DisplayStatus::ARCHIVED : (bool) $status?->allowsWithdrawal();
@endphp

@if ($card === [])
    <p class="text-content-body text-text-base">
        {{ __('Diese Karte ist nicht mehr vorhanden. Bitte das Board neu laden.') }}
    </p>
@else
    <div class="space-y-content-6">
        {{-- Aktualisierungsstand (#99). Hier — und nicht in der Pruefung —
             sieht die Redaktion einen veroeffentlichten Ratgeber; nur hier
             sind Sperrfrist, Vormerkung und ein erfolgloser Lauf ueberhaupt
             sichtbar zu machen. --}}
        @php
            $refreshState = $card['refresh'] ?? null;
            $refreshChildKey = ($refreshState['child_id'] ?? null)
                ? \App\Content\Support\PipelineCardKey::draft((int) $card['tenant_id'], (int) $refreshState['child_id'])->toString()
                : null;
        @endphp

        {{-- Objektliteral von Hand statt Js::from(): der Ausdruck wandert als
             Zeichenkette durch die Blade-Ausgabe und wuerde dort schon
             maskierte Anfuehrungszeichen ein zweites Mal maskieren. --}}
        @include('content.partials.refresh-strip', [
            'refresh' => $refreshState,
            'childClick' => $refreshChildKey === null
                ? null
                : "mountAction('cardDetails', { card: '{$refreshChildKey}' })",
        ])

        @if ($card['origin'] ?? null)
            {{-- Herkunftszeile (#99): Titel und Slug sind mit der
                 Elternfassung identisch. Die Gegenueberstellung selbst steht
                 in der Pruefung (#20), nicht in diesem Blatt. --}}
            <p class="text-content-label text-text-base">
                {{ __('Aktualisierung von :title', ['title' => $card['origin']['title']]) }}
                @if ($card['origin']['published_at'])
                    <span class="text-text-muted">{{ __('(veröffentlicht am :date)', ['date' => $card['origin']['published_at']]) }}</span>
                @endif
            </p>
        @endif

        <div class="flex flex-wrap items-center gap-content-2">
            <span class="content-status content-status--{{ $card['status'] }}">{{ $card['status_label'] }}</span>
            <span class="text-content-label text-text-muted">{{ $card['tenant'] }}</span>
            <span class="text-content-label text-text-muted">{{ $card['region'] }}</span>
            @if ($card['time'])
                <span class="text-content-label text-text-muted">
                    {{ __('geplant für :time Uhr', ['time' => $card['time']]) }}
                </span>
            @endif
            @if ($status === DisplayStatus::GENERATING && ($card['running_since'] ?? null))
                <span class="text-content-label text-text-muted">
                    {{ __('läuft seit :time', ['time' => $card['running_since']]) }}
                </span>
            @endif
        </div>

        @if ($card['progress'])
            <p class="content-status-progress">{{ $card['progress'] }}</p>
        @endif

        @if ($card['keyword'] ?? null)
            <p class="text-content-body text-text-base">
                <span class="text-text-muted">{{ __('Hauptkeyword') }}:</span> {{ $card['keyword'] }}
            </p>
        @endif

        @if ($card['withdrawn_reason'] ?? null)
            <p class="rounded-content-lg border-s-[3px] p-content-3 text-content-body"
               style="background: var(--color-status-archived-bg); border-color: var(--color-status-archived-dot)">
                {{ $card['withdrawn_reason'] }}
            </p>
        @endif

        <section>
            <h3 class="text-content-h3 font-semibold text-text-strong">{{ __('Bewertung') }}</h3>

            @forelse ($card['scores'] ?? [] as $score)
                <div class="mt-content-2">
                    <div class="flex items-center justify-between text-content-table text-text-base">
                        <span>{{ $score['label'] }}</span>
                        <span class="font-semibold">{{ number_format((float) $score['value'], 1, ',', '.') }}</span>
                    </div>
                    <div class="mt-content-1 h-1.5 w-full rounded-content-sm bg-surface-sunken">
                        <div
                            class="h-1.5 rounded-content-sm"
                            style="width: {{ min(100, max(0, (float) $score['value'])) }}%; background: var(--color-score-{{ $card['score_level'] ?? 'mid' }})"
                        ></div>
                    </div>
                    @if ($score['note'] ?? null)
                        <p class="mt-content-1 text-content-label text-text-muted">{{ $score['note'] }}</p>
                    @endif
                </div>
            @empty
                <p class="mt-content-2 text-content-body text-text-muted">
                    {{ __('Noch keine Bewertung vorhanden.') }}
                </p>
            @endforelse
        </section>

        <section>
            <h3 class="text-content-h3 font-semibold text-text-strong">{{ __('Quellen') }}</h3>

            @forelse ($card['sources'] ?? [] as $source)
                <div class="mt-content-2 border-t border-line-soft pt-content-2 text-content-table">
                    <p class="font-medium text-text-strong">{{ $source['title'] }}</p>
                    <p class="text-content-label text-text-muted">
                        {{ $source['publisher'] ?? __('ohne Herausgeber') }}
                        @if ($source['published_at'])
                            · {{ $source['published_at'] }}
                        @endif
                        @if ($source['is_cited'])
                            · {{ __('im Artikel belegt') }}
                        @endif
                    </p>
                    @if ($source['url'])
                        <a href="{{ $source['url'] }}" target="_blank" rel="noopener"
                           class="text-content-label text-content-700 underline underline-offset-4">
                            {{ __('Quelle öffnen') }}
                        </a>
                    @endif
                </div>
            @empty
                <p class="mt-content-2 text-content-body text-text-muted">
                    {{ __('Für dieses Objekt sind noch keine Quellen erfasst.') }}
                </p>
            @endforelse
        </section>

        <div class="border-t border-line-strong pt-content-4">
            <div class="flex flex-wrap gap-content-2">
                @if ($canPromote)
                    <button
                        type="button"
                        wire:click="mountAction('promoteReserve', {{ \Illuminate\Support\Js::from(['card' => $card['key']]) }})"
                        class="h-9 rounded-content-md bg-content-600 px-content-4 text-content-table font-medium text-white"
                    >
                        {{ __('Reserve hochstufen') }}
                    </button>
                @endif

                @if ($canGenerate)
                    {{-- Ohne Budget bleibt der Ausloeser sichtbar und fokussierbar:
                         ein `disabled`-Knopf erreicht keine Vorleseanwendung und
                         damit auch nicht die Begruendung darunter (#42, §6). --}}
                    <button
                        type="button"
                        @if ($budgetBlocks)
                            aria-disabled="true"
                            aria-describedby="{{ $noteId }}"
                        @else
                            wire:click="mountAction('generateNow', {{ \Illuminate\Support\Js::from(['card' => $card['key']]) }})"
                        @endif
                        @class([
                            'h-9 rounded-content-md border border-content-600 px-content-4 text-content-table font-medium text-content-700',
                            'opacity-50 cursor-not-allowed' => $budgetBlocks,
                        ])
                    >
                        {{ __('Jetzt generieren') }}
                    </button>
                @endif

                @if ($canReject)
                    <button
                        type="button"
                        wire:click="mountAction('reject', {{ \Illuminate\Support\Js::from(['card' => $card['key']]) }})"
                        class="h-9 rounded-content-md px-content-4 text-content-table font-medium"
                        style="color: var(--color-status-failed-fg)"
                    >
                        {{ __('Ablehnen') }}
                    </button>
                @endif
            </div>

            @if ($budgetBlocks)
                <p id="{{ $noteId }}" class="mt-content-2 text-content-label" style="color: var(--color-status-failed-fg)">
                    {{ $budget['reason'] }}
                </p>
            @endif
        </div>
    </div>
@endif
