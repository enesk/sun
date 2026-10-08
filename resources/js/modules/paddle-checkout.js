/*
 * Paddle-Checkout — ohne Inline-Skript (#21).
 *
 * Stand vorher in resources/views/payment-providers/paddle.blade.php und
 * payment-providers/paddle/payment-link.blade.php. paddle.js kommt weiter per
 * <script src> von cdn.paddle.com, die Einrichtung haengt jetzt an Markup:
 *
 *   <div data-paddle data-paddle-token="…" data-paddle-sandbox="1"></div>
 *
 * Geoeffnet wird auf das Livewire-Ereignis 'start-overlay-checkout' aus
 * App\Livewire\Checkout\* — vorher ein @script-Block mit $wire.on().
 */

let eingerichtet = false;

function konfiguration() {
    return document.querySelector('[data-paddle]');
}

function richteEin(el) {
    if (eingerichtet || typeof window.Paddle === 'undefined') {
        return false;
    }

    eingerichtet = true;

    if (el.dataset.paddleSandbox === '1') {
        window.Paddle.Environment.set('sandbox');
    }

    window.Paddle.Setup({
        token: el.dataset.paddleToken,
        checkout: {
            settings: {
                displayMode: 'overlay',
                theme: 'light',
            },
        },
        eventCallback: (data) => {
            switch (data.name) {
                case 'checkout.completed':
                    // Paddle braucht einen Moment, bis das Webhook durch ist.
                    window.setTimeout(() => {
                        window.location.href = el.dataset.paddleSuccessUrl || window.location.href;
                    }, 2000);
                    break;
                case 'checkout.closed':
                    window.location.reload();
                    break;
            }
        },
    });

    return true;
}

function oeffne(el, event) {
    el.dataset.paddleSuccessUrl = event.successUrl;

    if (!richteEin(el)) {
        return;
    }

    const kundendaten = {};

    if (event.subscriptionUuid) {
        kundendaten.subscriptionUuid = event.subscriptionUuid;
    }

    if (event.orderUuid) {
        kundendaten.orderUuid = event.orderUuid;
    }

    window.Paddle.Checkout.open({
        settings: { successUrl: event.successUrl },
        items: (event.initData?.productDetails ?? []).map((posten) => ({
            priceId: posten.paddlePriceId,
            quantity: posten.quantity,
        })),
        customData: kundendaten,
        customer: { email: event.email },
        discountId: event.initData?.paddleDiscountId ?? null,
    });
}

export function initPaddleCheckout() {
    const el = konfiguration();

    if (!el || el.dataset.paddleGebunden === '1') {
        return;
    }

    el.dataset.paddleGebunden = '1';

    // Zahlungs-Link-Seite: nur einrichten, kein Ereignis abwarten.
    if (el.dataset.paddleSetupOnly === '1') {
        richteEin(el);

        return;
    }

    document.addEventListener('livewire:initialized', () => {
        window.Livewire.on('start-overlay-checkout', (event) => {
            const nutzlast = Array.isArray(event) ? event[0] : event;

            if (nutzlast?.paymentProvider === 'paddle') {
                oeffne(el, nutzlast);
            }
        });
    });
}
