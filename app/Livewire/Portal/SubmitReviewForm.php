<?php

namespace App\Livewire\Portal;

use App\AntiSpam\Concerns\InteractsWithAntiSpam;
use App\AntiSpam\Support\RateLimitGuard;
use App\Mail\NewReviewNotification;
use App\Models\Portal\Company;
use App\Models\Portal\Review;
use App\Turnstile\Concerns\InteractsWithTurnstile;
use App\Turnstile\Enums\TurnstileAction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;

class SubmitReviewForm extends Component
{
    // Honigtopf und Mindest-Ausfuellzeit (#22); die Properties kommen aus dem
    // Trait, gebunden von <x-antispam-fields wire /> in der Ansicht.
    use InteractsWithAntiSpam;
    use InteractsWithTurnstile;

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

    public function mount(): void
    {
        // Bewertungslink /bewerten/{slug} (#12) oeffnet das Formular direkt
        $this->showForm = request()->boolean('bewerten');
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
        // Honigtopf und Mindest-Ausfuellzeit (#22): still abweisen. Der
        // Besucher sieht dieselbe Dankesmeldung, es entsteht aber keine
        // Bewertung. Der Treffer steht in turnstile_verifications.
        if ($this->antiSpamRejects(TurnstileAction::LeadRequest)) {
            $this->submitted = true;
            return;
        }

        $this->validate();

        // Eine Bewertung je Betrieb und Sitzung
        $sessionKey = 'review_submitted_' . $this->company->id;
        if (session()->has($sessionKey)) {
            $this->addError('rating', 'Sie haben heute bereits eine Bewertung für dieses Unternehmen abgegeben.');
            return;
        }

        // Rate-Limit je IP-Hash und Portal (#22), Grenze in
        // config/antispam.php. Greift auch, wenn die Sitzung weggeworfen wird.
        $wartezeit = $this->antiSpamLimit(RateLimitGuard::REVIEW);

        if ($wartezeit !== null) {
            RateLimitGuard::log(RateLimitGuard::REVIEW, TurnstileAction::LeadRequest);
            $this->addError('rating', $this->antiSpamThrottleMessage($wartezeit));
            return;
        }

        // Turnstile zuletzt: erst wenn alle Felder stimmen, wird ein Token
        // eingeloest — Cloudflare nimmt jedes Token nur einmal an.
        $this->validateTurnstile(TurnstileAction::LeadRequest, null);

        $review = Review::create([
            'company_id' => $this->company->id,
            'user_id' => auth()->id(),
            'author_name' => $this->authorName ?: null,
            'rating' => $this->rating,
            'title' => $this->title ?: null,
            'body' => $this->body ?: null,
            'is_approved' => false,
            'moderation_status' => Review::STATUS_PENDING,
        ]);

        try {
            Mail::to('hello@eneskul.com')->send(new NewReviewNotification($review));
        } catch (\Exception $e) {
            Log::warning('SubmitReviewForm: Failed to send new review notification email', [
                'review_id' => $review->id,
                'error' => $e->getMessage(),
            ]);
        }

        session()->put($sessionKey, true);

        // Erst jetzt zaehlen: ein Tippfehler in der Validierung soll keinen
        // Versuch kosten.
        $this->antiSpamCount(RateLimitGuard::REVIEW);

        $this->submitted = true;
    }

    public function render()
    {
        return view('livewire.portal.submit-review-form');
    }
}
