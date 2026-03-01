<x-layouts.email>
    <x-slot name="preview">
        Ihre Kündigung bei {{ config('app.name') }} wurde bestätigt
    </x-slot>

    <tr>
        <td class="sm-px-6" style="border-radius: 4px; padding: 48px; font-size: 16px; color: #334155; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05)" bgcolor="#ffffff">
            <h1 class="sm-leading-8" style="margin: 0 0 24px; font-size: 24px; font-weight: 600; color: #000">
                Hallo {{ $subscription->user->name }},
            </h1>
            <p style="margin: 0; line-height: 24px">
                Wir bedauern, dass Sie Ihr Abonnement gekündigt haben. Ihre Premium-Funktionen bleiben bis zum Ende des aktuellen Abrechnungszeitraums aktiv.
            </p>

            <p style="margin-top: 16px; padding-top: 12px; padding-bottom: 12px; line-height: 24px;">
                Wir würden uns freuen, wenn Sie uns mitteilen, was wir besser machen können:
                <a href="mailto:{{ config('app.support_email') }}">
                    {{ config('app.support_email') }}
                </a>
            </p>

            <p style="padding-top: 12px; padding-bottom: 12px; line-height: 24px;">
                Sie können Ihr Abonnement jederzeit in Ihrem Dashboard wieder aktivieren.
            </p>

            <p style="padding-top: 12px; padding-bottom: 12px; line-height: 24px;">
                Vielen Dank, dass Sie unser Portal genutzt haben. Wir hoffen, Sie bald wiederzusehen!
            </p>

            <p style="padding-top: 12px; padding-bottom: 12px;">
                Mit freundlichen Grüßen,<br>
                Ihr {{ config('app.name') }}-Team
            </p>
        </td>
    </tr>

</x-layouts.email>
