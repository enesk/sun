<?php

namespace App\Support\MediaLibrary;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

class TenantAwarePathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        return $this->getTenantPrefix() . $media->id . '/';
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->getTenantPrefix() . $media->id . '/conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getTenantPrefix() . $media->id . '/responsive-images/';
    }

    protected function getTenantPrefix(): string
    {
        // Mit suffix_storage_path: true in config/tenancy.php wird der
        // Storage-Root automatisch pro Tenant isoliert (storage/tenant{id}/).
        // Kein manueller Tenant-Prefix nötig — die Filesystem-Isolation
        // passiert auf Bootstrapper-Ebene.
        return 'media/';
    }
}
