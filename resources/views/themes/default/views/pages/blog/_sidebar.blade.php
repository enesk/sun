{{-- Blog Sidebar (Kategorien, Tags, Recent Posts) --}}
<aside class="blog-sidebar" aria-label="Blog-Sidebar">

    {{-- Kategorien --}}
    @if($categories->isNotEmpty())
        <div class="blog-sidebar__card">
            <h3 class="blog-sidebar__heading">Kategorien</h3>
            <ul class="space-y-0.5">
                @foreach($categories as $cat)
                    <li>
                        <a href="{{ route('portal.blog.category', $cat->slug) }}"
                           class="blog-sidebar__link {{ (isset($category) && $category->id === $cat->id) ? 'blog-sidebar__link--active' : '' }}">
                            <span>{{ $cat->name }}</span>
                            <span class="blog-sidebar__count">{{ $cat->posts_count }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Neueste Artikel --}}
    @if(isset($recentPosts) && $recentPosts->isNotEmpty())
        <div class="blog-sidebar__card">
            <h3 class="blog-sidebar__heading">Neueste Artikel</h3>
            <ul class="space-y-3">
                @foreach($recentPosts as $recent)
                    <li>
                        <a href="{{ route('portal.blog.show', $recent->slug) }}" class="block group">
                            <span class="text-sm font-medium text-[#334155] group-hover:text-[var(--portal-primary,#3B82F6)] transition-colors line-clamp-2">{{ $recent->title }}</span>
                            <span class="text-xs text-[#94A3B8] mt-0.5 block">{{ $recent->formatted_date }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Tags --}}
    @if($popularTags->isNotEmpty())
        <div class="blog-sidebar__card">
            <h3 class="blog-sidebar__heading">Themen</h3>
            <div class="flex flex-wrap gap-2">
                @foreach($popularTags as $t)
                    <a href="{{ route('portal.blog.tag', $t->slug) }}"
                       class="blog-sidebar__tag {{ (isset($tag) && $tag->id === $t->id) ? 'blog-sidebar__tag--active' : '' }}">
                        #{{ $t->name }}
                    </a>
                @endforeach
            </div>
        </div>
    @endif

</aside>
