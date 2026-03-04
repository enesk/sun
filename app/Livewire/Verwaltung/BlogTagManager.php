<?php

namespace App\Livewire\Verwaltung;

use App\Models\Portal\PostTag;
use Illuminate\Support\Str;
use Livewire\Component;

class BlogTagManager extends Component
{
    public ?int $editingId = null;
    public string $name = '';
    public string $slug = '';
    public bool $showForm = false;
    public bool $autoSlug = true;

    public array $selected = [];
    public string $search = '';

    protected function rules(): array
    {
        $slugUnique = 'unique:post_tags,slug';
        if ($this->editingId) {
            $slugUnique .= ',' . $this->editingId;
        }

        return [
            'name' => 'required|string|max:255',
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9\-]+$/', $slugUnique],
        ];
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showForm = true;
        $this->autoSlug = true;
    }

    public function openEdit(int $id): void
    {
        $tag = PostTag::findOrFail($id);
        $this->editingId = $tag->id;
        $this->name = $tag->name;
        $this->slug = $tag->slug;
        $this->showForm = true;
        $this->autoSlug = false;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    public function updatedName(string $value): void
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

        if ($this->editingId) {
            $tag = PostTag::findOrFail($this->editingId);
            $tag->update($validated);
            $this->dispatch('toast', type: 'success', message: "Tag \"{$tag->name}\" aktualisiert.");
        } else {
            PostTag::create($validated);
            $this->dispatch('toast', type: 'success', message: "Tag \"{$validated['name']}\" erstellt.");
        }

        $this->closeForm();
    }

    public function deleteTag(int $id): void
    {
        if (! auth()->user()->isAdmin()) {
            return;
        }

        $tag = PostTag::findOrFail($id);
        $name = $tag->name;
        $tag->posts()->detach();
        $tag->delete();

        $this->dispatch('toast', type: 'success', message: "Tag \"{$name}\" gelöscht.");
    }

    public function bulkDelete(): void
    {
        if (! auth()->user()->isAdmin() || empty($this->selected)) {
            return;
        }

        $tags = PostTag::whereIn('id', $this->selected)->get();

        foreach ($tags as $tag) {
            $tag->posts()->detach();
            $tag->delete();
        }

        $count = count($this->selected);
        $this->selected = [];
        $this->dispatch('toast', type: 'success', message: "{$count} Tags gelöscht.");
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->slug = '';
        $this->autoSlug = true;
        $this->resetValidation();
    }

    public function render()
    {
        $query = PostTag::withCount('posts')->ordered();

        if ($this->search) {
            $query->where('name', 'like', "%{$this->search}%");
        }

        return view('livewire.verwaltung.blog-tag-manager', [
            'tags' => $query->get(),
        ]);
    }
}
