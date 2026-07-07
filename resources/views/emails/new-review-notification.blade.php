<x-layouts.email>
    <x-slot name="preview">
        Neue Bewertung für {{ $review->company->name ?? 'Unbekannt' }} ({{ $review->rating }} Sterne)
    </x-slot>

    <tr>
        <td class="sm-px-6" style="border-radius: 4px; padding: 48px; font-size: 16px; color: #334155; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05)" bgcolor="#ffffff">
            <h1 class="sm-leading-8" style="margin: 0 0 24px; font-size: 24px; font-weight: 600; color: #000">
                Neue Bewertung
            </h1>

            <p style="margin: 0 0 16px; line-height: 24px">
                Es wurde eine neue Bewertung abgegeben:
            </p>

            <table style="width: 100%; border-collapse: collapse; margin-bottom: 24px;">
                <tr>
                    <td style="padding: 8px 0; font-weight: 600; color: #64748b; width: 140px;">Firma:</td>
                    <td style="padding: 8px 0;">{{ $review->company->name ?? 'Unbekannt' }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: 600; color: #64748b;">Bewertung:</td>
                    <td style="padding: 8px 0;">{{ $review->rating }} / 5 Sterne</td>
                </tr>
                @if($review->author_name)
                <tr>
                    <td style="padding: 8px 0; font-weight: 600; color: #64748b;">Verfasser:</td>
                    <td style="padding: 8px 0;">{{ $review->author_name }}</td>
                </tr>
                @endif
                @if($review->title)
                <tr>
                    <td style="padding: 8px 0; font-weight: 600; color: #64748b;">Titel:</td>
                    <td style="padding: 8px 0;">{{ $review->title }}</td>
                </tr>
                @endif
                @if($review->body)
                <tr>
                    <td style="padding: 8px 0; font-weight: 600; color: #64748b;">Text:</td>
                    <td style="padding: 8px 0;">{{ $review->body }}</td>
                </tr>
                @endif
                <tr>
                    <td style="padding: 8px 0; font-weight: 600; color: #64748b;">Tenant:</td>
                    <td style="padding: 8px 0;">{{ tenant()?->name ?? tenant()?->id ?? '-' }}</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: 600; color: #64748b;">Zeitpunkt:</td>
                    <td style="padding: 8px 0;">{{ $review->created_at->format('d.m.Y H:i') }} Uhr</td>
                </tr>
            </table>

            <div role="separator" style="background-color: #e2e8f0; height: 1px; line-height: 1px; margin: 32px 0;">&zwj;</div>
            <p style="padding-top: 12px; padding-bottom: 12px;">
                Mit freundlichen Grüßen,<br>
                Ihr {{ config('app.name') }}-System
            </p>
        </td>
    </tr>
</x-layouts.email>
