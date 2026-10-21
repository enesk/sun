<?php

declare(strict_types=1);

namespace App\ProfileRequests\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Tagesdigest der Anfragen-Inbox, 08:00 an Enes (SUN-RQ #9).
 *
 * Die einzige Mail des Vorhabens, die **nicht** an einen Betrieb geht —
 * deshalb keine Unterklasse von {@see RequestMail}: kein `[REQ-…]` im
 * Betreff, keine Antwortadresse auf das Sammelpostfach, kein Portal-Branding
 * (die Zeilen stammen aus allen Portalen), und Du-Form statt Sie-Form.
 *
 * Sie beantwortet eine Frage: Was hat die Automatik gestern getan, und was
 * liegt noch bei mir? Deshalb vier Bloecke in genau dieser Reihenfolge und
 * keine Zahlen ohne Vorgang dahinter. Gibt es in allen vier nichts, wird die
 * Mail nicht versendet (das entscheidet
 * {@see \App\Console\Commands\RequestsDailyDigest}).
 *
 * @param  array<string, list<array{id: int, titel: string, zeile: string, url: string}>>  $gruppen
 */
class DailyDigestMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Carbon $tag,
        public array $gruppen,
        public bool $autoDisabled = false,
    ) {}

    public function envelope(): Envelope
    {
        $erledigt = count($this->gruppen['erledigt'] ?? []);
        $offen = count($this->gruppen['freigabe'] ?? []);

        return new Envelope(
            subject: sprintf(
                'Anfragen-Inbox %s — %d automatisch erledigt, %d zur Freigabe',
                $this->tag->format('d.m.Y'),
                $erledigt,
                $offen,
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.profile-requests.daily-digest',
            with: [
                'tag' => $this->tag,
                'gruppen' => $this->gruppen,
                'autoDisabled' => $this->autoDisabled,
            ],
        );
    }
}
