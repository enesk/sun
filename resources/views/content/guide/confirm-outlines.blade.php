{{--
    Gliederung bestätigen (#15, design/guide-dashboard.md §5.4 Sammelfreigabe).
    Gruppiert nach Kategorie; jede Gliederung als <details> aufklappbar
    (Enter/Leertaste), Kontrollkästchen je Thema und je Kategorie.
--}}
@php
    $groups = $this->groups();
    $waiting = $this->waitingCount();
@endphp
<x-filament-panels::page>
    @if ($groups === [])
        <div class="flex items-center gap-3 rounded-xl border border-line-strong bg-surface-card p-6 text-sm text-text-base">
            <x-filament::icon icon="heroicon-o-check-circle" class="h-6 w-6 text-status-published-dot" />
            {{ __('Keine Gliederung wartet auf Bestätigung.') }}
        </div>
    @else
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-text-base">
                {{ trans_choice('{1} Ein Vorschlag wartet auf Ihre Freigabe.|[2,*] :count Vorschläge warten auf Ihre Freigabe.', count($this->proposalKeys()), ['count' => count($this->proposalKeys())]) }}
                @if ($waiting > 0)
                    {{ trans_choice('{1} Ein weiteres Thema hat noch keinen Vorschlag; es bekommt ihn im nächsten Lauf.|[2,*] :count weitere Themen haben noch keinen Vorschlag; sie bekommen ihn im nächsten Lauf.', $waiting, ['count' => $waiting]) }}
                @endif
            </p>
            <div class="flex flex-wrap gap-3">
                {{ $this->lockSelectedAction }}
                {{ $this->confirmAllAction }}
            </div>
        </div>

        @foreach ($groups as $slug => $group)
            @php
                $lockable = collect($group['topics'])->filter(fn (array $topic): bool => $topic['outline'] !== [])->pluck('key')->all();
                $allSelected = $lockable !== [] && array_diff($lockable, $selected) === [];
            @endphp
            <section class="rounded-xl border border-line-strong bg-surface-card" wire:key="group-{{ $slug }}">
                <header class="flex items-center justify-between gap-3 border-b border-line-soft px-4 py-3">
                    <h2 class="text-content-h3 font-semibold text-text-strong">{{ $group['name'] }}</h2>
                    @if ($lockable !== [])
                        <label class="flex items-center gap-2 text-sm text-text-base">
                            <x-filament::input.checkbox :checked="$allSelected" wire:click="toggleCategory(@js($slug))" />
                            {{ trans_choice('{1} das eine Thema|[2,*] alle :count in :name', count($lockable), ['count' => count($lockable), 'name' => $group['name']]) }}
                        </label>
                    @endif
                </header>

                <ul class="divide-y divide-line-soft">
                    @foreach ($group['topics'] as $topic)
                        <li class="flex items-start gap-3 px-4 py-3" wire:key="topic-{{ $topic['key'] }}">
                            <div class="pt-1">
                                @if ($topic['outline'] !== [])
                                    <x-filament::input.checkbox wire:model.live="selected" value="{{ $topic['key'] }}" :aria-label="__('Auswählen: :question', ['question' => $topic['question']])" />
                                @endif
                            </div>
                            <details class="min-w-0 flex-1">
                                <summary class="cursor-pointer text-sm">
                                    <span class="font-medium text-text-strong">{{ $topic['question'] }}</span>
                                    <span class="block text-xs text-text-muted">
                                        {{ collect([
                                            $topic['show_portal'] ? $topic['tenant_name'] : null,
                                            $topic['outline'] !== []
                                                ? trans_choice('{1} eine Überschrift|[2,*] :count Überschriften', count($topic['outline']), ['count' => count($topic['outline'])])
                                                : __('noch kein Vorschlag'),
                                            $topic['outline'] !== [] && $topic['proposed_at'] ? __('Vorschlag vom :date', ['date' => $topic['proposed_at']]) : null,
                                        ])->filter()->implode(' · ') }}
                                    </span>
                                </summary>
                                @if ($topic['outline'] !== [])
                                    <ol class="mt-2 flex flex-col gap-1 text-sm">
                                        @foreach ($topic['outline'] as $entry)
                                            <li @class(['text-text-base', 'pl-6' => $entry['level'] === 3, 'font-semibold text-text-strong' => $entry['level'] === 2])>
                                                {{ $entry['text'] }}
                                            </li>
                                        @endforeach
                                    </ol>
                                @endif
                                <a href="{{ \App\Guide\Filament\Resources\TopicResource::detailUrl($topic['key']) }}" class="mt-2 inline-block text-sm font-medium text-content-700 underline underline-offset-2">
                                    {{ __('Im Gliederungs-Editor anpassen') }}
                                </a>
                            </details>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    @endif
</x-filament-panels::page>
