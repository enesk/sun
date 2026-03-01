<?php

namespace App\Models\Portal;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\TenantConnection;

class NewsletterSubscriber extends Model
{
    use TenantConnection;
    protected $fillable = [
        'email',
        'subscribed_at',
        'unsubscribed_at',
        'ip_address',
    ];

    protected $casts = [
        'subscribed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->whereNull('unsubscribed_at');
    }
}
