<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Anschrift und Kontaktdaten des Netzbetreibers (#69).
 *
 * Alle Portale dieses Netzes werden vom selben Betreiber gefuehrt; die
 * Anschrift im Impressum ist deshalb fuer jeden Tenant dieselbe. Bis #69 stand
 * sie nur als private Konstantengruppe im CreateTenantCommand und galt damit
 * ausschliesslich fuer neu angelegte Tenants — die Bestandstenants blieben
 * ohne Anschrift zurueck.
 *
 * Diese Klasse ist die einzige Stelle, an der die Betreiberanschrift steht.
 * Sie wird von CreateTenantCommand (Neuanlage) und TenantAddressSeedCommand
 * (Bestand) gelesen. Wird die Anschrift des Betreibers je geaendert, aendert
 * sie sich hier — und die Bestandsdaten werden mit dem Kommando nachgezogen.
 *
 * Wichtig: das sind Vorgabewerte fuer den Betreiber selbst, keine geratenen
 * Angaben je Portal. Ein Tenant, der eine eigene, abweichende Anschrift
 * gepflegt hat, wird davon nie ueberschrieben.
 */
final class TenantOperatorDefaults
{
    public const ADDRESS_LINE_1 = 'Karlsruherstr. 31';

    public const ZIP = '76437';

    public const CITY = 'Rastatt';

    public const COUNTRY_CODE = 'DE';

    public const PHONE = '+4915561476133';

    /**
     * Kontakt-E-Mail des Betreibers (#78). Netzweit dieselbe Adresse — sie
     * stand bereits fest im CreateTenantCommand und gilt damit fuer jeden neu
     * angelegten Tenant; die Bestandstenants ziehen sie mit
     * tenants:contact:seed nach.
     */
    public const EMAIL = 'info@widimedia.com';

    /**
     * Redaktionell verantwortliche Person nach Paragraf 18 Abs. 2 MStV (#78).
     *
     * Das ist keine geratene Angabe: derselbe Name stand bis #48 fest im
     * Impressumstext jedes Portals und wurde von tenants:legal:depersonalize
     * dort nur durch den Platzhalter [VERANTWORTLICH_NAME] ersetzt, ohne dass
     * er als Feld hinterlegt wurde. Hier steht er wieder — jetzt an einer
     * einzigen Stelle statt in zwanzig Rechtstexten.
     */
    public const RESPONSIBLE_NAME = 'Rolland Szalai';
}
