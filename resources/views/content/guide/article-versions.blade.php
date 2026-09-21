{{--
    Verlauf › Versionen (#16, design/guide-dashboard.md §5.5, §7.2, §8.3).
    Ohne Thema: veröffentlichte Fassungen der letzten Tage. Mit Thema:
    Historie, Vergleich zweier Fassungen (abschnittsweise) und Rollback.
--}}
@php
    $tz = config('guide.timezone');
    $history = $this->history();
    $date = fn (?string $value): string => $value !== null ? \Illuminate\Support\Carbon::parse($value)->timezone($tz)->format('d.m.Y, H:i') : '–';
@endphp
<x-filament-panels::page>
    @if ($history === null)
        @php($recent = $this->recent())
        @if ($recent === [])
            <div class="rounded-content-lg bg-surface-card p-content-6 text-content-body text-text-base shadow-content-card">
                {{ __('In diesem Zeitraum wurde keine Fassung veröffentlicht.') }}
            </div>
        @else
            <section class="rounded-content-lg bg-surface-card shadow-content-card">
                <ul class="divide-y divide-line-soft">
                    @foreach ($recent as $row)
                        <li class="flex flex-col gap-content-1 px-content-6 py-content-3 sm:flex-row sm:items-start sm:justify-between sm:gap-content-4">
                            <div class="min-w-0">
                                <a href="{{ \App\Guide\Filament\Pages\ArticleVersions::topicUrl($row['topic_key']) }}" class="font-medium text-text-strong hover:underline">{{ $row['question'] }}</a>
                                <p class="text-content-table text-text-base">{{ $row['summary'] ?: ($row['version'] === 1 ? __('Erste Fassung.') : __('Ohne Changelog-Satz.')) }}</p>
                            </div>
                            <p class="shrink-0 text-content-table text-text-muted">
                                {{ collect([
                                    app(\App\Guide\Services\TopicDirectory::class)->isNetworkWide() ? $row['tenant_name'] : null,
                                    __('Fassung :v', ['v' => $row['version']]),
                                    $row['is_rollback'] ? __('Rollback') : null,
                                    $date($row['published_at']),
                                ])->filter()->implode(' · ') }}
                            </p>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    @else
        <div class="flex flex-wrap gap-x-content-6 gap-y-content-2 text-content-table">
            @if ($url = $this->topicDetailUrl())
                <a href="{{ $url }}" class="font-medium text-content-700 underline underline-offset-2">{{ __('Zum Thema') }}</a>
            @endif
            @if ($history['article_url'])
                <a href="{{ $history['article_url'] }}" target="_blank" rel="noopener" class="font-medium text-content-700 underline underline-offset-2">{{ __('Artikel öffnen') }}</a>
            @endif
            <a href="{{ \App\Guide\Filament\Pages\ArticleVersions::getUrl() }}" class="font-medium text-content-700 underline underline-offset-2">{{ __('Alle Versionen') }}</a>
        </div>

        <section class="rounded-content-lg bg-surface-card shadow-content-card">
            <table class="w-full text-content-table">
                <thead>
                    <tr class="border-b border-line-soft text-content-label text-text-muted">
                        <th class="px-content-6 py-content-2 text-start font-medium">{{ __('Fassung') }}</th>
                        <th class="py-content-2 text-start font-medium">{{ __('Changelog') }}</th>
                        <th class="hidden py-content-2 text-start font-medium md:table-cell">{{ __('Veröffentlicht') }}</th>
                        <th class="px-content-6 py-content-2 text-end font-medium">{{ __('Aktionen') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line-soft align-top text-text-base">
                    @foreach ($history['versions'] as $version)
                        <tr wire:key="version-{{ $version['id'] }}">
                            <td class="px-content-6 py-content-3">
                                <span class="font-medium tabular-nums text-text-strong">{{ $version['version'] }}</span>
                                @if ($version['is_current'])
                                    <span class="content-status content-status--published ms-content-2">{{ __('online') }}</span>
                                @elseif ($version['published_at'] === null)
                                    <span class="content-status content-status--idea ms-content-2">{{ __('nicht veröffentlicht') }}</span>
                                @endif
                            </td>
                            <td class="py-content-3 pe-content-3">
                                {{ $version['summary'] ?: '–' }}
                                @if ($version['is_rollback'])
                                    <span class="block text-content-label text-text-muted">{{ __('Rollback') }}</span>
                                @endif
                            </td>
                            <td class="hidden py-content-3 md:table-cell">{{ $date($version['published_at'] ?? $version['created_at']) }}</td>
                            <td class="px-content-6 py-content-3 text-end whitespace-nowrap">
                                {{ ($this->previewAction)(['version' => $version['id']]) }}
                                @if ($version['can_rollback'])
                                    {{ ($this->rollbackAction)(['version' => $version['id']]) }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        @if ($comparison = $this->comparison())
            <section class="flex flex-col gap-content-4">
                <div class="flex flex-wrap items-end gap-content-3">
                    <h2 class="text-content-h2 font-semibold text-text-strong">{{ __('Vergleich') }}</h2>
                    <label class="text-content-table text-text-base">
                        <span class="sr-only">{{ __('Ältere Fassung') }}</span>
                        <select wire:model.live="from" class="rounded-content-md border-line-strong text-content-table">
                            @foreach ($history['versions'] as $version)
                                <option value="{{ $version['id'] }}" @selected($version['id'] === $comparison['from'])>{{ __('Fassung :v', ['v' => $version['version']]) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <span aria-hidden="true">↔</span>
                    <label class="text-content-table text-text-base">
                        <span class="sr-only">{{ __('Neuere Fassung') }}</span>
                        <select wire:model.live="to" class="rounded-content-md border-line-strong text-content-table">
                            @foreach ($history['versions'] as $version)
                                <option value="{{ $version['id'] }}" @selected($version['id'] === $comparison['to'])>{{ __('Fassung :v', ['v' => $version['version']]) }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                @forelse ($comparison['rows'] as $row)
                    <article class="rounded-content-lg bg-surface-card p-content-4 shadow-content-card">
                        <h3 class="text-content-h3 font-semibold text-text-strong">{{ \App\Guide\Services\GuidePageData::replaceYear($row['heading']) }}</h3>
                        <div class="mt-content-3">
                            @include('content.guide.partials.section-diff', ['diff' => $row['diff']])
                        </div>
                    </article>
                @empty
                    <p class="text-content-body text-text-base">{{ __('Die beiden Fassungen unterscheiden sich im Text nicht.') }}</p>
                @endforelse
            </section>
        @endif
    @endif
</x-filament-panels::page>
