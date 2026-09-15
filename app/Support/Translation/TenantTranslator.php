<?php

namespace App\Support\Translation;

use App\Support\Tenancy\TenantTerms;
use Illuminate\Translation\Translator;

/**
 * Setzt die Branchenbegriffe des Tenants (#5) in jeden Text ein, der ueber
 * __(), trans() oder trans_choice() laeuft — Views uebergeben sie nie selbst.
 *
 * Explizit uebergebene Replacements haben Vorrang. :Branche/:BRANCHE und der
 * laengste Treffer (:branche_plural vor :branche) kommen aus dem strtr() der
 * Elternklasse. Die Begriffe werden bei jedem Aufruf frisch gelesen, damit ein
 * Tenant-Wechsel im selben Prozess (Queue, Tests) keine alten Werte liefert.
 */
class TenantTranslator extends Translator
{
    protected function makeReplacements($line, array $replace)
    {
        $terms = tenancy()->initialized
            ? (array) tenant(TenantTerms::ATTRIBUTE)
            : TenantTerms::defaults();

        return parent::makeReplacements($line, $replace + $terms);
    }
}
