<?php

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class QueuedVerifyEmail extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    /**
     * Link, der schon beim Absenden gebaut wurde. Im Queue-Worker gibt es
     * keinen Request, url() fiele dort auf APP_URL (zentrale Domain) zurueck —
     * wer sich auf einem Portal registriert, muss aber auf dessen Domain
     * bestaetigen, sonst fehlt dort die Anmeldung.
     */
    public function __construct(public ?string $url = null) {}

    protected function verificationUrl($notifiable)
    {
        return $this->url ?? parent::verificationUrl($notifiable);
    }

    public static function for(object $notifiable): self
    {
        $notification = new self;
        $notification->url = $notification->verificationUrl($notifiable);

        return $notification;
    }
}
