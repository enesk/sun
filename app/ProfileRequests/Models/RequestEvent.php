<?php

declare(strict_types=1);

namespace App\ProfileRequests\Models;

use App\ProfileRequests\Enums\RequestEventType;
use App\ProfileRequests\Enums\RequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Eine Zeile im Protokoll einer Anfrage (SUN-RQ, Ticket #3).
 *
 * Append-only: `$timestamps` ist aus, `created_at` setzt
 * {@see ProfileRequest::recordEvent()}. Geschrieben wird ausschliesslich
 * dort — ein Statuswechsel am Model vorbei wird vom
 * ProfileRequestObserver abgelehnt.
 *
 * @property int $id
 * @property int $request_id
 * @property RequestEventType|string $event
 * @property string $actor_type
 * @property int|null $actor_id
 * @property RequestStatus|null $from_status
 * @property RequestStatus|null $to_status
 * @property array<string, mixed>|null $payload
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class RequestEvent extends Model
{
    use CentralConnection;

    public const ACTOR_SYSTEM = 'system';

    public const ACTOR_REQUESTER = 'requester';

    public const ACTOR_USER = 'user';

    protected $table = 'request_events';

    public $timestamps = false;

    protected $fillable = [
        'request_id',
        'event',
        'actor_type',
        'actor_id',
        'from_status',
        'to_status',
        'payload',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'event' => RequestEventType::class,
            'from_status' => RequestStatus::class,
            'to_status' => RequestStatus::class,
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ProfileRequest::class, 'request_id');
    }

    /** Hat dieses Ereignis den Zustand bewegt? */
    public function isTransition(): bool
    {
        return $this->to_status !== null;
    }

    public function actorLabel(): string
    {
        return match ($this->actor_type) {
            self::ACTOR_REQUESTER => __('Anfragender'),
            self::ACTOR_USER => __('Bearbeiter'),
            default => __('System'),
        };
    }
}
