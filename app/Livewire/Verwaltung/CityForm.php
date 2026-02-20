<?php

namespace App\Livewire\Verwaltung;

use App\Models\Portal\City;
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

    public bool $autoSlug = true;

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
        ];
    }

    protected $messages = [
        'name.required' => 'Bitte geben Sie einen Stadtnamen ein.',
        'slug.required' => 'Bitte geben Sie einen Slug ein.',
        'slug.regex' => 'Der Slug darf nur Kleinbuchstaben, Zahlen und Bindestriche enthalten.',
        'slug.unique' => 'Dieser Slug wird bereits verwendet.',
        'latitude.between' => 'Breitengrad muss zwischen -90 und 90 liegen.',
        'longitude.between' => 'Längengrad muss zwischen -180 und 180 liegen.',
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

        // Cast empty strings to null for optional fields
        foreach (['zipcode', 'administrative_area_level_1', 'community', 'latitude', 'longitude'] as $field) {
            if (isset($validated[$field]) && $validated[$field] === '') {
                $validated[$field] = null;
            }
        }

        if ($this->cityId) {
            $city = City::findOrFail($this->cityId);
            $city->update($validated);
            $this->dispatch('toast', type: 'success', message: "Stadt \"{$city->name}\" wurde aktualisiert.");
        } else {
            $city = City::create($validated);
            $this->dispatch('toast', type: 'success', message: "Stadt \"{$city->name}\" wurde erstellt.");
        }

        $this->redirect(route('verwaltung.cities.index'), navigate: false);
    }

    public function render()
    {
        return view('livewire.verwaltung.city-form');
    }
}
