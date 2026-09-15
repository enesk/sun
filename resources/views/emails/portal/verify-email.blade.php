{{--
    E-Mail-Bestaetigung auf einem Portal (App\Mail\User\VerifyEmail im Tenant-Kontext, #11).
    Alle Texte aus portal.mail.*; ohne Tenant gilt emails.user.verify-email.
--}}
<x-layouts.email>
    <x-slot name="preview">
        {{ __('portal.mail.verify_email.preview') }}
    </x-slot>

    <tr>
        <td class="sm-px-6" style="border-radius: 4px; padding: 48px; font-size: 16px; color: #334155; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05)" bgcolor="#ffffff">
            <h1 class="sm-leading-8" style="margin: 0 0 24px; font-size: 24px; font-weight: 600; color: #000">
                {{ __('portal.mail.verify_email.heading') }}
            </h1>
            <p style="margin: 0 0 16px; line-height: 24px">
                {{ filled($name) ? __('portal.mail.greeting', ['name' => $name]) : __('portal.mail.greeting_anonymous') }}
            </p>
            <p style="margin: 0; line-height: 24px">
                {{ __('portal.mail.verify_email.intro') }}
            </p>

            <div style="text-align: center;">
                <a href="{{ $url }}" style="margin-top: 24px; margin-bottom: 24px; display: inline-block; border-radius: 16px; background-color: {{config('app.email_color_tint')}}; padding: 12px 32px; font-size: 18px; color: #fff; text-decoration-line: none; font-weight: 600;">
                    {{ __('portal.mail.verify_email.button') }}
                </a>
            </div>

            <p style="margin: 0 0 16px; line-height: 24px">
                {{ __('portal.mail.verify_email.outro') }}
            </p>
            <p style="margin: 0; line-height: 24px">
                {{ __('portal.mail.sign_off') }}<br>
                {{ __('portal.mail.sign_off_name') }}
            </p>

            <div role="separator" style="background-color: #e2e8f0; height: 1px; line-height: 1px; margin: 32px 0;">&zwj;</div>

            <p style="font-size: 14px; color: #64748b;">
                {{ __('portal.mail.verify_email.link_hint') }} <a href="{{ $url }}">
                    {{ $url }}
                </a>
            </p>
        </td>
    </tr>
</x-layouts.email>
