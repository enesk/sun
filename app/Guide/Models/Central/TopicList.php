<?php

declare(strict_types=1);

namespace App\Guide\Models\Central;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Importierte Themenliste (#6). Liegt central, weil dieselbe Liste mehreren
 * Portalen zugewiesen wird.
 */
class TopicList extends Model
{
    use CentralConnection;

    protected $table = 'guide_topic_lists';

    protected $fillable = [
        'name',
        'branch',
        'source',
        'description',
        'created_by',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(TopicListItem::class, 'guide_topic_list_id')->orderBy('position');
    }

    /**
     * Portale, denen die Liste zugewiesen ist (TopicListAssigner).
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'guide_topic_list_tenant', 'guide_topic_list_id', 'tenant_id')
            ->withPivot('last_assigned_at')
            ->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
