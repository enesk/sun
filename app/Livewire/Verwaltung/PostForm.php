<?php

namespace App\Livewire\Verwaltung;

use App\Models\Portal\Post;
use App\Models\Portal\PostCategory;
use App\Models\Portal\PostTag;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class PostForm extends Component
{
    use WithFileUploads;

    public ?int $postId = null;
    public bool $isEdit = false;

    // Post fields
    public string $title = '';
    public string $slug = '';
    public string $excerpt = '';
    public string $body = '';
    public ?int $category_id = null;
    public string $status = 'draft';
    public ?string $published_at = null;
    public string $meta_title = '';
    public string $meta_description = '';

    // Tags (comma-separated input)
    public string $tagInput = '';
    public array $selectedTagIds = [];

    // Image
    public $featured_image = null;
    public ?string $existingImage = null;
    public bool $removeImage = false;

    public bool $autoSlug = true;

    protected function rules(): array
    {
        $slugUnique = 'unique:posts,slug';
        if ($this->postId) {
            $slugUnique .= ',' . $this->postId;
        }

        return [
            'title' => 'required|string|min:3|max:255',
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9\-]+$/', $slugUnique],
            'excerpt' => 'nullable|string|max:1000',
            'body' => 'required|string|min:50',
            'category_id' => 'nullable|exists:post_categories,id',
            'status' => 'required|in:draft,published,archived',
            'published_at' => 'nullable|date',
            'meta_title' => 'nullable|string|max:160',
            'meta_description' => 'nullable|string|max:500',
            'featured_image' => 'nullable|image|max:5120', // 5MB
        ];
    }

    protected $messages = [
        'title.required' => 'Bitte geben Sie einen Titel ein.',
        'title.min' => 'Der Titel muss mindestens 3 Zeichen lang sein.',
        'slug.required' => 'Bitte geben Sie einen Slug ein.',
        'slug.regex' => 'Der Slug darf nur Kleinbuchstaben, Zahlen und Bindestriche enthalten.',
        'slug.unique' => 'Dieser Slug wird bereits verwendet.',
        'body.required' => 'Bitte geben Sie einen Artikeltext ein.',
        'body.min' => 'Der Artikeltext muss mindestens 50 Zeichen lang sein.',
        'featured_image.max' => 'Das Bild darf maximal 5 MB groß sein.',
    ];

    public function mount(?Post $post = null): void
    {
        if ($post && $post->exists) {
            $this->postId = $post->id;
            $this->isEdit = true;
            $this->title = $post->title;
            $this->slug = $post->slug;
            $this->excerpt = $post->excerpt ?? '';
            $this->body = $post->body;
            $this->category_id = $post->category_id;
            $this->status = $post->status;
            $this->published_at = $post->published_at?->format('Y-m-d\TH:i');
            $this->meta_title = $post->meta_title ?? '';
            $this->meta_description = $post->meta_description ?? '';
            $this->autoSlug = false;

            // Load tags
            $this->selectedTagIds = $post->tags->pluck('id')->toArray();
            $this->tagInput = $post->tags->pluck('name')->implode(', ');

            // Existing image
            $this->existingImage = $post->featured_image_url;
        }
    }

    public function updatedTitle(string $value): void
    {
        if ($this->autoSlug) {
            $this->slug = Str::slug($value);
        }
    }

    public function updatedSlug(): void
    {
        $this->autoSlug = false;
    }

    public function save(): void
    {
        if (! auth()->user()->isAdmin()) {
            return;
        }

        $validated = $this->validate();

        // Parse tags from input
        $tagIds = $this->resolveTagIds();

        // Prepare post data
        $postData = [
            'title' => $validated['title'],
            'slug' => $validated['slug'],
            'excerpt' => $validated['excerpt'] ?: null,
            'body' => $validated['body'],
            'category_id' => $validated['category_id'] ?: null,
            'status' => $validated['status'],
            'published_at' => $validated['published_at'] ? \Carbon\Carbon::parse($validated['published_at']) : null,
            'meta_title' => $validated['meta_title'] ?: null,
            'meta_description' => $validated['meta_description'] ?: null,
            'author_id' => auth()->id(),
        ];

        // Auto-set published_at when publishing
        if ($postData['status'] === Post::STATUS_PUBLISHED && ! $postData['published_at']) {
            $postData['published_at'] = now();
        }

        if ($this->isEdit) {
            $post = Post::findOrFail($this->postId);
            $post->update($postData);
            $post->tags()->sync($tagIds);

            // Handle image
            if ($this->removeImage && ! $this->featured_image) {
                $post->clearMediaCollection('featured_image');
                $post->update(['featured_image' => null]);
            }

            if ($this->featured_image) {
                $post->clearMediaCollection('featured_image');
                $post->addMedia($this->featured_image->getRealPath())
                    ->usingFileName(Str::slug($post->title) . '.' . $this->featured_image->getClientOriginalExtension())
                    ->toMediaCollection('featured_image');
            }

            $this->dispatch('toast', type: 'success', message: "Artikel \"{$post->title}\" wurde aktualisiert.");
        } else {
            $post = Post::create($postData);
            $post->tags()->sync($tagIds);

            if ($this->featured_image) {
                $post->addMedia($this->featured_image->getRealPath())
                    ->usingFileName(Str::slug($post->title) . '.' . $this->featured_image->getClientOriginalExtension())
                    ->toMediaCollection('featured_image');
            }

            $this->dispatch('toast', type: 'success', message: "Artikel \"{$post->title}\" wurde erstellt.");
        }

        $this->redirect(route('verwaltung.blog.index'), navigate: false);
    }

    public function saveAsDraft(): void
    {
        $this->status = 'draft';
        $this->save();
    }

    public function publish(): void
    {
        $this->status = 'published';
        $this->save();
    }

    private function resolveTagIds(): array
    {
        if (empty(trim($this->tagInput))) {
            return [];
        }

        $tagNames = array_map('trim', explode(',', $this->tagInput));
        $tagNames = array_filter($tagNames);
        $ids = [];

        foreach ($tagNames as $name) {
            $tag = PostTag::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name]
            );
            $ids[] = $tag->id;
        }

        return $ids;
    }

    public function render()
    {
        $categories = PostCategory::ordered()->get();
        $allTags = PostTag::ordered()->get();

        return view('livewire.verwaltung.post-form', [
            'categories' => $categories,
            'allTags' => $allTags,
        ]);
    }
}
