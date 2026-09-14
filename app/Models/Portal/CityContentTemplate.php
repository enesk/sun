<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

/**
 * Tenant-weite Vorlage fuer Introtext und FAQ der Stadtseiten (#11), eine
 * Zeile je Tenant-Datenbank. Platzhalter: CityContentResolver::PLACEHOLDERS.
 *
 * @property list<array{question: string, answer: string}>|null $faq_templates
 */
class CityContentTemplate extends Model
{
    use TenantConnection;

    protected $fillable = [
        'intro_template',
        'faq_templates',
    ];

    protected $casts = [
        'faq_templates' => 'array',
    ];

    public static function current(): ?self
    {
        return static::query()->oldest('id')->first();
    }
}
