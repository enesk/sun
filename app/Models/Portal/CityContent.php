<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

/**
 * Stadt-Overrides: Meta-Title/-Description (#10) sowie Introtext, Stadtteile
 * und FAQ des Local Hubs (#11). Aufgeloest gegen die Tenant-Vorlage von
 * App\Services\Content\CityContentResolver.
 *
 * @property list<string>|null $districts
 * @property list<array{question: string, answer: string}>|null $faqs
 */
class CityContent extends Model
{
    use TenantConnection;

    protected $fillable = [
        'city_id',
        'intro_text',
        'districts',
        'faqs',
        'is_published',
        'meta_title',
        'meta_description',
        'is_generated',
        'generated_at',
    ];

    protected $casts = [
        'districts' => 'array',
        'faqs' => 'array',
        'is_published' => 'boolean',
        'is_generated' => 'boolean',
        'generated_at' => 'datetime',
    ];

    protected $attributes = [
        'is_published' => true,
    ];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
