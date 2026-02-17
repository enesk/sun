<?php

namespace App\Filament\Admin\Resources\Tenants\Pages;

use App\Filament\Admin\Resources\Tenants\TenantResource;
use App\Themes\ThemeManager;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class EditTenant extends EditRecord
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * Override to bypass Laravel's fill() which mishandles dot-notation keys.
     *
     * VirtualColumn stores attributes as flat dot-notation keys (e.g. "theme.active"),
     * but Laravel's fill() interprets dots as nested arrays and routes them into
     * the "data" cast column incorrectly. We set each attribute directly instead.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        foreach ($data as $key => $value) {
            $record->{$key} = $value;
        }

        $record->save();

        return $record;
    }

    /**
     * Mutate the record data before filling the form.
     *
     * Stancl's VirtualColumn stores data as flat dot-notation keys
     * (e.g. "theme.active", "branding.primary_color"), but Filament
     * interprets dots in field names as nested arrays. We need to
     * expand the flat keys into a nested structure for Filament.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $expanded = [];

        foreach ($data as $key => $value) {
            Arr::set($expanded, $key, $value);
        }

        return $expanded;
    }

    /**
     * Mutate the form data before saving to the model.
     *
     * Filament sends nested arrays (e.g. ['theme' => ['active' => 'default']]),
     * but VirtualColumn needs flat dot-notation keys ("theme.active").
     * We flatten the nested structure back, but preserve array values
     * for keys like "theme.options" (which stores an assoc array).
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $flattened = [];

        $this->flattenForVirtualColumn($data, '', $flattened);

        return $flattened;
    }

    /**
     * Recursively flatten nested arrays into dot-notation keys,
     * but stop flattening when we reach a known VirtualColumn key
     * that stores a composite value (like theme.options).
     */
    private function flattenForVirtualColumn(array $data, string $prefix, array &$result): void
    {
        // Keys that should store their value as-is (not flattened further)
        $compositeKeys = [
            ThemeManager::TENANT_THEME_OPTIONS_KEY, // 'theme.options'
        ];

        foreach ($data as $key => $value) {
            $fullKey = $prefix === '' ? $key : $prefix . '.' . $key;

            if (is_array($value) && !in_array($fullKey, $compositeKeys, true)) {
                // Check if this is a sequential (indexed) array - store as-is
                if (array_is_list($value)) {
                    $result[$fullKey] = $value;
                } else {
                    $this->flattenForVirtualColumn($value, $fullKey, $result);
                }
            } else {
                $result[$fullKey] = $value;
            }
        }
    }
}
