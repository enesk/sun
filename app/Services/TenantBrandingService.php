<?php

namespace App\Services;

use App\Constants\TenantConfigConstants;
use App\Models\Tenant;
use Illuminate\Support\Facades\Storage;

class TenantBrandingService
{
    public function get(Tenant $tenant, string $key, mixed $default = null): mixed
    {
        $value = $tenant->getAttribute($key);

        if ($value === null) {
            return $default ?? (TenantConfigConstants::DEFAULTS[$key] ?? null);
        }

        return $value;
    }

    public function set(Tenant $tenant, string $key, mixed $value): void
    {
        $tenant->setAttribute($key, $value);
        $tenant->save();
    }

    public function setMany(Tenant $tenant, array $data): void
    {
        foreach ($data as $key => $value) {
            $tenant->setAttribute($key, $value);
        }

        $tenant->save();
    }

    public function getAll(Tenant $tenant): array
    {
        $result = [];

        $reflection = new \ReflectionClass(TenantConfigConstants::class);
        $constants = $reflection->getConstants();

        foreach ($constants as $name => $key) {
            if (is_string($key) && str_contains($key, '.')) {
                $result[$key] = $this->get($tenant, $key);
            }
        }

        return $result;
    }

    public function getFooterText(Tenant $tenant): string
    {
        $text = $this->get($tenant, TenantConfigConstants::FOOTER_TEXT);

        if ($text === null) {
            return '';
        }

        return str_replace(
            ['{year}', '{tenant_name}'],
            [date('Y'), $tenant->name],
            $text
        );
    }

    public function isFeatureEnabled(Tenant $tenant, string $featureKey): bool
    {
        return (bool) $this->get($tenant, $featureKey, false);
    }

    public function handleFileUpload(Tenant $tenant, string $key, $file): ?string
    {
        if (!in_array($key, TenantConfigConstants::FILE_FIELDS)) {
            return null;
        }

        // Delete old file if exists
        $oldPath = $this->get($tenant, $key);
        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        // Store new file in tenant-isolated directory
        $path = $file->store("tenants/{$tenant->uuid}/branding", 'public');

        $this->set($tenant, $key, $path);

        return $path;
    }

    public function deleteFile(Tenant $tenant, string $key): void
    {
        if (!in_array($key, TenantConfigConstants::FILE_FIELDS)) {
            return;
        }

        $path = $this->get($tenant, $key);
        if ($path) {
            Storage::disk('public')->delete($path);
            $this->set($tenant, $key, null);
        }
    }
}
