<?php

namespace App\Livewire\Verwaltung;

use App\Models\Portal\City;
use App\Models\Portal\CityContent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Component;

class CityForm extends Component
{
    public ?int $cityId = null;

    public string $name = '';
    public string $slug = '';
    public string $zipcode = '';
    public string $administrative_area_level_1 = '';
    public string $community = '';
    public ?string $latitude = null;
    public ?string $longitude = null;

    // CityContent fields
    public string $intro_text = '';
    public string $meta_title = '';
    public string $meta_description = '';
    public bool $is_generated = false;
    public ?string $generated_at = null;

    public bool $autoSlug = true;
    public bool $isGenerating = false;

    protected function rules(): array
    {
        $uniqueSlugRule = 'unique:cities,slug';
        if ($this->cityId) {
            $uniqueSlugRule .= ',' . $this->cityId;
        }

        return [
            'name' => 'required|string|max:255',
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9\-]+$/', $uniqueSlugRule],
            'zipcode' => 'nullable|string|max:10',
            'administrative_area_level_1' => 'nullable|string|max:255',
            'community' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'intro_text' => 'nullable|string|max:10000',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
        ];
    }

    protected $messages = [
        'name.required' => 'Bitte geben Sie einen Stadtnamen ein.',
        'slug.required' => 'Bitte geben Sie einen Slug ein.',
        'slug.regex' => 'Der Slug darf nur Kleinbuchstaben, Zahlen und Bindestriche enthalten.',
        'slug.unique' => 'Dieser Slug wird bereits verwendet.',
        'latitude.between' => 'Breitengrad muss zwischen -90 und 90 liegen.',
        'longitude.between' => 'Längengrad muss zwischen -180 und 180 liegen.',
        'meta_description.max' => 'Die Meta-Beschreibung darf maximal 500 Zeichen lang sein.',
    ];

    public function mount(?City $city = null): void
    {
        if ($city && $city->exists) {
            $this->cityId = $city->id;
            $this->name = $city->name;
            $this->slug = $city->slug ?? '';
            $this->zipcode = $city->zipcode ?? '';
            $this->administrative_area_level_1 = $city->administrative_area_level_1 ?? '';
            $this->community = $city->community ?? '';
            $this->latitude = $city->latitude;
            $this->longitude = $city->longitude;
            $this->autoSlug = false;

            // Load CityContent
            $content = $city->cityContent;
            if ($content) {
                $this->intro_text = $content->intro_text ?? '';
                $this->meta_title = $content->meta_title ?? '';
                $this->meta_description = $content->meta_description ?? '';
                $this->is_generated = (bool) $content->is_generated;
                $this->generated_at = $content->generated_at?->format('d.m.Y H:i');
            }
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

    public function generateContent(): void
    {
        if (! auth()->user()->isAdmin() || ! $this->cityId) {
            return;
        }

        $provider = config('ai.default', 'anthropic');
        $config = config("ai.providers.{$provider}");

        if (! $config || empty($config['api_key'])) {
            $this->dispatch('toast', type: 'error', message: 'KI-Provider nicht konfiguriert. Bitte API-Key in .env setzen.');

            return;
        }

        $this->isGenerating = true;

        try {
            $city = City::withCount('companies')->findOrFail($this->cityId);
            $portalName = tenant()->name ?? config('app.name', 'Branchenportal');
            $prompt = $this->buildPrompt($city, $portalName);

            $text = $provider === 'anthropic'
                ? $this->callAnthropic($prompt, $config)
                : $this->callOpenAI($prompt, $config);

            if (empty($text)) {
                $this->dispatch('toast', type: 'error', message: 'KI-Generierung hat keinen Text geliefert.');
                $this->isGenerating = false;

                return;
            }

            $this->intro_text = $text;
            $this->meta_title = "Unternehmen in {$city->name} — {$portalName}";

            $state = $city->administrative_area_level_1 ?: 'Deutschland';
            $count = $city->companies_count ?? 0;
            $this->meta_description = $count > 0
                ? "Finden Sie {$count} Unternehmen und Dienstleister in {$city->name} ({$state}). Lokale Firmen entdecken, Bewertungen lesen und Kontakt aufnehmen — auf {$portalName}."
                : "Entdecken Sie lokale Unternehmen und Dienstleister in {$city->name} ({$state}) — auf {$portalName}.";

            $this->is_generated = true;
            $this->generated_at = now()->format('d.m.Y H:i');

            $this->dispatch('toast', type: 'success', message: 'Introtext wurde generiert. Speichern nicht vergessen!');
        } catch (\Exception $e) {
            Log::error("CityForm generateContent error: {$e->getMessage()}");
            $this->dispatch('toast', type: 'error', message: 'Fehler bei der KI-Generierung: ' . Str::limit($e->getMessage(), 100));
        }

        $this->isGenerating = false;
    }

    public function save(): void
    {
        if (! auth()->user()->isAdmin()) {
            return;
        }

        $validated = $this->validate();

        // Cast empty strings to null for optional fields
        foreach (['zipcode', 'administrative_area_level_1', 'community', 'latitude', 'longitude'] as $field) {
            if (isset($validated[$field]) && $validated[$field] === '') {
                $validated[$field] = null;
            }
        }

        // Separate city and content data
        $contentData = [
            'intro_text' => $validated['intro_text'] ?? '',
            'meta_title' => $validated['meta_title'] ?? '',
            'meta_description' => $validated['meta_description'] ?? '',
        ];
        unset($validated['intro_text'], $validated['meta_title'], $validated['meta_description']);

        if ($this->cityId) {
            $city = City::findOrFail($this->cityId);
            $city->update($validated);

            // Save CityContent if any content field is filled
            if (! empty($contentData['intro_text']) || ! empty($contentData['meta_title']) || ! empty($contentData['meta_description'])) {
                CityContent::updateOrCreate(
                    ['city_id' => $city->id],
                    array_merge($contentData, [
                        'is_generated' => $this->is_generated,
                        'generated_at' => $this->is_generated ? ($city->cityContent?->generated_at ?? now()) : null,
                    ])
                );
            }

            $this->dispatch('toast', type: 'success', message: "Stadt \"{$city->name}\" wurde aktualisiert.");
        } else {
            $city = City::create($validated);

            if (! empty($contentData['intro_text'])) {
                CityContent::create(array_merge($contentData, [
                    'city_id' => $city->id,
                    'is_generated' => $this->is_generated,
                    'generated_at' => $this->is_generated ? now() : null,
                ]));
            }

            $this->dispatch('toast', type: 'success', message: "Stadt \"{$city->name}\" wurde erstellt.");
        }

        $this->redirect(route('verwaltung.cities.index'), navigate: false);
    }

    private function buildPrompt(City $city, string $portalName): string
    {
        $state = $city->administrative_area_level_1 ?: 'Deutschland';
        $companyCount = $city->companies_count ?? 0;
        $community = $city->community ? " ({$city->community})" : '';

        return <<<PROMPT
Schreibe einen informativen Introtext für die Stadt {$city->name}{$community} in {$state} im Kontext des Branchenportals "{$portalName}".

Anforderungen:
- 150-250 Wörter, auf Deutsch, professioneller aber zugänglicher Ton
- Erwähne die Stadt, ihre Lage und regionale Bedeutung
- Stelle einen Bezug zu lokalen Unternehmen und Dienstleistern her
- Aktuell sind {$companyCount} Unternehmen aus {$city->name} auf {$portalName} gelistet
- SEO-optimiert: Verwende natürlich Keywords wie "Unternehmen in {$city->name}", "Dienstleister {$city->name}", "Firmen {$city->name}"
- Format: Reines HTML mit <p>-Tags (2-3 Absätze). Optional eine <h3>-Zwischenüberschrift
- Keine erfundenen Fakten, keine konkreten Einwohnerzahlen wenn unsicher
- Kein Markdown, kein Intro wie "Hier ist der Text:", direkt mit dem HTML-Content beginnen
PROMPT;
    }

    private function callAnthropic(string $prompt, array $config): string
    {
        $response = Http::withHeaders([
            'x-api-key' => $config['api_key'],
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])
            ->timeout(30)
            ->retry(2, 3000)
            ->post($config['base_url'] . '/messages', [
                'model' => $config['model'],
                'max_tokens' => $config['max_tokens'],
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException("Anthropic API error: {$response->status()}");
        }

        return $response->json('content.0.text') ?? '';
    }

    private function callOpenAI(string $prompt, array $config): string
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $config['api_key'],
            'Content-Type' => 'application/json',
        ])
            ->timeout(30)
            ->retry(2, 3000)
            ->post($config['base_url'] . '/chat/completions', [
                'model' => $config['model'],
                'max_tokens' => $config['max_tokens'],
                'messages' => [
                    ['role' => 'system', 'content' => 'Du bist ein professioneller SEO-Texter für deutsche Branchenportale.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException("OpenAI API error: {$response->status()}");
        }

        return $response->json('choices.0.message.content') ?? '';
    }

    public function render()
    {
        return view('livewire.verwaltung.city-form');
    }
}
