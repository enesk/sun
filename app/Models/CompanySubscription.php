<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Zuordnung einer SaasyKit-Subscription zu einem Betrieb im Tenant (#5).
 * company_id verweist in die Tenant-DB (App\Models\Portal\Company).
 * featured_city_id/featured_category_id nur beim Add-on Top-Platzierung (#6).
 */
class CompanySubscription extends Model
{
    use CentralConnection;

    protected $fillable = [
        'tenant_id',
        'company_id',
        'subscription_id',
        'featured_city_id',
        'featured_category_id',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'company_id' => 'integer',
            'subscription_id' => 'integer',
            'featured_city_id' => 'integer',
            'featured_category_id' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
