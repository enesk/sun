<?php

declare(strict_types=1);

namespace App\ProfileRequests\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Eine Zeile des Diffs einer Aenderungsanfrage (SUN-RQ, Ticket #3).
 *
 * `old_value`/`new_value` sind JSON, weil Oeffnungszeiten und Leistungen
 * strukturiert sind. Ein Skalar (Telefonnummer, Website) liegt dann als
 * JSON-Skalar in der Spalte — die Casts geben ihn unveraendert zurueck.
 *
 * @property int $id
 * @property int $request_id
 * @property string $field
 * @property mixed $old_value
 * @property mixed $new_value
 * @property bool|null $accepted
 */
class RequestChange extends Model
{
    use CentralConnection;

    protected $table = 'request_changes';

    protected $fillable = [
        'request_id',
        'field',
        'old_value',
        'new_value',
        'accepted',
    ];

    protected function casts(): array
    {
        return [
            'old_value' => 'json',
            'new_value' => 'json',
            'accepted' => 'boolean',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ProfileRequest::class, 'request_id');
    }

    /** Noch nicht entschieden (#11). @param  Builder<self>  $query */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('accepted');
    }

    /** @param  Builder<self>  $query */
    public function scopeAccepted(Builder $query): void
    {
        $query->where('accepted', true);
    }

    /**
     * Darf dieses Feld ohne Freigabe uebernommen werden?
     * Quelle ist allein config('profile-requests.auto_apply_fields').
     */
    public function isAutoApplicable(): bool
    {
        return in_array($this->field, (array) config('profile-requests.auto_apply_fields', []), true);
    }
}
