<?php

declare(strict_types=1);

namespace App\ProfileRequests\Models;

use App\ProfileRequests\Ingestion\MailOutcome;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Eine gelesene Mail des Sammelpostfachs (SUN-RQ, Ticket #5).
 *
 * Der Dedupe-Schluessel ist {@see self::hashMessageId()} auf der Message-ID;
 * die Spalte traegt einen Unique-Index. Geschrieben wird genau einmal je
 * Mail, bevor Anfrage oder Ereignis entstehen — so bleibt eine zweite
 * Zustellung auch dann draussen, wenn der Lauf danach abbricht.
 *
 * @property int $id
 * @property string $message_id_hash
 * @property string|null $message_id
 * @property MailOutcome $outcome
 * @property string|null $reason
 * @property int|null $request_id
 * @property string|null $from_email
 * @property string|null $recipient
 * @property string|null $subject
 * @property \Illuminate\Support\Carbon|null $mail_date
 */
class RequestMailLog extends Model
{
    use CentralConnection;

    protected $table = 'request_mail_log';

    protected $fillable = [
        'message_id_hash',
        'message_id',
        'outcome',
        'reason',
        'request_id',
        'from_email',
        'recipient',
        'subject',
        'mail_date',
    ];

    protected function casts(): array
    {
        return [
            'outcome' => MailOutcome::class,
            'mail_date' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ProfileRequest::class, 'request_id');
    }

    public static function hashMessageId(string $messageId): string
    {
        return hash('sha256', trim($messageId));
    }

    /** Wurde diese Mail schon einmal verarbeitet? */
    public static function alreadySeen(string $messageId): bool
    {
        return self::query()
            ->where('message_id_hash', self::hashMessageId($messageId))
            ->exists();
    }

    /** Die Anfrage, zu der eine fruehere Mail dieses Threads gehoerte. */
    public static function requestIdForMessageId(string $messageId): ?int
    {
        $row = self::query()
            ->where('message_id_hash', self::hashMessageId($messageId))
            ->whereNotNull('request_id')
            ->first();

        return $row === null ? null : (int) $row->request_id;
    }
}
