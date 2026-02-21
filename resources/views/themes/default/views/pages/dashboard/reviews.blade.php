@extends('layouts.dashboard')

@section('title', 'Bewertungen')

@section('content')
    <div class="dash-page-header">
        <div>
            <h1 class="dash-page-title">Bewertungen</h1>
            <p class="dash-page-subtitle">Alle Bewertungen für {{ $company->name }}.</p>
        </div>
    </div>

    {{-- Rating Summary --}}
    @if($company->rating_count > 0)
        <div class="dash-card dash-card-padded mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                <div class="text-center sm:text-left">
                    <div class="text-4xl font-bold" style="color: var(--dash-text-primary)">{{ number_format($company->rating, 1) }}</div>
                    <div class="flex items-center justify-center sm:justify-start gap-0.5 mt-1" aria-label="{{ $company->rating }} von 5 Sternen">
                        @for($i = 1; $i <= 5; $i++)
                            <svg class="w-5 h-5 {{ $i <= round($company->rating) ? 'text-portal-accent' : '' }}" style="{{ $i > round($company->rating) ? 'color: var(--dash-text-muted)' : '' }}" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                            </svg>
                        @endfor
                    </div>
                    <p class="text-sm mt-1" style="color: var(--dash-text-muted)">{{ $company->rating_count }} {{ $company->rating_count === 1 ? 'Bewertung' : 'Bewertungen' }}</p>
                </div>

                {{-- Rating Distribution --}}
                <div class="flex-1 space-y-1.5">
                    @for($stars = 5; $stars >= 1; $stars--)
                        @php
                            $count = $reviews->where('rating', $stars)->count();
                            $percentage = $company->rating_count > 0 ? ($count / $company->rating_count) * 100 : 0;
                        @endphp
                        <div class="dash-rating-bar">
                            <span class="w-3 text-right" style="color: var(--dash-text-muted)">{{ $stars }}</span>
                            <svg class="w-3.5 h-3.5 text-portal-accent shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                            </svg>
                            <div class="dash-rating-bar-track">
                                <div class="dash-rating-bar-fill" style="width: {{ $percentage }}%"></div>
                            </div>
                            <span class="w-6 text-right text-xs" style="color: var(--dash-text-muted)">{{ $count }}</span>
                        </div>
                    @endfor
                </div>
            </div>
        </div>
    @endif

    {{-- Reviews List --}}
    @if($reviews->isEmpty())
        <div class="dash-card">
            <div class="dash-empty" style="padding: 3rem 1.5rem;">
                <svg class="w-16 h-16 mx-auto mb-4" style="color: var(--dash-text-muted); opacity: 0.3;" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                </svg>
                <h3 class="dash-empty-title" style="font-size: 1.125rem;">Noch keine Bewertungen</h3>
                <p class="dash-empty-description" style="max-width: 24rem;">
                    Sobald Kunden Bewertungen abgeben, erscheinen sie hier. Teilen Sie Ihre Firmenseite, um Bewertungen zu erhalten.
                </p>
                <a href="{{ route('portal.companies.show', $company->url_slug) }}" target="_blank"
                   class="dash-btn dash-btn-sm dash-btn-secondary inline-flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                    </svg>
                    Firmenseite ansehen
                </a>
            </div>
        </div>
    @else
        <div class="space-y-4">
            @foreach($reviews as $review)
                <div class="dash-review-card {{ $review->isPending() ? 'dash-review-card-pending' : ($review->isRejected() ? 'dash-review-card-rejected' : '') }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-medium text-sm" style="color: var(--dash-text-primary)">{{ $review->author_name ?: 'Anonym' }}</span>

                                @if($review->isPending())
                                    <span class="dash-badge dash-badge-warning">Wartend</span>
                                @elseif($review->isRejected())
                                    <span class="dash-badge dash-badge-danger">Abgelehnt</span>
                                @elseif($review->isApproved())
                                    <span class="dash-badge dash-badge-success">Veröffentlicht</span>
                                @endif
                            </div>

                            {{-- Stars --}}
                            <div class="flex items-center gap-0.5 mt-1" aria-label="{{ $review->rating }} von 5 Sternen">
                                @for($i = 1; $i <= 5; $i++)
                                    <svg class="w-4 h-4 {{ $i <= $review->rating ? 'text-portal-accent' : '' }}" style="{{ $i > $review->rating ? 'color: var(--dash-text-muted)' : '' }}" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                                    </svg>
                                @endfor
                            </div>

                            @if($review->title)
                                <h3 class="font-medium mt-2" style="color: var(--dash-text-primary)">{{ $review->title }}</h3>
                            @endif

                            @if($review->body)
                                <p class="text-sm mt-1" style="color: var(--dash-text-secondary)">{{ $review->body }}</p>
                            @endif

                            @if($review->isRejected() && $review->moderation_note)
                                <p class="text-xs mt-2 flex items-center gap-1" style="color: var(--dash-danger)">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                    </svg>
                                    Grund: {{ $review->moderation_note }}
                                </p>
                            @endif
                        </div>

                        <span class="text-xs shrink-0" style="color: var(--dash-text-muted)">{{ $review->created_at->format('d.m.Y') }}</span>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Pagination --}}
        <div class="dash-pagination mt-6">
            {{ $reviews->links() }}
        </div>
    @endif
@endsection
