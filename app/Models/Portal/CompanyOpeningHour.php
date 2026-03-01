<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class CompanyOpeningHour extends Model
{
    use TenantConnection;
    protected $fillable = [
        'company_id',
        'day_of_week',
        'opens_at',
        'closes_at',
        'is_closed',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_closed' => 'boolean',
    ];

    public const DAYS = [
        0 => 'Montag',
        1 => 'Dienstag',
        2 => 'Mittwoch',
        3 => 'Donnerstag',
        4 => 'Freitag',
        5 => 'Samstag',
        6 => 'Sonntag',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function getDayNameAttribute(): string
    {
        return self::DAYS[$this->day_of_week] ?? '';
    }

    public function getFormattedTimeAttribute(): string
    {
        if ($this->is_closed) {
            return 'Geschlossen';
        }

        if ($this->opens_at && $this->closes_at) {
            return substr($this->opens_at, 0, 5) . ' – ' . substr($this->closes_at, 0, 5);
        }

        return 'Keine Angabe';
    }
}
