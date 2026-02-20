<?php

namespace App\Http\Requests\Verwaltung;

class UpdateCompanyRequest extends StoreCompanyRequest
{
    public function rules(): array
    {
        $rules = parent::rules();

        // On update, name unique check should exclude the current company
        // Logo/cover are optional on update
        $rules['logo'] = ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048'];
        $rules['cover'] = ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:4096'];

        return $rules;
    }
}
