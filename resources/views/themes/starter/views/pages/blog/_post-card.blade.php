{{-- Blog Post Card (wiederverwendbar in index, category, tag, search) --}}
<article class="blog-card {{ ($featured ?? false) ? 'blog-card--featured' : '' }} reveal">
    <a href="{{ route('portal.blog.show', $post->slug) }}" class="block group {{ ($featured ?? false) ? 'sm:grid sm:grid-cols-2' : '' }}">
        {{-- Featured Image --}}
        @if($post->featured_image_url)
            <div class="blog-card__image">
                <img src="{{ $post->featured_image_url }}"
                     alt="{{ $post->title }}"
                     loading="lazy">
            </div>
        @else
            <div class="blog-card__image-placeholder">
                <svg class="w-12 h-12 text-[#CBD5E1]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                </svg>
            </div>
        @endif

        <div class="blog-card__body">
            {{-- Meta --}}
            <div class="blog-card__meta">
                @if($post->category)
                    <span class="blog-card__category">{{ $post->category->name }}</span>
                @endif
                <span>{{ $post->formatted_date }}</span>
                @if($post->reading_time_minutes)
                    <span>&middot; {{ $post->reading_time_minutes }} Min.</span>
                @endif
            </div>

            {{-- Title --}}
            <h2 class="blog-card__title">{{ $post->title }}</h2>

            {{-- Excerpt --}}
            <p class="blog-card__excerpt">{{ $post->excerpt_or_truncated }}</p>

            {{-- Read More --}}
            <div class="blog-card__readmore">
                Weiterlesen
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </div>
        </div>
    </a>
</article>
