{{--
    Click-to-Call ohne kaputten href: <a href="tel:+49..."> nur mit gueltiger E.164-Nummer (App\Support\PhoneNumber).
    Ist die Nummer nicht normalisierbar, erscheint sie als reiner Text (fallback-class), bei :fallback="false" gar nicht –
    fuer reine Icon-Buttons, deren Nummer an anderer Stelle schon als Text steht. Leerer Slot = sichtbare Nummer.
    Usage: <x-phone-link :number="$company->tel" class="btn-primary"><x-sun.icon name="phone" class="icon" />Anrufen</x-phone-link>
--}}
@props(['number' => null, 'country' => 'DE', 'fallback' => true, 'fallbackClass' => null])
@php
    $display = \App\Support\PhoneNumber::display($number);
    $e164 = \App\Support\PhoneNumber::toE164($number, $country);
@endphp
@if($e164)
<a href="tel:{{ $e164 }}" {{ $attributes }}>{{ $slot->isEmpty() ? $display : $slot }}</a>
@elseif($display && $fallback)
<span @class([$fallbackClass])>{{ $display }}</span>
@endif
