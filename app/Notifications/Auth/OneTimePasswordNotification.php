<?php

namespace App\Notifications\Auth;

use App\Support\Tenancy\TenantMailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Spatie\OneTimePasswords\Models\OneTimePassword;

/**
 * Einmal-Passwort-Mail im sun-Layout statt der Paketvorlage (markdown).
 * Betreff und Text nennen das Portal, auf dem die Anmeldung laeuft.
 */
class OneTimePasswordNotification extends Notification
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public OneTimePassword $oneTimePassword) {}

    public function via(object $notifiable): string|array
    {
        return 'mail';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tenant = tenant();
        $portalName = TenantMailBranding::for($tenant instanceof \App\Models\Tenant ? $tenant : null)->portalName();

        return (new MailMessage)
            ->subject('Dein Anmeldecode für '.$portalName.': '.$this->oneTimePassword->password)
            ->view('emails.auth.one-time-password', [
                'oneTimePassword' => $this->oneTimePassword,
                'mailTenant' => $tenant instanceof \App\Models\Tenant ? $tenant : null,
            ]);
    }
}
