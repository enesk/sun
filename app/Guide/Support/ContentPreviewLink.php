<?php

declare(strict_types=1);

namespace App\Guide\Support;

/**
 * Gemeinsame Konstanten der signierten Vorschau-Adressen (#20). Die Adressen
 * selbst baut seit dem Rueckbau der Entwurfsvorschau (#23) GuidePreviewLink.
 *
 * Die Vorschau muss den Artikel im echten Frontend-Template des Mandanten
 * zeigen (#17) — sie liegt also auf der Portaldomain, nicht auf der
 * Zentraldomain des Content-Panels. Damit steht die Sitzung des
 * `content`-Guards dort nicht zur Verfuegung: die 24 Portale sind eigene
 * Domains, das Sitzungscookie von /content reicht nicht hinueber.
 *
 * Die Adresse traegt deshalb den pruefenden Administrator als Parameter
 * `pruefer` mit und ist signiert. Erzeugen kann sie nur, wer im Panel
 * angemeldet ist; die Middleware auf der Portalseite prueft Signatur,
 * Ablauf, Aktivstatus des Accounts und dessen Portalzuordnung erneut.
 * Ohne gueltige Signatur ist die Seite nicht erreichbar.
 */
final class ContentPreviewLink
{
    public const USER_PARAM = 'pruefer';

    /**
     * Gueltigkeit der Adresse in Minuten. Kurz genug, dass ein
     * weitergegebener Link nicht dauerhaft traegt, lang genug fuer eine
     * Pruefsitzung.
     */
    public const TTL_MINUTES = 60;
}
