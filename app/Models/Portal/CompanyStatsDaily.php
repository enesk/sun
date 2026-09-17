<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

/**
 * Tageswerte eines Betriebs (#15), geschrieben von stats:aggregate-daily.
 *
 * @property int $id
 * @property int $company_id
 * @property Carbon $date
 * @property int $profile_views
 * @property int $phone_clicks
 * @property int $website_clicks
 * @property int $quote_requests
 * @property int $list_impressions
 * @property int $widget_views
 * @property int $review_replies
 * @property int|null $ranking_position
 * @property-read Company|null $company
 */
class CompanyStatsDaily extends Model
{
    use TenantConnection;

    protected $table = 'company_stats_daily';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'profile_views' => 'integer',
            'phone_clicks' => 'integer',
            'website_clicks' => 'integer',
            'quote_requests' => 'integer',
            'list_impressions' => 'integer',
            'widget_views' => 'integer',
            'review_replies' => 'integer',
            'ranking_position' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
