<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Tiefenverteidigung (#8, config/antispam.php)
|--------------------------------------------------------------------------
|
| Nur die Texte, die ein Besucher zu sehen bekommt. Wie bei lang/de/turnstile.php
| bewusst ohne Fachbegriff und ohne Code — die Diagnose steht in
| turnstile_verifications.
|
| Honeypot und Mindest-Ausfuellzeit werden im Regelfall STILL abgewiesen
| (App\AntiSpam\SpamGuard), da sieht niemand einen Text. `rejected` und
| `too_fast` gelten nur, wenn ein Formular die Rules direkt an die Validierung
| haengt und den Treffer sichtbar machen will.
|
| Das Projekt laeuft mit locale=en und fallback_locale=de (config/app.php),
| deshalb greifen diese Texte ohne eine englische Fassung daneben.
|
*/
return [

    // Honeypot-Treffer, falls sichtbar gemeldet.
    'rejected' => 'Deine Eingabe konnte nicht verarbeitet werden. Bitte versuch es noch einmal.',

    // Mindest-Ausfuellzeit, falls sichtbar gemeldet.
    'too_fast' => 'Das ging zu schnell. Bitte sieh das Formular noch einmal durch und schick es dann ab.',

    // Wegwerf-E-Mail-Sperrliste. Wortlaut vom Ticket vorgegeben.
    'disposable_email' => 'Bitte verwende eine dauerhafte E-Mail-Adresse.',

    // Rate-Limit. Die Minutenangabe kommt aus dem Retry-After-Header.
    'throttled' => '{1} Zu viele Versuche. Bitte versuch es in einer Minute noch einmal.|[2,*] Zu viele Versuche. Bitte versuch es in :minuten Minuten noch einmal.',

    // Honeypot-Beschriftung fuer Screenreader: das Feld liegt off-screen, ist
    // aber im Baum. Ohne Label liest ein Screenreader ein namenloses Eingabefeld
    // vor; mit diesem Satz weiss die Person, dass sie es ueberspringen kann.
    'honeypot_label' => 'Dieses Feld bitte leer lassen',

];
