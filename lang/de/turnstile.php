<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Cloudflare Turnstile (#4, docs/turnstile.md)
|--------------------------------------------------------------------------
|
| Nur die Texte, die ein Besucher zu sehen bekommt. Bewusst ohne Fachbegriff
| und ohne Fehlercode: dem Besucher hilft "noch einmal versuchen", die
| Diagnose steht in turnstile_verifications und im Laravel-Log.
|
| Das Projekt laeuft mit locale=en und fallback_locale=de (config/app.php),
| deshalb greifen diese Texte ohne eine englische Fassung daneben.
|
*/
return [

    // Token fehlt, success=false, timeout-or-duplicate, Hostname oder Action
    // passen nicht. Nach dieser Meldung setzt #5 das Widget zurueck.
    'failed' => 'Die Sicherheitsprüfung ist fehlgeschlagen. Bitte versuch es noch einmal.',

    // Nur bei fail_mode=closed und einem Ausfall von Siteverify.
    'unavailable' => 'Die Sicherheitsprüfung ist gerade nicht erreichbar. Bitte versuch es in ein paar Minuten noch einmal.',

    // Social Login als Registrierungsweg ist gesperrt (#6): der Callback kann
    // kein Token tragen. Steht auf /register, wohin der Besucher geleitet wird.
    'oauth_registration_blocked' => 'Mit dieser E-Mail-Adresse besteht noch kein Konto. Bitte registriere dich kurz hier — danach funktioniert die Anmeldung über den Anbieter.',

    // Hinweis unter jedem geschuetzten Formular (#11). :datenschutz ist der
    // Platz fuer den Link auf die Datenschutzerklaerung des Portals; gesetzt
    // wird er in resources/views/components/turnstile.blade.php. Bewusst kurz,
    // damit der Satz auf 360 px in zwei Zeilen passt und nichts umbricht,
    // was zusammengehoert.
    'notice' => 'Diese Seite ist durch Cloudflare Turnstile geschützt. Es gelten die :datenschutz.',
    'notice_link' => 'Datenschutzhinweise',

];
