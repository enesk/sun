{{--
    Fassungsvergleich Bisher | Neu (design/content-dashboard.md §4.1/§4.1a),
    Bauform wie im Prüfblatt der alten Pipeline. Nur Wortmarken tragen Farbe.
    Erwartet: $diff — Ergebnis von App\Guide\Services\ArticleDiffRenderer::diff().
--}}
@php($renderer = \App\Guide\Services\ArticleDiffRenderer::class)
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
