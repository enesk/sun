<?php

namespace App\Models\Portal;

use App\Services\Seo\StructuredDataService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Number;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

/**
 * Eintrag im Leistungskatalog eines Betriebs (#13, Feature service_catalog).
 * price_from in Cent (brutto); null bedeutet Preis auf Anfrage.
 *
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property int|null $price_from
 * @property string|null $description
 * @property int $sort_order
 * @property-read Company|null $company
 * @property-read string|null $price_from_display
 */
class CompanyService extends Model
{
    use TenantConnection;

    protected $fillable = [
        'company_id',
        'name',
        'price_from',
        'description',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_from' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Leistungen stehen als Offer im JSON-LD des Profils
        static::saved(fn (CompanyService $service) => StructuredDataService::forget($service->company_id));
        static::deleted(fn (CompanyService $service) => StructuredDataService::forget($service->company_id));
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function getPriceFromDisplayAttribute(): ?string
    {
        if ($this->price_from === null) {
            return null;
        }

        // Deutsches Format (89,00 €); money() ignoriert die Locale
        return (string) Number::currency($this->price_from / 100, in: (string) config('premium.currency', 'EUR'), locale: 'de');
    }
}
