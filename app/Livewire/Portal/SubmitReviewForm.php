<?php

namespace App\Livewire\Portal;

use App\Models\Portal\Company;
use App\Models\Portal\Review;
use Livewire\Component;

class SubmitReviewForm extends Component
{
    public Company $company;

    public float $rating = 0;
    public string $authorName = '';
    public string $title = '';
    public string $body = '';

    public bool $submitted = false;
    public bool $showForm = false;

    protected function rules(): array
    {
        return [
            'rating' => ['required', 'numeric', 'min:0.5', 'max:5'],
            'authorName' => ['nullable', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:150'],
            'body' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function messages(): array
    {
        return [
            'rating.required' => 'Bitte wählen Sie eine Bewertung.',
            'rating.min' => 'Bitte wählen Sie mindestens einen halben Stern.',
            'authorName.max' => 'Der Name darf maximal 100 Zeichen lang sein.',
            'title.max' => 'Der Titel darf maximal 150 Zeichen lang sein.',
            'body.max' => 'Der Text darf maximal 2.000 Zeichen lang sein.',
        ];
    }

    public function toggleForm(): void
    {
        $this->showForm = !$this->showForm;
    }

    public function setRating(float $value): void
    {
        $this->rating = $value;
        $this->resetValidation('rating');
    }

    public function submit(): void
    {
        $this->validate();

        // Rate-Limiting: Max 1 Bewertung pro Company pro IP pro Tag
        $ip = request()->ip();
        $existingToday = Review::where('company_id', $this->company->id)
            ->whereDate('created_at', today())
            ->whereRaw("JSON_EXTRACT(created_at, '$') IS NOT NULL") // Ensure valid date
            ->count();

        // Simple IP-based check via session
        $sessionKey = 'review_submitted_' . $this->company->id;
        if (session()->has($sessionKey)) {
            $this->addError('rating', 'Sie haben heute bereits eine Bewertung für dieses Unternehmen abgegeben.');
            return;
        }

        Review::create([
            'company_id' => $this->company->id,
            'user_id' => auth()->id(),
            'author_name' => $this->authorName ?: null,
            'rating' => $this->rating,
            'title' => $this->title ?: null,
            'body' => $this->body ?: null,
            'is_approved' => false,
            'moderation_status' => Review::STATUS_PENDING,
        ]);

        session()->put($sessionKey, true);

        $this->submitted = true;
    }

    public function render()
    {
        return view('livewire.portal.submit-review-form');
    }
}
