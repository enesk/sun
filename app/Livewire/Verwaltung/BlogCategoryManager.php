<?php

namespace App\Livewire\Verwaltung;

use App\Models\Portal\PostCategory;
use Illuminate\Support\Str;
use Livewire\Component;

class BlogCategoryManager extends Component
{
    public ?int $editingId = null;
    public string $name = '';
    public string $slug = '';
    public string $description = '';
    public ?int $parent_id = null;
    public int $sort_order = 0;

    public bool $showForm = false;
    public bool $autoSlug = true;

    public array $selected = [];

    protected function rules(): array
    {
        $slugUnique = 'unique:post_categories,slug';
        if ($this->editingId) {
            $slugUnique .= ',' . $this->editingId;
        }

        return [
            'name' => 'required|string|max:255',
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9\-]+$/', $slugUnique],
            'description' => 'nullable|string|max:1000',
            'parent_id' => 'nullable|exists:post_categories,id',
            'sort_order' => 'integer|min:0',
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
        $category = PostCategory::findOrFail($id);
        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->slug = $category->slug;
        $this->description = $category->description ?? '';
        $this->parent_id = $category->parent_id;
        $this->sort_order = $category->sort_order;
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

        // Prevent self-referencing parent
        if ($this->editingId && $validated['parent_id'] == $this->editingId) {
            $validated['parent_id'] = null;
        }

        if (empty($validated['description'])) {
            $validated['description'] = null;
        }

        if ($this->editingId) {
            $category = PostCategory::findOrFail($this->editingId);
            $category->update($validated);
            $this->dispatch('toast', type: 'success', message: "Kategorie \"{$category->name}\" aktualisiert.");
        } else {
            $category = PostCategory::create($validated);
            $this->dispatch('toast', type: 'success', message: "Kategorie \"{$category->name}\" erstellt.");
        }

        $this->closeForm();
    }

    public function deleteCategory(int $id): void
    {
        if (! auth()->user()->isAdmin()) {
            return;
        }

        $category = PostCategory::withCount('posts')->findOrFail($id);

        if ($category->posts_count > 0) {
            $this->dispatch('toast', type: 'error', message: "Kategorie \"{$category->name}\" hat {$category->posts_count} Artikel und kann nicht gelöscht werden.");

            return;
        }

        // Move children to parent
        PostCategory::where('parent_id', $id)->update(['parent_id' => $category->parent_id]);

        $name = $category->name;
        $category->delete();

        $this->dispatch('toast', type: 'success', message: "Kategorie \"{$name}\" gelöscht.");
    }

    public function bulkDelete(): void
    {
        if (! auth()->user()->isAdmin() || empty($this->selected)) {
            return;
        }

        $categories = PostCategory::withCount('posts')->whereIn('id', $this->selected)->get();
        $deleted = 0;

        foreach ($categories as $category) {
            if ($category->posts_count === 0) {
                PostCategory::where('parent_id', $category->id)->update(['parent_id' => $category->parent_id]);
                $category->delete();
                $deleted++;
            }
        }

        $this->selected = [];
        $this->dispatch('toast', type: 'success', message: "{$deleted} Kategorien gelöscht.");
    }

    public function updateOrder(array $order): void
    {
        foreach ($order as $index => $id) {
            PostCategory::where('id', $id)->update(['sort_order' => $index]);
        }

        $this->dispatch('toast', type: 'success', message: 'Reihenfolge aktualisiert.');
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->slug = '';
        $this->description = '';
        $this->parent_id = null;
        $this->sort_order = 0;
        $this->autoSlug = true;
        $this->resetValidation();
    }

    public function render()
    {
        $categories = PostCategory::with('parent')
            ->withCount('posts')
            ->ordered()
            ->get();

        return view('livewire.verwaltung.blog-category-manager', [
            'categories' => $categories,
        ]);
    }
}
