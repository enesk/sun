@extends('layouts.dashboard')

@section('title', 'Bewertungen')

@section('content')
    <div class="mb-6">
        <h1 class="text-xl sm:text-2xl font-bold text-base-content">Bewertungen</h1>
        <p class="text-sm text-base-content/60 mt-1">Alle Bewertungen für {{ $company->name }}.</p>
    </div>

    {{-- Rating Summary --}}
    @if($company->rating_count > 0)
        <div class="card-portal mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                <div class="text-center sm:text-left">
                    <div class="text-4xl font-bold text-base-content">{{ number_format($company->rating, 1) }}</div>
                    <div class="flex items-center justify-center sm:justify-start gap-0.5 mt-1" aria-label="{{ $company->rating }} von 5 Sternen">
                        @for($i = 1; $i <= 5; $i++)
                            <svg class="w-5 h-5 {{ $i <= round($company->rating) ? 'text-portal-accent' : 'text-base-content/20' }}" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                            </svg>
                        @endfor
                    </div>
                    <p class="text-sm text-base-content/50 mt-1">{{ $company->rating_count }} {{ $company->rating_count === 1 ? 'Bewertung' : 'Bewertungen' }}</p>
                </div>

                {{-- Rating Distribution --}}
                <div class="flex-1 space-y-1.5">
                    @for($stars = 5; $stars >= 1; $stars--)
                        @php
                            $count = $reviews->where('rating', $stars)->count();
                            $percentage = $company->rating_count > 0 ? ($count / $company->rating_count) * 100 : 0;
                        @endphp
                        <div class="flex items-center gap-2 text-sm">
                            <span class="w-3 text-right text-base-content/50">{{ $stars }}</span>
                            <svg class="w-3.5 h-3.5 text-portal-accent shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                            </svg>
                            <div class="flex-1 bg-base-200 rounded-full h-2">
                                <div class="bg-portal-accent rounded-full h-2 transition-all" style="width: {{ $percentage }}%"></div>
                            </div>
                            <span class="w-6 text-right text-xs text-base-content/40">{{ $count }}</span>
                        </div>
                    @endfor
                </div>
            </div>
        </div>
    @endif

    {{-- Reviews List --}}
    @if($reviews->isEmpty())
        <div class="card-portal text-center py-12">
            <svg class="w-16 h-16 mx-auto text-base-content/15 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
            </svg>
            <h3 class="text-lg font-semibold text-base-content/70 mb-1">Noch keine Bewertungen</h3>
            <p class="text-sm text-base-content/40 max-w-sm mx-auto">
                Sobald Kunden Bewertungen abgeben, erscheinen sie hier. Teilen Sie Ihre Firmenseite, um Bewertungen zu erhalten.
            </p>
            <a href="{{ route('portal.companies.show', $company->slug) }}" target="_blank"
               class="btn btn-sm btn-portal-outline mt-4 inline-flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                </svg>
                Firmenseite ansehen
            </a>
        </div>
    @else
        <div class="space-y-4">
            @foreach($reviews as $review)
                <div class="card-portal {{ $review->isPending() ? 'border-l-4 border-l-portal-accent' : ($review->isRejected() ? 'border-l-4 border-l-red-400 opacity-60' : '') }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-medium text-sm text-base-content">{{ $review->author_name ?: 'Anonym' }}</span>

                                @if($review->isPending())
                                    <span class="badge badge-sm bg-amber-100 text-amber-700 border-0">Wartend</span>
                                @elseif($review->isRejected())
                                    <span class="badge badge-sm bg-red-100 text-red-700 border-0">Abgelehnt</span>
                                @elseif($review->isApproved())
                                    <span class="badge badge-sm bg-green-100 text-green-700 border-0">Veröffentlicht</span>
                                @endif
                            </div>

                            {{-- Stars --}}
                            <div class="flex items-center gap-0.5 mt-1" aria-label="{{ $review->rating }} von 5 Sternen">
                                @for($i = 1; $i <= 5; $i++)
                                    <svg class="w-4 h-4 {{ $i <= $review->rating ? 'text-portal-accent' : 'text-base-content/20' }}" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                                    </svg>
                                @endfor
                            </div>

                            @if($review->title)
                                <h3 class="font-medium text-base-content mt-2">{{ $review->title }}</h3>
                            @endif

                            @if($review->body)
                                <p class="text-sm text-base-content/70 mt-1">{{ $review->body }}</p>
                            @endif

                            @if($review->isRejected() && $review->moderation_note)
                                <p class="text-xs text-red-500 mt-2 flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                    </svg>
                                    Grund: {{ $review->moderation_note }}
                                </p>
                            @endif
                        </div>

                        <span class="text-xs text-base-content/40 shrink-0">{{ $review->created_at->format('d.m.Y') }}</span>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Pagination --}}
        <div class="mt-6">
            {{ $reviews->links() }}
        </div>
    @endif
@endsection
