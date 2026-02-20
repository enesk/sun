<?php

namespace App\Livewire\Verwaltung;

use App\Models\Portal\Category;
use Illuminate\Support\Str;
use Livewire\Component;

class CategoryForm extends Component
{
    public ?int $categoryId = null;

    public string $name = '';
    public string $slug = '';
    public string $description = '';
    public string $icon = '';
    public ?int $parent_id = null;
    public int $sort_order = 0;

    public bool $autoSlug = true;

    protected function rules(): array
    {
        $uniqueSlugRule = 'unique:categories,slug';
        if ($this->categoryId) {
            $uniqueSlugRule .= ',' . $this->categoryId;
        }

        return [
            'name' => 'required|string|max:255',
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9\-]+$/', $uniqueSlugRule],
            'description' => 'nullable|string|max:1000',
            'icon' => 'nullable|string|max:100',
            'parent_id' => 'nullable|integer|exists:categories,id',
            'sort_order' => 'integer|min:0|max:9999',
        ];
    }

    protected $messages = [
        'name.required' => 'Bitte geben Sie einen Namen ein.',
        'slug.required' => 'Bitte geben Sie einen Slug ein.',
        'slug.regex' => 'Der Slug darf nur Kleinbuchstaben, Zahlen und Bindestriche enthalten.',
        'slug.unique' => 'Dieser Slug wird bereits verwendet.',
        'parent_id.exists' => 'Die gewählte Oberkategorie existiert nicht.',
    ];

    public function mount(?Category $category = null): void
    {
        if ($category && $category->exists) {
            $this->categoryId = $category->id;
            $this->name = $category->name;
            $this->slug = $category->slug;
            $this->description = $category->description ?? '';
            $this->icon = $category->icon ?? '';
            $this->parent_id = $category->parent_id;
            $this->sort_order = $category->sort_order ?? 0;
            $this->autoSlug = false;
        }
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

        // Prevent circular parent reference
        if ($this->categoryId && $this->parent_id === $this->categoryId) {
            $this->addError('parent_id', 'Eine Kategorie kann nicht ihre eigene Oberkategorie sein.');
            return;
        }

        if ($this->categoryId) {
            $category = Category::findOrFail($this->categoryId);
            $category->update($validated);
            $this->dispatch('toast', type: 'success', message: "Kategorie \"{$category->name}\" wurde aktualisiert.");
        } else {
            $category = Category::create($validated);
            $this->dispatch('toast', type: 'success', message: "Kategorie \"{$category->name}\" wurde erstellt.");
        }

        $this->redirect(route('verwaltung.categories.index'), navigate: false);
    }

    public function render()
    {
        $parentOptions = Category::roots()
            ->when($this->categoryId, fn ($q) => $q->where('id', '!=', $this->categoryId))
            ->ordered()
            ->pluck('name', 'id');

        return view('livewire.verwaltung.category-form', compact('parentOptions'));
    }
}
