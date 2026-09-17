<?php

namespace App\Models\Portal;

use App\Constants\CompanyEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

/**
 * Rohevent der Betriebsstatistik (#15). Geschrieben nur ueber RecordCompanyEvents.
 *
 * @property int $id
 * @property int $company_id
 * @property CompanyEventType $event_type
 * @property Carbon $occurred_at
 * @property Carbon $occurred_hour
 * @property string $session_hash
 * @property int|null $city_id
 * @property string|null $source
 * @property-read Company|null $company
 */
class CompanyEvent extends Model
{
    use TenantConnection;

    public const SOURCE_PROFILE = 'profile';

    public const SOURCE_SEARCH = 'search';

    public const SOURCE_CITY = 'city';

    public const SOURCE_CATEGORY = 'category';

    public const SOURCE_LISTING = 'listing';

    public const SOURCE_WIDGET = 'widget';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'event_type' => CompanyEventType::class,
            'occurred_at' => 'datetime',
            'occurred_hour' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
