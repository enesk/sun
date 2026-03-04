<?php

namespace App\Livewire\Verwaltung;

use App\Models\Portal\FAQ;
use Livewire\Component;

class FaqManager extends Component
{
    // ── Formular-State (Neue FAQ / Bearbeitung) ──

    public bool $showForm = false;
    public ?int $editingId = null;
    public string $question = '';
    public string $answer = '';
    public string $page = 'faq';
    public bool $is_active = true;

    // ── Bulk-Auswahl ──

    public array $selected = [];

    protected function rules(): array
    {
        return [
            'question' => 'required|string|max:500',
            'answer' => 'required|string|max:10000',
            'page' => 'required|in:home,faq',
            'is_active' => 'boolean',
        ];
    }

    protected $messages = [
        'question.required' => 'Bitte geben Sie eine Frage ein.',
        'question.max' => 'Die Frage darf maximal 500 Zeichen lang sein.',
        'answer.required' => 'Bitte geben Sie eine Antwort ein.',
    ];

    // ── CRUD ──

    public function openCreateForm(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $faq = FAQ::findOrFail($id);

        $this->editingId = $faq->id;
        $this->question = $faq->question;
        $this->answer = $faq->answer;
        $this->page = $faq->page;
        $this->is_active = $faq->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $validated = $this->validate();

        if ($this->editingId) {
            $faq = FAQ::findOrFail($this->editingId);
            $faq->update($validated);
            $this->dispatch('toast', type: 'success', message: 'FAQ wurde aktualisiert.');
        } else {
            $maxOrder = FAQ::max('sort_order') ?? -1;
            $validated['sort_order'] = $maxOrder + 1;
            FAQ::create($validated);
            $this->dispatch('toast', type: 'success', message: 'FAQ wurde erstellt.');
        }

        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $faq = FAQ::findOrFail($id);
        $faq->delete();
        $this->dispatch('toast', type: 'success', message: 'FAQ wurde gelöscht.');
    }

    public function toggleActive(int $id): void
    {
        $faq = FAQ::findOrFail($id);
        $faq->update(['is_active' => ! $faq->is_active]);

        $status = $faq->is_active ? 'aktiviert' : 'deaktiviert';
        $this->dispatch('toast', type: 'success', message: "FAQ wurde {$status}.");
    }

    public function bulkDelete(): void
    {
        if (empty($this->selected)) {
            return;
        }

        $count = FAQ::whereIn('id', $this->selected)->delete();
        $this->selected = [];

        $this->dispatch('toast', type: 'success', message: "{$count} FAQs wurden gelöscht.");
    }

    // ── Drag-and-Drop Sortierung ──

    public function updateOrder(array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            FAQ::where('id', (int) $id)->update(['sort_order' => $index]);
        }

        $this->dispatch('toast', type: 'success', message: 'Reihenfolge gespeichert.');
    }

    // ── Helpers ──

    public function cancelForm(): void
    {
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->question = '';
        $this->answer = '';
        $this->page = 'faq';
        $this->is_active = true;
        $this->resetValidation();
    }

    public function render()
    {
        $faqs = FAQ::ordered()->get();

        return view('livewire.verwaltung.faq-manager', compact('faqs'));
    }
}
