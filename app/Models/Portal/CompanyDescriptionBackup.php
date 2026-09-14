<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

/**
 * Originaltext einer von seo:clean-ai-footprints bereinigten Spalte (#2).
 */
class CompanyDescriptionBackup extends Model
{
    use TenantConnection;

    public const UPDATED_AT = null;

    protected $fillable = [
        'company_id',
        'column_name',
        'original_description',
        'cleaned_description',
        'matched_pattern',
        'restored_at',
    ];

    protected $casts = [
        'restored_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
