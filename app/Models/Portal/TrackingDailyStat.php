<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class TrackingDailyStat extends Model
{
    use TenantConnection;

    protected $fillable = [
        'company_id',
        'date',
        'page_views',
        'contact_clicks_phone',
        'contact_clicks_email',
        'contact_clicks_website',
        'contact_clicks_map',
        'search_impressions',
    ];

    protected $casts = [
        'date' => 'date',
        'page_views' => 'integer',
        'contact_clicks_phone' => 'integer',
        'contact_clicks_email' => 'integer',
        'contact_clicks_website' => 'integer',
        'contact_clicks_map' => 'integer',
        'search_impressions' => 'integer',
    ];

    // ── Relationships ──

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    // ── Scopes ──

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeInPeriod($query, string $from, string $to)
    {
        return $query->whereBetween('date', [$from, $to]);
    }

    // ── Accessors ──

    public function getTotalContactClicksAttribute(): int
    {
        return $this->contact_clicks_phone
            + $this->contact_clicks_email
            + $this->contact_clicks_website
            + $this->contact_clicks_map;
    }
}
