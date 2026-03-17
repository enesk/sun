<?php

declare(strict_types=1);

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class AdSlot extends Model
{
    use HasUlids, TenantConnection;

    protected $attributes = [
        'device_visibility' => '["desktop","tablet","mobile"]',
    ];

    protected $fillable = [
        'name',
        'position',
        'code',
        'is_active',
        'sort_order',
        'device_visibility',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'device_visibility' => 'array',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForPosition(Builder $query, string $position): Builder
    {
        return $query->where('position', $position);
    }

    public function scopeSorted(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }
}
