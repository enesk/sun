<?php

declare(strict_types=1);

namespace App\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

/**
 * Redaktionelle Einstellungen des Mandanten. Genau eine Zeile je Tenant-DB.
 */
class TenantContentSetting extends Model
{
    use TenantConnection;

    protected $fillable = [
        'is_active',
        'activated_at',
        'auto_publish_threshold',
        'is_ymyl',
        'tone',
        'preferred_states_json',
        'author_name',
        'author_bio',
        'organization_same_as_json',
        'author_same_as_json',
        'brand_colors_json',
        'gsc_property',
        'gsc_check_status',
        'gsc_checked_at',
        'gsc_check_detail',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'activated_at' => 'datetime',
            'auto_publish_threshold' => 'integer',
            'is_ymyl' => 'boolean',
            'preferred_states_json' => 'array',
            'organization_same_as_json' => 'array',
            'author_same_as_json' => 'array',
            'brand_colors_json' => 'array',
            'gsc_checked_at' => 'datetime',
        ];
    }

    /**
     * Einstellungen des aktuellen Tenants; legt beim ersten Zugriff die
     * Zeile mit den Spaltenvorgaben an.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }
}
