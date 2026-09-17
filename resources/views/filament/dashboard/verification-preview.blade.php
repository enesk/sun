{{-- Vorschau eines Verifizierungsnachweises (#11); Datei nur ueber signierten Admin-Link. --}}
@php
    /** @var \App\Models\Portal\CompanyVerification $record */
    $record = $getRecord();
    $url = app(\App\Services\Premium\CompanyVerificationService::class)->documentUrl($record);
@endphp
@if($url === null)
    <p class="text-sm text-gray-500">{{ __('Die Datei wurde nach Ablauf der Aufbewahrungsfrist gelöscht.') }}</p>
@else
    <div class="space-y-3">
        @if($record->isImage())
            <img src="{{ $url }}" alt="{{ __('Nachweis') }}" class="max-h-[70vh] w-auto rounded-lg border border-gray-200">
        @else
            <iframe src="{{ $url }}" title="{{ __('Nachweis') }}" class="h-[70vh] w-full rounded-lg border border-gray-200"></iframe>
        @endif
        <a href="{{ $url }}" target="_blank" rel="noopener" class="text-sm font-medium text-primary-600 hover:underline">{{ __('In neuem Tab öffnen') }}</a>
    </div>
@endif
