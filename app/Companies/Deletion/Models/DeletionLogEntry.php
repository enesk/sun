<?php

declare(strict_types=1);

namespace App\Companies\Deletion\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

/**
 * Eine Zeile im Loeschprotokoll (#8), Tabelle `deletion_log` je Tenant-DB.
 *
 * Append-only: `updated_at` gibt es nicht, geschrieben wird ausschliesslich
 * von {@see App\Companies\Deletion\DeletionLogger}. Die Zeile bleibt auch
 * nach dem harten Loeschen des Profils stehen — sie ist der Nachweis nach
 * Art. 17 und traegt deshalb Name und Pfad als Kopie.
 *
 * @property int $id
 * @property string $run_id
 * @property int|null $company_id
 * @property string|null $company_name
 * @property string|null $company_path
 * @property int|null $request_id
 * @property string $step
 * @property string $status
 * @property string|null $message
 * @property array<string, mixed>|null $payload
 * @property int|null $duration_ms
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $finished_at
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class DeletionLogEntry extends Model
{
    use TenantConnection;

    protected $table = 'deletion_log';

    public $timestamps = false;

    protected $fillable = [
        'run_id',
        'company_id',
        'company_name',
        'company_path',
        'request_id',
        'step',
        'status',
        'message',
        'payload',
        'duration_ms',
        'started_at',
        'finished_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'duration_ms' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
