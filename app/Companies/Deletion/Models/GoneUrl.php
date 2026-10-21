<?php

declare(strict_types=1);

namespace App\Companies\Deletion\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

/**
 * Ein Pfad, der dauerhaft 410 liefert (#8), Tabelle `gone_urls` je Tenant-DB.
 *
 * Geschrieben und geleert wird nur ueber
 * {@see App\Companies\Deletion\GoneUrlRegistry} — der haelt auch den Cache
 * aktuell, den die Middleware liest.
 *
 * @property int $id
 * @property string $path
 * @property int|null $company_id
 * @property string|null $reason
 */
class GoneUrl extends Model
{
    use TenantConnection;

    protected $table = 'gone_urls';

    protected $fillable = [
        'path',
        'company_id',
        'reason',
    ];
}
