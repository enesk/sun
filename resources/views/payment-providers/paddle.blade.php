{{-- Paddle-Overlay-Checkout. Verhalten in resources/js/modules/paddle-checkout.js;
     hier stehen nur die Werte, die der Browser braucht (#21). --}}
<div data-paddle
     data-paddle-token="{{ config('services.paddle.client_side_token') }}"
     @if(config('services.paddle.is_sandbox')) data-paddle-sandbox="1" @endif
     hidden></div>

@push('head')
    <script src="https://cdn.paddle.com/paddle/v2/paddle.js"></script>
@endpush
