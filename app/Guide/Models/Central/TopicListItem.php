<?php

declare(strict_types=1);

namespace App\Guide\Models\Central;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Themen-Vorlage einer Liste. `question` darf Platzhalter wie {{branch}}
 * enthalten; die Zuweisung loest sie je Tenant auf und legt daraus
 * guide_topics an (guide_topics.list_item_id).
 *
 * `outline_json` ist die vorgegebene Gliederung in der Form von
 * guide_topics.outline_json, leer ohne Ueberschriften-Vorgabe.
 *
 * @property array<int, array<string, mixed>>|null $outline_json
 */
class TopicListItem extends Model
{
    use CentralConnection;

    protected $table = 'guide_topic_list_items';

    protected $fillable = [
        'guide_topic_list_id',
        'position',
        'question',
        'question_normalized',
        'category_name',
        'outline_json',
        'notes',
        'priority',
        'refresh_interval_days',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'outline_json' => 'array',
            'priority' => 'integer',
            'refresh_interval_days' => 'integer',
        ];
    }

    public function topicList(): BelongsTo
    {
        return $this->belongsTo(TopicList::class, 'guide_topic_list_id');
    }
}
