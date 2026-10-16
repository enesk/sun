{{--
    Datenschutz-Abschnitt "Google AdSense" (SUN-AD-009, Ticket #9).

    EIN Text fuer alle Portale, bewusst nicht in den gepflegten Portaltext
    (branding.datenschutz, Tenant-DB) kopiert: dort muesste er 23-mal
    gepflegt werden, und eine Aenderung an der Ausspielung wuerde ihn
    stillschweigend falsch machen. Angehaengt wird er stattdessen beim
    Rendern der Datenschutzseite — App\Ads\Support\AdSensePrivacySection,
    aufgerufen in App\Http\Controllers\Portal\StaticPageController::datenschutz().
    Damit steht er in allen drei Themes und laeuft in sun-v2 durch dieselbe
    Abschnittszerlegung wie der gepflegte Text (Sprungmarke, Inhalts-
    verzeichnis).

    Er erscheint nur, wenn das Portal Werbung tatsaechlich ausspielt
    (ad_settings.ads_enabled und eine wirksame Publisher-ID) — ein Portal
    ohne Werbung soll keinen Abschnitt ueber Werbung haben.

    Nur Standard-HTML, keine Theme-Klassen: default/starter gestalten ueber
    .legal-content, sun-v2 ueber Utility-Varianten auf Kindelemente.

    Platzhalter in Klammerform werden wie im uebrigen Rechtstext von
    TenantBrandingService::resolveLegalPlaceholders() aufgeloest.

    Nicht abgedeckt: eigene HTML-Werbemittel (App\Ads\Providers\HtmlProvider).
    Der Abschnitt nennt nur AdSense; bis er um einen freigegebenen Absatz zu
    Drittanbieter-Werbemitteln ergaenzt ist, gilt die Auslieferungsregel aus
    Ticket #29 — nachzulesen in docs/ad-slots.md 9.4 und im Panel an der
    Provider-Auswahl (ProviderConfigForm::htmlLegalRule()).

    Wortlaut freigegeben in Ticket #26 (Durchsicht mit Datenschutz-Fokus,
    Rechtsstand 16.10.2026). Zwei dort entschiedene Punkte: der Abschnitt
    bleibt ohne eigene Nummer (die Nummerierung wird je Portal gepflegt),
    und die AdSense-Cookies werden nicht namentlich aufgefuehrt (Google gibt
    Namen und Laufzeiten nicht stabil an; genannt werden Zwecke und
    Laufzeitspanne). Aenderungen am Wortlaut nur mit erneuter Durchsicht.
--}}
<h2>Werbung mit Google AdSense</h2>
<p>
    Auf <strong>[PORTAL_NAME]</strong> schalten wir Werbung über Google AdSense, einen Dienst der
    Google Ireland Limited, Gordon House, Barrow Street, Dublin 4, Irland („Google“). Mit den
    Werbeeinnahmen finanzieren wir den Betrieb des Portals und die kostenlose Nutzung für
    Besucher und eingetragene Betriebe.
</p>

<h3>Nur mit Ihrer Einwilligung</h3>
<p>
    Werbung wird erst geladen, <strong>nachdem</strong> Sie im Cookie-Hinweis in die Kategorie
    „Marketing“ eingewilligt haben. Solange keine Einwilligung vorliegt oder Sie sie abgelehnt
    haben, wird kein Werbe-Skript geladen, und es werden keine Daten an Google übertragen —
    auch Ihre IP-Adresse nicht. Die Werbeflächen bleiben in diesem Fall leer.
</p>
<p>
    <strong>Rechtsgrundlage:</strong> Art. 6 Abs. 1 lit. a DSGVO (Einwilligung) sowie
    § 25 Abs. 1 TDDDG für das Speichern von und den Zugriff auf Informationen in Ihrem Endgerät.
</p>

<h3>Welche Daten verarbeitet werden</h3>
<p>
    Nach Ihrer Einwilligung lädt Ihr Browser Werbe-Inhalte direkt von Google-Servern. Dabei
    verarbeitet Google:
</p>
<ul>
    <li>Ihre IP-Adresse,</li>
    <li>Angaben zu Ihrem Browser, Betriebssystem und Gerät,</li>
    <li>die aufgerufene Seite auf [PORTAL_NAME] und die zuvor besuchte Seite (Referrer),</li>
    <li>Datum und Uhrzeit des Aufrufs,</li>
    <li>Kennungen aus Cookies und vergleichbaren Technologien, mit denen Google Ihr Gerät
        wiedererkennt, Werbung auswählt, deren Sichtbarkeit misst und Klickbetrug erkennt.</li>
</ul>
<p>
    Google kann diese Daten nutzen, um Ihnen interessenbezogene Werbung anzuzeigen, und sie mit
    weiteren Daten zusammenführen, die Google über Sie vorliegen — insbesondere, wenn Sie in
    einem Google-Konto angemeldet sind. Wir selbst erhalten von Google keine personenbezogenen
    Daten, sondern nur zusammengefasste Auswertungen über Einnahmen und Anzeigenabrufe.
</p>
<p>
    <strong>Verantwortlichkeit:</strong> Für die Verarbeitung durch Google ist Google eigener
    Verantwortlicher. Für das Einholen Ihrer Einwilligung und das Einbinden der Werbeflächen sind
    wir verantwortlich.
</p>

<h3>Übermittlung in die USA</h3>
<p>
    Google verarbeitet Daten auch in den USA. Google LLC ist nach dem EU-US Data Privacy Framework
    zertifiziert; Grundlage der Übermittlung ist der Angemessenheitsbeschluss der Europäischen
    Kommission vom 10. Juli 2023, ergänzend die Standardvertragsklauseln der Kommission. Trotz
    dieser Garantien lässt sich nicht ausschließen, dass US-Behörden auf die Daten zugreifen.
</p>

<h3>Speicherdauer</h3>
<p>
    Die Cookies von Google AdSense haben unterschiedliche Laufzeiten; sie liegen in der Regel
    zwischen wenigen Monaten und zwei Jahren. Ihre Einwilligungs-Entscheidung speichern wir
    selbst für 12 Monate, damit wir Sie nicht bei jedem Besuch erneut fragen müssen.
</p>

<h3>Einwilligung widerrufen</h3>
<p>
    Sie können Ihre Einwilligung jederzeit mit Wirkung für die Zukunft widerrufen: Öffnen Sie dazu
    über den Link im Seitenfuß die Cookie- bzw. Datenschutz-Einstellungen und entfernen Sie die
    Kategorie „Marketing“. Nach dem Widerruf wird beim nächsten Seitenaufruf keine Werbung mehr geladen.
    Unabhängig davon können Sie personalisierte Werbung bei Google in Ihren
    <a href="https://adssettings.google.com/" target="_blank" rel="noreferrer noopener">Google-Anzeigeneinstellungen</a>
    deaktivieren und in Ihrem Browser Cookies löschen oder blockieren.
</p>
<p>
    Weitere Informationen zur Datenverarbeitung durch Google finden Sie in der
    <a href="https://policies.google.com/privacy?hl=de" target="_blank" rel="noreferrer noopener">Datenschutzerklärung von Google</a>
    und unter
    <a href="https://policies.google.com/technologies/ads?hl=de" target="_blank" rel="noreferrer noopener">„Wie Google Daten von Partner-Websites verwendet“</a>.
</p>
