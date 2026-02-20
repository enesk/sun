<?php

namespace App\Http\Requests\Verwaltung;

use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by controller
    }

    public function rules(): array
    {
        return [
            // Firmendaten
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'categories' => ['required', 'array', 'min:1', 'max:5'],
            'categories.*' => ['integer', 'exists:categories,id'],

            // Adresse
            'street' => ['required', 'string', 'max:255'],
            'house_no' => ['nullable', 'string', 'max:20'],
            'zipcode' => ['required', 'string', 'regex:/^\d{5}$/'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],

            // Kontakt
            'tel' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:500'],

            // Status (admin only)
            'is_active' => ['boolean'],
            'is_premium' => ['boolean'],
            'is_verified' => ['boolean'],

            // Media
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
            'cover' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:4096'],
            'gallery' => ['nullable', 'array', 'max:10'],
            'gallery.*' => ['image', 'mimes:jpeg,png,webp', 'max:4096'],

            // Öffnungszeiten
            'opening_hours' => ['nullable', 'array'],
            'opening_hours.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'opening_hours.*.opens_at' => ['nullable', 'date_format:H:i'],
            'opening_hours.*.closes_at' => ['nullable', 'date_format:H:i'],
            'opening_hours.*.is_closed' => ['boolean'],

            // Owner (admin only)
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Der Firmenname ist erforderlich.',
            'name.min' => 'Der Firmenname muss mindestens 2 Zeichen lang sein.',
            'categories.required' => 'Bitte wählen Sie mindestens eine Kategorie.',
            'categories.max' => 'Maximal 5 Kategorien erlaubt.',
            'street.required' => 'Die Straße ist erforderlich.',
            'zipcode.required' => 'Die Postleitzahl ist erforderlich.',
            'zipcode.regex' => 'Bitte geben Sie eine gültige 5-stellige PLZ ein.',
            'city_id.required' => 'Bitte wählen Sie eine Stadt.',
            'city_id.exists' => 'Die ausgewählte Stadt ist ungültig.',
            'email.email' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.',
            'website.url' => 'Bitte geben Sie eine gültige URL ein.',
            'logo.max' => 'Das Logo darf maximal 2 MB groß sein.',
            'cover.max' => 'Das Coverbild darf maximal 4 MB groß sein.',
            'gallery.max' => 'Maximal 10 Galerie-Bilder erlaubt.',
            'gallery.*.max' => 'Jedes Galerie-Bild darf maximal 4 MB groß sein.',
        ];
    }
}
