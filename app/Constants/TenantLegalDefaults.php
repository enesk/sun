<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * Standard-Rechtstexte fuer neu angelegte Tenants (#48).
 *
 * Der Wortlaut enthaelt ausschliesslich Platzhalter in Klammerform
 * ([BETREIBER_NAME], [BETREIBER_STRASSE], ...); aufgeloest werden sie erst
 * beim Rendern in TenantBrandingService::resolveLegalPlaceholders().
 * Keine festen Betreiberangaben im Text.
 *
 * Genutzt von CreateTenantCommand (Neuanlage) und dem Nachzug
 * TenantLegalSeedCommand (tenants:legal:seed, #56).
 */
class TenantLegalDefaults
{
    public const IMPRESSUM = <<<'IMPRESSUM_HTML'
<h1>Impressum</h1>

<h2>Angaben gemäß § 5 DDG</h2>
<p>
    <strong>[BETREIBER_NAME]</strong><br />
    [BETREIBER_STRASSE]<br />
    [BETREIBER_PLZ] [BETREIBER_ORT]<br />
    Deutschland
</p>

<h2>Kontakt</h2>
<p>
    Telefon: [BETREIBER_TELEFON]<br />
    E-Mail: <a href="mailto:[BETREIBER_EMAIL]">[BETREIBER_EMAIL]</a>
</p>

<h2>Redaktionell verantwortlich gemäß § 18 Abs. 2 MStV</h2>
<p>
    <strong>[VERANTWORTLICH_NAME]</strong><br />
    [BETREIBER_STRASSE]<br />
    [BETREIBER_PLZ] [BETREIBER_ORT]
</p>

<h2>Hinweis zu KI-gestützten Inhalten</h2>
<p>
    Die Ratgeber-Artikel auf <strong>[PORTAL_NAME]</strong> werden mit Unterstützung von KI
    erstellt und vor der Veröffentlichung redaktionell geprüft. Verantwortlich für alle
    veröffentlichten Inhalte bleibt [BETREIBER_NAME]. Wie unsere Artikel entstehen, beschreiben
    wir unter <a href="/ratgeber/redaktion">So arbeitet unsere Redaktion</a>.
</p>

<h2>EU-Streitschlichtung</h2>
<p>
    Die Europäische Kommission stellt eine Plattform zur Online-Streitbeilegung (OS) bereit:
    <a href="https://ec.europa.eu/consumers/odr/" target="_blank" rel="noreferrer noopener">https://ec.europa.eu/consumers/odr/</a>
</p>
<p>
    Unsere E-Mail-Adresse finden Sie oben im Impressum.
</p>

<h2>Verbraucherstreitbeilegung / Universalschlichtungsstelle</h2>
<p>
    Wir sind nicht bereit oder verpflichtet, an Streitbeilegungsverfahren vor einer
    Verbraucherschlichtungsstelle teilzunehmen.
</p>

<h2>Haftung für Inhalte</h2>
<p>
    Als Diensteanbieter sind wir gemäß § 7 Abs. 1 DDG für eigene Inhalte auf diesen Seiten
    nach den allgemeinen Gesetzen verantwortlich. Nach §§ 8 bis 10 DDG sind wir als
    Diensteanbieter jedoch nicht verpflichtet, übermittelte oder gespeicherte fremde
    Informationen zu überwachen oder nach Umständen zu forschen, die auf eine rechtswidrige
    Tätigkeit hinweisen.
</p>
<p>
    Verpflichtungen zur Entfernung oder Sperrung der Nutzung von Informationen nach den
    allgemeinen Gesetzen bleiben hiervon unberührt. Eine diesbezügliche Haftung ist jedoch
    erst ab dem Zeitpunkt der Kenntnis einer konkreten Rechtsverletzung möglich. Bei
    Bekanntwerden von entsprechenden Rechtsverletzungen werden wir diese Inhalte umgehend
    entfernen.
</p>

<h2>Haftung für Links</h2>
<p>
    Unser Angebot enthält Links zu externen Websites Dritter, auf deren Inhalte wir keinen
    Einfluss haben. Deshalb können wir für diese fremden Inhalte auch keine Gewähr übernehmen.
    Für die Inhalte der verlinkten Seiten ist stets der jeweilige Anbieter oder Betreiber
    der Seiten verantwortlich. Die verlinkten Seiten wurden zum Zeitpunkt der Verlinkung auf
    mögliche Rechtsverstöße überprüft. Rechtswidrige Inhalte waren zum Zeitpunkt der
    Verlinkung nicht erkennbar.
</p>
<p>
    Eine permanente inhaltliche Kontrolle der verlinkten Seiten ist jedoch ohne konkrete
    Anhaltspunkte einer Rechtsverletzung nicht zumutbar. Bei Bekanntwerden von
    Rechtsverletzungen werden wir derartige Links umgehend entfernen.
</p>

<h2>Urheberrecht</h2>
<p>
    Die durch die Seitenbetreiber erstellten Inhalte und Werke auf diesen Seiten unterliegen
    dem deutschen Urheberrecht. Die Vervielfältigung, Bearbeitung, Verbreitung und jede Art
    der Verwertung außerhalb der Grenzen des Urheberrechtes bedürfen der schriftlichen
    Zustimmung des jeweiligen Autors bzw. Erstellers. Downloads und Kopien dieser Seite sind
    nur für den privaten, nicht kommerziellen Gebrauch gestattet.
</p>
<p>
    Soweit die Inhalte auf dieser Seite nicht vom Betreiber erstellt wurden, werden die
    Urheberrechte Dritter beachtet. Insbesondere werden Inhalte Dritter als solche
    gekennzeichnet. Sollten Sie trotzdem auf eine Urheberrechtsverletzung aufmerksam werden,
    bitten wir um einen entsprechenden Hinweis. Bei Bekanntwerden von Rechtsverletzungen
    werden wir derartige Inhalte umgehend entfernen.
</p>

<h2>Branchenportal — Nutzergenerierte Inhalte</h2>
<p>
    Dieses Portal (<strong>[PORTAL_NAME]</strong>) ermöglicht es Firmeninhabern, eigenständig
    Unternehmenseinträge zu erstellen und zu verwalten, sowie Nutzern, Bewertungen abzugeben.
    Für die Richtigkeit und Vollständigkeit der von Firmeninhabern eingestellten
    Unternehmensdaten übernehmen wir keine Haftung. Jeder Firmeninhaber ist für die Inhalte
    seines Eintrags selbst verantwortlich.
</p>
<p>
    Bewertungen geben die persönliche Meinung der jeweiligen Verfasser wieder und stellen
    keine Empfehlung oder Bewertung durch den Portalbetreiber dar. Wir behalten uns vor,
    Bewertungen zu moderieren und bei Verstoß gegen unsere Nutzungsbedingungen zu entfernen.
</p>

IMPRESSUM_HTML;

    public const DATENSCHUTZ = <<<'DATENSCHUTZ_HTML'

    <h1>Datenschutzerklärung</h1>
    <p><strong>Stand:</strong> Februar 2026</p>

    <h2>1. Verantwortlicher</h2>
    <p>
        Verantwortlich für die Datenverarbeitung auf diesem Portal ist:<br />
        <strong>[BETREIBER_NAME]</strong><br />
        [BETREIBER_STRASSE]<br />
        [BETREIBER_PLZ] [BETREIBER_ORT]<br />
        Deutschland<br />
        E-Mail: <a href="mailto:[BETREIBER_EMAIL]">[BETREIBER_EMAIL]</a><br />
        Telefon: [BETREIBER_TELEFON]
    </p>
    <h2>2. Übersicht der Datenverarbeitungen</h2>
    <p>
        Dieses Branchenportal (<strong>[PORTAL_NAME]</strong>) ermöglicht es Nutzern, Unternehmen zu finden, zu bewerten
        und eigene Firmeneinträge zu erstellen. Die nachfolgende Datenschutzerklärung erläutert, welche personenbezogenen
        Daten wir erheben, zu welchem Zweck und auf welcher Rechtsgrundlage.
    </p>

    <h2>3. Hosting und technische Bereitstellung</h2>
    <p>
        Unser Portal wird auf Servern in Deutschland/der EU gehostet. Beim Aufruf der Website werden automatisch
        folgende Daten in Server-Logfiles gespeichert:
    </p>
    <ul>
        <li>IP-Adresse des anfragenden Rechners (anonymisiert)</li>
        <li>Datum und Uhrzeit des Zugriffs</li>
        <li>Name und URL der abgerufenen Seite</li>
        <li>Übertragene Datenmenge</li>
        <li>Browsertyp und -version</li>
        <li>Verwendetes Betriebssystem</li>
        <li>Referrer-URL (zuvor besuchte Seite)</li>
    </ul>
    <p>
        <strong>Rechtsgrundlage:</strong> Art. 6 Abs. 1 lit. f DSGVO (berechtigtes Interesse an der technischen
        Bereitstellung und Sicherheit des Portals).<br />
        <strong>Speicherdauer:</strong> Logfiles werden nach 30 Tagen automatisch gelöscht.
    </p>

    <h2>4. Registrierung und Nutzerkonto</h2>
    <p>
        Sie können auf unserem Portal ein Nutzerkonto erstellen, um Firmeneinträge zu verwalten oder Bewertungen
        abzugeben. Dabei werden folgende Daten erhoben:
    </p>
    <ul>
        <li>Vor- und Nachname</li>
        <li>E-Mail-Adresse</li>
        <li>Passwort (verschlüsselt gespeichert)</li>
        <li>Optional: Firmenname, Telefonnummer</li>
    </ul>
    <p>
        <strong>Rechtsgrundlage:</strong> Art. 6 Abs. 1 lit. b DSGVO (Vertragserfüllung — Bereitstellung des Nutzerkontos).<br />
        <strong>Speicherdauer:</strong> Bis zur Löschung des Nutzerkontos durch den Nutzer oder auf Anfrage.
    </p>

    <h2>5. Firmeneinträge (Self-Service)</h2>
    <p>
        Firmeninhaber können über unser Portal kostenlos oder kostenpflichtig (Premium) einen Firmeneintrag erstellen.
        Dabei werden folgende Daten verarbeitet:
    </p>
    <ul>
        <li>Firmenname, Rechtsform</li>
        <li>Anschrift (Straße, PLZ, Ort)</li>
        <li>Kontaktdaten (Telefon, E-Mail, Website)</li>
        <li>Branche/Kategorie</li>
        <li>Beschreibungstext</li>
        <li>Firmenlogo und Bilder</li>
        <li>Öffnungszeiten</li>
    </ul>
    <p>
        Diese Daten werden <strong>öffentlich</strong> auf dem Portal angezeigt, da dies der ausdrückliche Zweck
        des Eintrags ist.
    </p>
    <p>
        <strong>Rechtsgrundlage:</strong> Art. 6 Abs. 1 lit. b DSGVO (Vertragserfüllung) und Art. 6 Abs. 1 lit. a
        DSGVO (Einwilligung zur Veröffentlichung).<br />
        <strong>Speicherdauer:</strong> Bis zur Löschung des Eintrags durch den Firmeninhaber oder auf Anfrage.
    </p>

    <h2>6. Bewertungen</h2>
    <p>
        Nutzer können Unternehmen auf unserem Portal bewerten. Dabei werden erhoben:
    </p>
    <ul>
        <li>Sternebewertung (1–5)</li>
        <li>Bewertungstext</li>
        <li>Name (optional — andernfalls „Anonym")</li>
        <li>E-Mail-Adresse (nicht öffentlich, nur zur Verifizierung)</li>
        <li>IP-Adresse (zur Missbrauchsprävention, nicht öffentlich)</li>
    </ul>
    <p>
        Bewertungen durchlaufen eine Moderation, bevor sie veröffentlicht werden.
    </p>
    <p>
        <strong>Rechtsgrundlage:</strong> Art. 6 Abs. 1 lit. f DSGVO (berechtigtes Interesse an einem
        vertrauenswürdigen Bewertungssystem).<br />
        <strong>Speicherdauer:</strong> Bis zur Löschung durch den Nutzer, den Portaladministrator oder auf
        berechtigten Antrag des bewerteten Unternehmens.
    </p>

    <h2>7. Zahlungsabwicklung (Premium-Einträge)</h2>
    <p>
        Für kostenpflichtige Premium-Einträge nutzen wir <strong>Stripe</strong> (Stripe Inc., 354 Oyster Point Blvd,
        South San Francisco, CA 94080, USA) als Zahlungsdienstleister.
    </p>
    <p>
        Bei einer Zahlung werden Ihre Zahlungsdaten (Kreditkartennummer, IBAN etc.) direkt an Stripe übermittelt.
        Wir selbst speichern <strong>keine</strong> vollständigen Zahlungsdaten — lediglich eine Referenz-ID,
        den Zahlungsstatus und das Abonnement-Modell.
    </p>
    <p>
        <strong>Rechtsgrundlage:</strong> Art. 6 Abs. 1 lit. b DSGVO (Vertragserfüllung).<br />
        Datenschutzerklärung von Stripe:
        <a href="https://stripe.com/de/privacy" target="_blank" rel="noreferrer noopener">stripe.com/de/privacy</a>
    </p>

    <h2>8. Cookies</h2>
    <p>Unser Portal verwendet folgende Cookies:</p>

    <h3>8.1 Technisch notwendige Cookies</h3>
    <table>
        <thead>
            <tr>
                <th>Cookie</th>
                <th>Zweck</th>
                <th>Dauer</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>session</td>
                <td>Sitzungsverwaltung, Login-Status</td>
                <td>Sitzungsende</td>
            </tr>
            <tr>
                <td>XSRF-TOKEN</td>
                <td>Schutz vor Cross-Site-Request-Forgery</td>
                <td>Sitzungsende</td>
            </tr>
            <tr>
                <td>cookie_consent</td>
                <td>Speicherung Ihrer Cookie-Präferenzen</td>
                <td>12 Monate</td>
            </tr>
        </tbody>
    </table>
    <p><strong>Rechtsgrundlage:</strong> Art. 6 Abs. 1 lit. f DSGVO (technisch erforderlich).</p>

    <h3>8.2 Analyse-Cookies (nur mit Einwilligung)</h3>
    <p>
        Sofern Sie einwilligen, setzen wir Google Analytics ein, um die Nutzung des Portals auszuwerten.
        Google Analytics verwendet Cookies, die eine Analyse der Benutzung der Website ermöglichen.
        Die IP-Adresse wird anonymisiert (anonymizeIp).
    </p>
    <p>
        <strong>Rechtsgrundlage:</strong> Art. 6 Abs. 1 lit. a DSGVO (Einwilligung).<br />
        Sie können Ihre Einwilligung jederzeit über den Cookie-Banner widerrufen.
    </p>

    <h2>9. Kontaktaufnahme</h2>
    <p>
        Wenn Sie uns per E-Mail oder über ein Kontaktformular kontaktieren, werden die von Ihnen mitgeteilten
        Daten (Name, E-Mail, Nachrichteninhalt) zur Bearbeitung Ihrer Anfrage gespeichert.
    </p>
    <p>
        <strong>Rechtsgrundlage:</strong> Art. 6 Abs. 1 lit. f DSGVO (berechtigtes Interesse an der Beantwortung
        von Anfragen).<br />
        <strong>Speicherdauer:</strong> Bis zur abschließenden Bearbeitung, maximal 6 Monate.
    </p>

    <h2>10. Ihre Rechte</h2>
    <p>Sie haben nach der DSGVO folgende Rechte:</p>
    <ul>
        <li><strong>Auskunftsrecht</strong> (Art. 15 DSGVO) — Welche Daten speichern wir über Sie?</li>
        <li><strong>Berichtigungsrecht</strong> (Art. 16 DSGVO) — Korrektur unrichtiger Daten</li>
        <li><strong>Löschungsrecht</strong> (Art. 17 DSGVO) — Löschung Ihrer Daten („Recht auf Vergessenwerden")</li>
        <li><strong>Einschränkung der Verarbeitung</strong> (Art. 18 DSGVO)</li>
        <li><strong>Datenübertragbarkeit</strong> (Art. 20 DSGVO) — Export Ihrer Daten in maschinenlesbarem Format</li>
        <li><strong>Widerspruchsrecht</strong> (Art. 21 DSGVO) — Widerspruch gegen Verarbeitung auf Basis berechtigter Interessen</li>
        <li><strong>Widerruf der Einwilligung</strong> (Art. 7 Abs. 3 DSGVO) — Jederzeit für die Zukunft</li>
    </ul>
    <p>
        Zur Ausübung Ihrer Rechte kontaktieren Sie uns unter:
        <a href="mailto:[BETREIBER_EMAIL]">[BETREIBER_EMAIL]</a>
    </p>

    <h2>11. Beschwerderecht</h2>
    <p>
        Sie haben das Recht, sich bei einer Datenschutz-Aufsichtsbehörde über unsere Verarbeitung personenbezogener
        Daten zu beschweren. Die zuständige Aufsichtsbehörde richtet sich nach dem Bundesland des Betreibers.
        Eine Liste der Aufsichtsbehörden finden Sie unter:
        <a href="https://www.bfdi.bund.de" target="_blank" rel="noreferrer noopener">www.bfdi.bund.de</a>
    </p>

    <h2>12. SSL/TLS-Verschlüsselung</h2>
    <p>
        Dieses Portal nutzt aus Sicherheitsgründen eine SSL/TLS-Verschlüsselung. Eine verschlüsselte Verbindung
        erkennen Sie an dem Schloss-Symbol in der Browserzeile und daran, dass die Adresszeile mit
        https:// beginnt.
    </p>

    <h2>13. Änderungen dieser Datenschutzerklärung</h2>
    <p>
        Wir behalten uns vor, diese Datenschutzerklärung bei Bedarf anzupassen, um sie an geänderte Rechtslagen
        oder bei Änderungen unserer Datenverarbeitungen anzupassen. Die jeweils aktuelle Version finden Sie
        stets auf dieser Seite.
    </p>

DATENSCHUTZ_HTML;
}
