<?php

namespace App\Livewire\Verwaltung;

use App\Models\Portal\Post;
use App\Models\Portal\PostCategory;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class PostTable extends Component
{
    use WithPagination;

    public string $search = '';
    public string $filterStatus = '';
    public string $filterCategory = '';
    public string $sortBy = 'created_at';
    public string $sortDir = 'desc';
    public int $perPage = 15;

    public array $selected = [];
    public bool $selectAll = false;

    protected $queryString = [
        'search' => ['except' => '', 'as' => 'q'],
        'filterStatus' => ['except' => '', 'as' => 'status'],
        'filterCategory' => ['except' => '', 'as' => 'category'],
        'sortBy' => ['except' => 'created_at', 'as' => 'sort'],
        'sortDir' => ['except' => 'desc', 'as' => 'dir'],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatingFilterCategory(): void
    {
        $this->resetPage();
    }

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = 'asc';
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'filterStatus', 'filterCategory']);
        $this->resetPage();
    }

    public function toggleStatus(int $id): void
    {
        $post = Post::findOrFail($id);

        if ($post->status === Post::STATUS_PUBLISHED) {
            $post->unpublish();
            $this->dispatch('toast', type: 'success', message: "Artikel \"{$post->title}\" zurückgezogen.");
        } else {
            $post->publish();
            $this->dispatch('toast', type: 'success', message: "Artikel \"{$post->title}\" veröffentlicht.");
        }
    }

    public function deletePost(int $id): void
    {
        $post = Post::findOrFail($id);
        $title = $post->title;

        $post->tags()->detach();
        $post->clearMediaCollection('featured_image');
        $post->delete();

        $this->dispatch('toast', type: 'success', message: "Artikel \"{$title}\" wurde gelöscht.");
    }

    public function bulkDelete(): void
    {
        if (empty($this->selected)) {
            return;
        }

        $posts = Post::whereIn('id', $this->selected)->get();

        foreach ($posts as $post) {
            $post->tags()->detach();
            $post->clearMediaCollection('featured_image');
            $post->delete();
        }

        $count = count($this->selected);
        $this->selected = [];
        $this->selectAll = false;

        $this->dispatch('toast', type: 'success', message: "{$count} Artikel gelöscht.");
    }

    public function render()
    {
        $query = Post::query()
            ->with(['category'])
            ->withCount('tags');

        // Search
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('title', 'like', "%{$this->search}%")
                    ->orWhere('excerpt', 'like', "%{$this->search}%");
            });
        }

        // Filter
        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        if ($this->filterCategory) {
            $query->where('category_id', $this->filterCategory);
        }

        // Sort
        $query->orderBy($this->sortBy, $this->sortDir);

        $posts = $query->paginate($this->perPage);
        $categories = PostCategory::ordered()->get();

        return view('livewire.verwaltung.post-table', [
            'posts' => $posts,
            'categories' => $categories,
        ]);
    }
}
