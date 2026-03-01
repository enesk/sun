<x-layouts.email>
    <x-slot name="preview">
        Bestätigen Sie Ihre E-Mail-Adresse bei {{ config('app.name') }}
    </x-slot>

    <tr>
        <td class="sm-px-6" style="border-radius: 4px; padding: 48px; font-size: 16px; color: #334155; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05)" bgcolor="#ffffff">
            <h1 class="sm-leading-8" style="margin: 0 0 24px; font-size: 24px; font-weight: 600; color: #000">
                E-Mail-Adresse bestätigen
            </h1>
            <p style="margin: 0; line-height: 24px">
                Vielen Dank für Ihre Registrierung bei {{ config('app.name') }}! Bitte bestätigen Sie Ihre E-Mail-Adresse, indem Sie auf den folgenden Button klicken:
            </p>

            <div style="text-align: center;">
                <a href="{{ $url }}" style="margin-top: 24px; margin-bottom: 24px; display: inline-block; border-radius: 16px; background-color: {{config('app.email_color_tint')}}; padding: 12px 32px; font-size: 18px; color: #fff; text-decoration-line: none; font-weight: 600;">
                    E-Mail-Adresse bestätigen
                </a>
            </div>

            <div role="separator" style="background-color: #e2e8f0; height: 1px; line-height: 1px; margin: 32px 0;">&zwj;</div>

            <p style="font-size: 14px; color: #64748b;">
                Falls der Button nicht funktioniert, kopieren Sie den folgenden Link in Ihren Browser: <a href="{{ $url }}">
                    {{ $url }}
                </a>
            </p>
        </td>
    </tr>
</x-layouts.email>
