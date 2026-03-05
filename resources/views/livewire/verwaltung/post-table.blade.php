<div>
    {{-- Status Tabs --}}
    <div class="dash-tab-bar mb-4">
        @php
            $tabs = [
                '' => 'Alle',
                'published' => 'Veröffentlicht',
                'draft' => 'Entwürfe',
                'archived' => 'Archiviert',
            ];
        @endphp

        @foreach($tabs as $value => $label)
            <button wire:click="$set('filterStatus', '{{ $value }}')"
                    class="dash-tab {{ $filterStatus === $value ? 'dash-tab-active' : '' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Filter Bar --}}
    <div class="dash-card mb-4">
        <div class="dash-filter-bar">
            {{-- Search --}}
            <div class="dash-filter-search">
                <svg class="dash-filter-search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                </svg>
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="Titel oder Auszug suchen..."
                       class="dash-input"
                       style="padding-left: 2.5rem;"
                       aria-label="Artikel durchsuchen">
            </div>

            {{-- Category Filter --}}
            <div class="dash-filter-actions">
                <select wire:model.live="filterCategory"
                        class="dash-select dash-btn-sm"
                        style="width: auto; min-height: auto; padding: 0.5rem 2rem 0.5rem 0.75rem;"
                        aria-label="Kategorie filtern">
                    <option value="">Alle Kategorien</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>

                @if($search || $filterStatus || $filterCategory)
                    <button wire:click="resetFilters"
                            class="dash-btn-icon"
                            title="Filter zurücksetzen">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                @endif
            </div>
        </div>

        {{-- Bulk Actions --}}
        @if(count($selected) > 0)
            <div class="dash-table-bulk">
                <span class="dash-table-bulk-count">{{ count($selected) }} ausgewählt</span>
                <button wire:click="bulkDelete"
                        wire:confirm="Ausgewählte Artikel unwiderruflich löschen?"
                        class="dash-btn dash-btn-sm dash-btn-secondary"
                        style="min-height: auto; background-color: var(--dash-danger-light); color: var(--dash-danger); border-color: var(--dash-danger-border);">
                    Löschen
                </button>
            </div>
        @endif
    </div>

    {{-- Post Cards --}}
    <div class="space-y-3">
        @forelse($posts as $post)
            <div class="dash-card dash-card-padded" wire:key="post-{{ $post->id }}">
                <div class="flex items-start gap-3">
                    {{-- Checkbox --}}
                    <input type="checkbox"
                           wire:model.live="selected"
                           value="{{ $post->id }}"
                           class="mt-1 shrink-0"
                           style="accent-color: var(--portal-primary, #3b82f6);"
                           aria-label="Artikel auswählen">

                    {{-- Content --}}
                    <div class="flex-1 min-w-0">
                        {{-- Header: Title + Status --}}
                        <div class="flex flex-wrap items-center gap-2 mb-1.5">
                            <h3 class="text-sm font-semibold" style="color: var(--dash-text-primary);">
                                <a href="{{ route('verwaltung.blog.edit', $post->id) }}"
                                   class="hover:underline"
                                   style="color: inherit;">
                                    {{ $post->title }}
                                </a>
                            </h3>

                            {{-- Status Badge --}}
                            @if($post->status === 'published')
                                <span class="dash-badge dash-badge-success">Veröffentlicht</span>
                            @elseif($post->status === 'draft')
                                <span class="dash-badge dash-badge-warning">Entwurf</span>
                            @else
                                <span class="dash-badge dash-badge-neutral">Archiviert</span>
                            @endif

                            {{-- Category Badge --}}
                            @if($post->category)
                                <span class="dash-badge dash-badge-info">{{ $post->category->name }}</span>
                            @endif

                            {{-- Tags Count --}}
                            @if($post->tags_count > 0)
                                <span class="dash-badge">{{ $post->tags_count }} {{ $post->tags_count === 1 ? 'Tag' : 'Tags' }}</span>
                            @endif
                        </div>

                        {{-- Excerpt --}}
                        @if($post->excerpt)
                            <p class="text-sm mb-2 line-clamp-2" style="color: var(--dash-text-secondary);">
                                {{ $post->excerpt }}
                            </p>
                        @endif

                        {{-- Meta --}}
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs" style="color: var(--dash-text-muted);">
                            {{-- Reading Time --}}
                            @if($post->reading_time_minutes)
                                <span class="inline-flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    {{ $post->reading_time_minutes }} Min. Lesezeit
                                </span>
                            @endif

                            {{-- Views --}}
                            <span class="inline-flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                {{ number_format($post->view_count) }} Aufrufe
                            </span>

                            {{-- Created --}}
                            <span class="inline-flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/>
                                </svg>
                                {{ $post->created_at->format('d.m.Y') }}
                            </span>

                            {{-- Published --}}
                            @if($post->published_at)
                                <span class="inline-flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 7.5h1.5m-1.5 3h1.5m-7.5 3h7.5m-7.5 3h7.5m3-9h3.375c.621 0 1.125.504 1.125 1.125V18a2.25 2.25 0 01-2.25 2.25M16.5 7.5V18a2.25 2.25 0 002.25 2.25M16.5 7.5V4.875c0-.621-.504-1.125-1.125-1.125H4.125C3.504 3.75 3 4.254 3 4.875V18a2.25 2.25 0 002.25 2.25h13.5"/>
                                    </svg>
                                    Veröff.: {{ $post->published_at->format('d.m.Y H:i') }}
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Quick Actions --}}
                    <div class="flex items-center gap-1 shrink-0">
                        {{-- Toggle Status --}}
                        <button wire:click="toggleStatus({{ $post->id }})"
                                class="dash-btn-icon"
                                title="{{ $post->status === 'published' ? 'Zurückziehen' : 'Veröffentlichen' }}">
                            @if($post->status === 'published')
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5" style="color: var(--dash-warning, #d97706);">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/>
                                </svg>
                            @else
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5" style="color: var(--dash-success);">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                            @endif
                        </button>

                        {{-- Edit --}}
                        <a href="{{ route('verwaltung.blog.edit', $post->id) }}"
                           class="dash-btn-icon"
                           title="Bearbeiten">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/>
                            </svg>
                        </a>

                        {{-- Delete --}}
                        <button wire:click="deletePost({{ $post->id }})"
                                wire:confirm="Artikel &quot;{{ $post->title }}&quot; unwiderruflich löschen?"
                                class="dash-btn-icon dash-btn-danger"
                                title="Löschen">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <div class="dash-card">
                <div class="dash-empty">
                    <svg class="dash-empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/>
                    </svg>
                    @if($search || $filterStatus || $filterCategory)
                        <p class="dash-empty-title">Keine Artikel gefunden</p>
                        <p class="dash-empty-description">Versuchen Sie es mit anderen Suchbegriffen oder Filtern.</p>
                        <button wire:click="resetFilters" class="dash-btn dash-btn-sm dash-btn-primary">
                            Filter zurücksetzen
                        </button>
                    @else
                        <p class="dash-empty-title">Noch keine Artikel vorhanden</p>
                        <p class="dash-empty-description">Erstellen Sie Ihren ersten Ratgeber-Artikel, um Besucher mit nützlichem Content anzuziehen.</p>
                        <a href="{{ route('verwaltung.blog.create') }}" class="dash-btn dash-btn-sm dash-btn-primary">
                            Ersten Artikel erstellen
                        </a>
                    @endif
                </div>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if($posts->hasPages())
        <div class="dash-pagination mt-4">
            {{ $posts->links() }}
        </div>
    @endif

    {{-- Result count --}}
    <div class="dash-result-count mt-2" style="border-top: none;">
        {{ $posts->total() }} {{ $posts->total() === 1 ? 'Artikel' : 'Artikel' }} gefunden
    </div>
</div>
