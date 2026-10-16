<?php

declare(strict_types=1);

namespace App\Ads\Rules;

use App\Ads\Support\AdSlotCatalog;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * `slot_key` muss im Anker-Katalog stehen (SUN-AD-003, Ticket #3).
 *
 * Es gibt keinen Fremdschluessel darauf — der Katalog ist Code. Diese Rule ist
 * die Formular-Seite derselben Pruefung, die AdPlacement beim Speichern macht.
 *
 * `$implicit`, damit ein fehlender oder leerer Wert nicht stillschweigend
 * durchrutscht (Validator::presentOrRuleIsImplicit).
 */
class SlotKeyInCatalog implements ValidationRule
{
    public bool $implicit = true;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail(__('Bitte einen Werbeplatz waehlen.'));

            return;
        }

        if (! AdSlotCatalog::exists($value)) {
            $fail(__('Der Werbeplatz :key steht nicht im Katalog.', ['key' => $value]));
        }
    }
}
