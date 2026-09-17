<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

/**
 * Neu geschriebene Beschreibung einer Firma (profiles:rewrite-descriptions).
 */
class ProfileDescriptionRewrite extends Model
{
    use TenantConnection;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'company_id',
        'status',
        'original_description',
        'original_source',
        'generated_description',
        'model',
        'prompt_version',
        'attempts',
        'last_error',
        'generated_at',
        'applied_at',
    ];

    protected $casts = [
        'prompt_version' => 'integer',
        'attempts' => 'integer',
        'generated_at' => 'datetime',
        'applied_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
