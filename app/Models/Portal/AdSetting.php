<?php

declare(strict_types=1);

namespace App\Models\Portal;

use Stancl\Tenancy\Database\Concerns\TenantConnection;
use Illuminate\Database\Eloquent\Model;

class AdSetting extends Model
{
    use TenantConnection;

    protected $fillable = [
        'ads_txt_content',
    ];

    public static function instance(): self
    {
        return self::firstOrCreate([]);
    }
}
