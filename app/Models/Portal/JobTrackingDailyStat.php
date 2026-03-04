<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobTrackingDailyStat extends Model
{
    protected $connection = 'tenant';

    protected $table = 'job_tracking_daily_stats';

    protected $fillable = [
        'job_id',
        'company_id',
        'date',
        'page_views',
        'search_impressions',
    ];

    protected $casts = [
        'date' => 'date',
        'page_views' => 'integer',
        'search_impressions' => 'integer',
    ];

    // ── Relationships ──

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    // ── Scopes ──

    public function scopeForJob($query, int $jobId)
    {
        return $query->where('job_id', $jobId);
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeInPeriod($query, $from, $to)
    {
        return $query->whereBetween('date', [$from, $to]);
    }
}
