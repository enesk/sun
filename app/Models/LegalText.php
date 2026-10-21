<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LegalTextKey;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Ein zentral gepflegter Rechtstext, gueltig fuer alle Portale (Ticket #17).
 *
 * Central, genau eine Zeile je {@see LegalTextKey}. Der Inhalt ist HTML und
 * enthaelt die Platzhalter `[BETREIBER_*]`, die erst beim Ausliefern je Portal
 * aufgeloest werden ({@see \App\Services\TenantBrandingService::resolveLegalPlaceholders()}).
 * Hier steht also absichtlich kein fertiger Text, sondern eine Vorlage.
 *
 * @property int $id
 * @property string $key
 * @property string|null $content
 * @property int|null $updated_by
 */
class LegalText extends Model
{
    use CentralConnection;

    protected $fillable = [
        'key',
        'content',
        'updated_by',
    ];

    public function textKey(): ?LegalTextKey
    {
        return LegalTextKey::tryFrom($this->getAttribute('key'));
    }
}
