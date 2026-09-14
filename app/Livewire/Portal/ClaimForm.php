<?php

namespace App\Livewire\Portal;

/**
 * Eingebettete Fassung des Uebernahme-Dialogs (ClaimModal) fuer die Seite
 * "Ist das dein Betrieb?" im Theme sun-v2.
 *
 * Gleiche Logik wie ClaimModal (Registrieren/Anmelden, Uebernahme-Antrag,
 * Weiterleitung zum Nachweis), nur ohne Dialog: das Szenario wird beim
 * Laden bestimmt statt beim Oeffnen.
 */
class ClaimForm extends ClaimModal
{
    public function mount(): void
    {
        $this->openModal();
    }

    public function closeModal(): void
    {
        // Eingebettet gibt es nichts zu schliessen
    }

    public function render()
    {
        return view('livewire.portal.claim-form');
    }
}
