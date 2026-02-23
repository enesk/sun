<?php

namespace App\Filament\Admin\Resources\Tenants\Pages;

use App\Events\Tenant\TenantCreated;
use App\Filament\Admin\Resources\Tenants\TenantResource;
use App\Themes\ThemeManager;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['uuid'] = (string) Str::uuid();

        // Flatten nested arrays from Filament form into dot-notation
        // keys for Stancl's VirtualColumn storage
        $flattened = [];
        $this->flattenForVirtualColumn($data, '', $flattened);

        return $flattened;
    }

    /**
     * Override to bypass Laravel's fill() which mishandles dot-notation keys.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $record = new ($this->getModel());

        foreach ($data as $key => $value) {
            $record->{$key} = $value;
        }

        $record->save();

        return $record;
    }

    protected function afterCreate()
    {
        TenantCreated::dispatch($this->record, auth()->user());
    }

    private function flattenForVirtualColumn(array $data, string $prefix, array &$result): void
    {
        $compositeKeys = [
            ThemeManager::TENANT_THEME_OPTIONS_KEY,
        ];

        foreach ($data as $key => $value) {
            $fullKey = $prefix === '' ? $key : $prefix . '.' . $key;

            if (is_array($value) && !in_array($fullKey, $compositeKeys, true)) {
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
