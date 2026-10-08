<x-layouts.app>

{{-- Zahlungs-Link: Paddle nur einrichten, das Overlay oeffnet der Link selbst.
     Verhalten in resources/js/modules/paddle-checkout.js (#21). --}}
<div data-paddle
     data-paddle-setup-only="1"
     data-paddle-token="{{ config('services.paddle.client_side_token') }}"
     @if(config('services.paddle.is_sandbox')) data-paddle-sandbox="1" @endif
     hidden></div>

@push('head')
    <script src="https://cdn.paddle.com/paddle/v2/paddle.js"></script>
@endpush

</x-layouts.app>
