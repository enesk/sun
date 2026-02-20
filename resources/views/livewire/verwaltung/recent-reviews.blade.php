<div>
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-base font-semibold text-base-content">
            Neueste Bewertungen
            @if($pendingCount > 0)
                <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">
                    {{ $pendingCount }} wartend
                </span>
            @endif
        </h2>
        <a href="{{ route('verwaltung.reviews.index') }}" class="text-xs font-medium hover:underline" style="color: var(--portal-primary, #3b82f6);">
            Alle ansehen
        </a>
    </div>

    @if($reviews->isEmpty())
        <div class="text-center py-8">
            <svg class="w-10 h-10 mx-auto text-base-content/15 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.563.563 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/>
            </svg>
            <p class="text-sm text-base-content/40">Noch keine Bewertungen</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach($reviews as $review)
                <div class="flex items-start gap-3 p-3 rounded-lg border border-base-200 {{ $review->moderation_status === 'pending' ? 'bg-yellow-50/50' : 'bg-base-100' }}"
                     wire:key="review-{{ $review->id }}">
                    {{-- Stars --}}
                    <div class="flex items-center gap-0.5 shrink-0 pt-0.5">
                        @for($i = 1; $i <= 5; $i++)
                            <svg class="w-3.5 h-3.5 {{ $i <= $review->rating ? '' : 'text-base-content/15' }}"
                                 @if($i <= $review->rating) style="color: var(--portal-accent, #f59e0b);" @endif
                                 fill="currentColor" viewBox="0 0 24 24">
                                <path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                            </svg>
                        @endfor
                    </div>

                    {{-- Content --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-sm font-medium text-base-content truncate">
                                {{ $review->author_name ?: 'Anonym' }}
                            </span>
                            <span class="text-xs text-base-content/40 shrink-0">{{ $review->created_at->diffForHumans() }}</span>
                        </div>
                        @if($review->company)
                            <span class="text-xs text-base-content/50">{{ $review->company->name }}</span>
                        @endif
                        @if($review->body)
                            <p class="text-xs text-base-content/60 line-clamp-2 mt-1">{{ $review->body }}</p>
                        @endif

                        {{-- Moderation actions for pending reviews --}}
                        @if($review->moderation_status === 'pending')
                            <div class="flex items-center gap-2 mt-2">
                                <button wire:click="approve({{ $review->id }})"
                                        wire:loading.attr="disabled"
                                        class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-700 hover:bg-green-200 transition-colors">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                                    </svg>
                                    Freigeben
                                </button>
                                <button wire:click="reject({{ $review->id }})"
                                        wire:loading.attr="disabled"
                                        class="inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-medium bg-red-100 text-red-700 hover:bg-red-200 transition-colors">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                    Ablehnen
                                </button>
                            </div>
                        @else
                            <span class="inline-flex items-center mt-1 px-1.5 py-0.5 rounded text-xs font-medium
                                {{ $review->moderation_status === 'approved' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                {{ $review->moderation_status === 'approved' ? 'Freigegeben' : 'Abgelehnt' }}
                            </span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
