/*
 * reCAPTCHA v2 an Livewire — ohne Inline-Skript (#21).
 *
 * Gehoert zu resources/views/livewire/auth/partials/recaptcha.blade.php. Das
 * Widget ruft die Funktion aus seinem data-callback auf; sie muss darum global
 * sein und wird hier gesetzt statt in einem Inline-Block.
 */

export function initRecaptchaBridge() {
    if (window.__recaptchaBridgeGebunden) {
        return;
    }

    window.__recaptchaBridgeGebunden = true;

    window.onRecaptchaSuccess = (token) => {
        window.Livewire?.dispatch('captcha-success', { token });
    };

    document.addEventListener('livewire:initialized', () => {
        window.Livewire.on('reset-recaptcha', () => {
            // Eine halbe Sekunde warten: sonst setzt grecaptcha zurueck,
            // bevor Livewire den Morph durch hat.
            window.setTimeout(() => window.grecaptcha?.reset(), 500);
        });
    });
}
