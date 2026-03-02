<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class TrackingEvent extends Model
{
    use TenantConnection;

    public $timestamps = false;

    const TYPE_PAGE_VIEW = 'page_view';
    const TYPE_CONTACT_CLICK = 'contact_click';
    const TYPE_SEARCH_IMPRESSION = 'search_impression';

    const CONTACT_PHONE = 'phone';
    const CONTACT_EMAIL = 'email';
    const CONTACT_WEBSITE = 'website';
    const CONTACT_MAP = 'map';

    protected $fillable = [
        'company_id',
        'event_type',
        'contact_type',
        'search_query',
        'user_id',
        'referrer',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    // ── Relationships ──

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    // ── Scopes ──

    public function scopePageViews($query)
    {
        return $query->where('event_type', self::TYPE_PAGE_VIEW);
    }

    public function scopeContactClicks($query)
    {
        return $query->where('event_type', self::TYPE_CONTACT_CLICK);
    }

    public function scopeSearchImpressions($query)
    {
        return $query->where('event_type', self::TYPE_SEARCH_IMPRESSION);
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeInPeriod($query, string $from, string $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }
}
