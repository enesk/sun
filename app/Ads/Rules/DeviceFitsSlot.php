<?php

declare(strict_types=1);

namespace App\Ads\Rules;

use App\Ads\Enums\AdDevice;
use App\Ads\Support\AdSlotCatalog;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Die Geraetewahl einer Belegung muss zu den Geraeten des Ankers passen
 * (SUN-AD-003, Ticket #3): eine Seitenspalte gibt es nur auf dem Desktop,
 * ein mobiler Anker nur mobil.
 */
class DeviceFitsSlot implements ValidationRule
{
    public bool $implicit = true;

    public function __construct(private readonly ?string $slotKey) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $device = $value instanceof AdDevice
            ? $value
            : AdDevice::tryFrom(is_string($value) ? $value : '');

        if ($device === null) {
            $fail(__('Bitte eine Geraeteklasse waehlen.'));

            return;
        }

        // Ohne gueltigen Anker meldet SlotKeyInCatalog den Fehler.
        if ($this->slotKey === null || ! AdSlotCatalog::exists($this->slotKey)) {
            return;
        }

        if (! AdSlotCatalog::allowsDevice($this->slotKey, $device)) {
            $fail(__('Der Werbeplatz :key laesst nur :allowed zu.', [
                'key' => $this->slotKey,
                'allowed' => AdSlotCatalog::deviceOf($this->slotKey)->label(),
            ]));
        }
    }
}
