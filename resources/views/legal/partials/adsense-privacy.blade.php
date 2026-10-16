{{--
    Werbe-Abschnitt der Datenschutzerklaerung (SUN-AD-009, Tickets #9, #26, #35).

    EIN Text fuer alle Portale, bewusst nicht in den gepflegten Portaltext
    (branding.datenschutz, Tenant-DB) kopiert: dort muesste er 23-mal
    gepflegt werden, und eine Aenderung an der Ausspielung wuerde ihn
    stillschweigend falsch machen. Angehaengt wird er stattdessen beim
    Rendern der Datenschutzseite — App\Ads\Support\AdSensePrivacySection,
    aufgerufen in App\Http\Controllers\Portal\StaticPageController::datenschutz().
    Damit steht er in allen drei Themes und laeuft in sun-v2 durch dieselbe
    Abschnittszerlegung wie der gepflegte Text (Sprungmarke, Inhalts-
    verzeichnis).

    Zwei Teile, unabhaengig voneinander geschaltet
    (AdSensePrivacySection::sections()):

    * $showAdSense — Google AdSense. Nur wenn das Portal AdSense ausspielt
      (ad_settings.ads_enabled und eine wirksame Publisher-ID) und der
      gepflegte Text das Wort "AdSense" nicht selbst enthaelt. Wortlaut
      freigegeben in Ticket #26 (Rechtsstand 16.10.2026); Aenderungen daran
      nur mit erneuter Durchsicht. Zwei dort entschiedene Punkte: der
      Abschnitt bleibt ohne eigene Nummer (die Nummerierung wird je Portal
      gepflegt), und die AdSense-Cookies werden nicht namentlich aufgefuehrt
      (Google gibt Namen und Laufzeiten nicht stabil an; genannt werden
      Zwecke und Laufzeitspanne).
    * $showHtml — eigene HTML-Werbemittel (App\Ads\Providers\HtmlProvider).
      Nur wenn auf dem Portal eine laufende HTML-Belegung liegt, deren
      Werbemittel einwilligungspflichtig ist. Der Absatz sagt "erst nach
      Einwilligung" zu; dieselbe Angabe
      (config_json->third_party, Ticket #36) laesst den Rahmen warten und
      diesen Absatz erscheinen — Text und Technik haengen an einem Wert
      (docs/ad-slots.md 9.4, ProviderConfigForm::htmlLegalRule()).

    Ohne AdSense-Teil steht "Weitere Werbemittel" als h2 statt als h3, damit
    die Zerlegung in sun-v2 eine Sprungmarke und einen Eintrag im
    Inhaltsverzeichnis bekommt. Der Satz zum Widerruf verweist dann nicht
    nach oben, sondern nennt den Weg selbst.

    Nur Standard-HTML, keine Theme-Klassen: default/starter gestalten ueber
    .legal-content, sun-v2 ueber Utility-Varianten auf Kindelemente.

    Platzhalter in Klammerform werden wie im uebrigen Rechtstext von
    TenantBrandingService::resolveLegalPlaceholders() aufgeloest.
--}}
@php
    $showAdSense = $showAdSense ?? true;
    $showHtml = $showHtml ?? false;
@endphp
@if ($showAdSense)
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
@endif

@if ($showHtml)
@if ($showAdSense)
<h3>Weitere Werbemittel</h3>
@else
<h2>Weitere Werbemittel</h2>
@endif
<p>
    @if ($showAdSense)
        Neben Google AdSense schalten wir auf <strong>[PORTAL_NAME]</strong> vereinzelt Werbemittel,
    @else
        Auf <strong>[PORTAL_NAME]</strong> schalten wir vereinzelt Werbemittel,
    @endif
    die wir selbst einbinden — etwa Banner von Partnern oder Werbenetzwerken. Solche Werbemittel laufen in einem abgeschotteten Rahmen (iframe mit Sandbox), der keinen Zugriff
    auf Ihre Sitzung und auf die übrige Portalseite hat.
</p>
<p>
    Bringt ein Werbemittel eigene Skripte oder Inhalte mit, die von Servern des jeweiligen Anbieters
    geladen werden, erhält dieser Anbieter dabei Ihre IP-Adresse, Angaben zu Browser und Gerät sowie
    Datum und Uhrzeit des Aufrufs. Das geschieht erst, nachdem Sie in die Kategorie „Marketing“
    eingewilligt haben; vorher bleibt die Werbefläche leer.
</p>
<p>
    <strong>Rechtsgrundlage:</strong> Art. 6 Abs. 1 lit. a DSGVO (Einwilligung) sowie
    § 25 Abs. 1 TDDDG.
    @if ($showAdSense)
        Den Widerruf nehmen Sie wie oben beschrieben über die Cookie-Einstellungen vor.
    @else
        Den Widerruf nehmen Sie jederzeit mit Wirkung für die Zukunft vor: Öffnen Sie über den Link im
        Seitenfuß die Cookie- bzw. Datenschutz-Einstellungen und entfernen Sie die Kategorie „Marketing“.
    @endif
</p>
@endif
