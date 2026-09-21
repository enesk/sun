{{-- Artikelkarte der Ratgeber-Listen im Theme sun-v2 (Uebersicht, Suche, Schlagwort). Erwartet $post und $sunBlog. --}}
<a href="{{ route('guide.show', $post->slug) }}" class="card-interactive overflow-hidden flex flex-col">
  @if($post->featured_image_url)
    <img src="{{ $post->featured_image_url }}" alt="" width="640" height="360" class="aspect-video w-full object-cover" loading="lazy">
  @else
    <div class="aspect-video w-full bg-brand-50 text-brand flex items-center justify-center" aria-hidden="true"><x-sun.icon :name="$sunBlog['icon']($post->category?->slug)" class="size-14" /></div>
  @endif
  <div class="p-5 flex flex-col gap-2 flex-1">
    @if($post->category)
      <span class="pill self-start">{{ $post->category->name }}</span>
    @endif
    <h3 class="text-lg font-semibold text-zinc-900 leading-snug">{{ $post->title }}</h3>
    <p class="text-sm text-zinc-500 line-clamp-2">{{ $post->excerpt_or_truncated }}</p>
    @if($post->reading_time_minutes)
      <span class="text-sm text-zinc-500 mt-auto pt-1">{{ __('portal.layout.reading_time', ['minuten' => $post->reading_time_minutes]) }}</span>
    @endif
  </div>
</a>
