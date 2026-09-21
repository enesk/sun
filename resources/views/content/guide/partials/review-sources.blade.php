{{--
    Quellen zu einem Abschnitt im Prüfblatt (design/guide-dashboard.md §8.2
    Punkt 4, §5.7.3). Nicht erreichbare Quellen stehen oben, ihre URL nur als
    Text; Marke und Statuszeile aus content.guide.partials.unreachable-source.
    Im Lauf ersetzende Quellen tragen „neu in diesem Lauf“ und darunter die
    Zeilen aus UnreachableSources::replacedLines() (§5.7.5 A.2).
    Erwartet: $sources — Zeilen aus ReviewService::sourcesFor().
--}}
@if ($sources === [])
    <p class="mt-content-2 text-content-table text-text-muted">{{ __('Keine Quelle über die Fakten dieses Abschnitts zugeordnet.') }}</p>
@else
    <ul class="mt-content-2 flex flex-col gap-content-3">
        @foreach ($sources as $source)
            <li class="text-content-table text-text-base">
                <div class="flex flex-wrap items-center gap-content-2">
                    <span class="font-medium text-text-strong">{{ $source['publisher'] ?: parse_url($source['url'], PHP_URL_HOST) }}</span>
                    @if ($source['trust'])
                        <span class="text-content-label text-text-muted">{{ $source['trust'] }}</span>
                    @endif
                    @if ($source['is_new'] && ! $source['unreachable'])
                        <span class="content-status content-status--scheduled">{{ __('neu in diesem Lauf') }}</span>
                    @endif
                </div>
                @if ($source['unreachable'])
                    <span class="block">{{ $source['title'] }}</span>
                    <span class="block break-all text-text-muted">{{ $source['url'] }}</span>
                    <div class="mt-content-1">
                        @include('content.guide.partials.unreachable-source', ['code' => $source['code'], 'checkedAt' => $source['checked_at']])
                    </div>
                @else
                    <a href="{{ $source['url'] }}" target="_blank" rel="noopener noreferrer" class="block break-all text-content-700 underline underline-offset-2">{{ $source['title'] ?: $source['url'] }}</a>
                    @foreach (\App\Guide\Support\UnreachableSources::replacedLines($source['replaces'] ?? []) as $line)
                        <span class="block break-words text-[13px] text-text-muted">{{ $line }}</span>
                    @endforeach
                @endif
                @if ($source['published_at'])
                    <span class="block text-[13px] text-text-muted">{{ __('Stand :date', ['date' => $source['published_at']]) }}</span>
                @endif
                @foreach ($source['facts'] as $fact)
                    <blockquote class="mt-content-1 border-s-2 border-line-strong ps-content-2 text-[13px] text-text-base">{{ $fact }}</blockquote>
                @endforeach
            </li>
        @endforeach
    </ul>
@endif
