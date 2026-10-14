# Anleitung: Cloudflare-Token mit Zonen-Rechten anlegen (#48 — Handgriff als Schritt 0 in #40)

Ein Token, der **#37** (Weiterleitungen von `firmenfreund.net` und
`tierarztportal.com`) und **#40** (die vier WAF-Regeln auf 18 Portal-Zonen)
zusammen abdeckt. Das Anlegen selbst geht nur im Cloudflare-Konto und kann aus
keinem Agentenlauf heraus erledigt werden — alles danach ist Werkzeugarbeit.

Dauer: etwa fünf Minuten. Danach laufen #37 und #40 ohne Dashboard.

---

## 1. Befund, gemessen 08.10.2026

`/root/sun-zugang.txt` am Ursprung existiert (`-rw------- root`, angelegt
08.10.2026 17:50) und enthält genau zwei Zeilen:

| Schlüssel | Zustand |
|---|---|
| `CLOUDFLARE_API_TOKEN_SUN_TURNSTILE` | gültig, aber auf „Account / Turnstile: Edit" geschnitten |
| `CLOUDFLARE_ACCOUNT_ID_SUN` | vorhanden |

Gegenprobe mit diesem Token:

```
CF_API_TOKEN=<Turnstile-Token> scripts/cloudflare-weiterleitungen-entfernen.sh --rechte-pruefen
== Rechte des Tokens
  Zone / Zone: Read                      lesbar (0 Zone(n) sichtbar)
  Der Token sieht keine einzige Zone — Zone Resources sind leer.
EXIT=65
```

`/user/tokens/verify` sagt also `active`, und trotzdem ist der Token hier
unbrauchbar: er sieht keine Zone, trägt weder Weiterleitungen (#37) noch
Firewall-Regeln (#40). **Ein gültiger Token ist noch kein brauchbarer.** Weder
`CLOUDFLARE_API_TOKEN_SUN_REDIRECT` noch `CLOUDFLARE_API_TOKEN_SUN_WAF` sind
hinterlegt; beide Skripte beenden deshalb mit Exit 65.

---

## 2. Token anlegen

Cloudflare-Dashboard → Profilmenü rechts oben → **My Profile** → **API Tokens**
→ **Create Token** → ganz unten **Create Custom Token** → **Get started**.

Name: `sun-zonen-redirect-waf`

### Berechtigungen

Alle sechs Zeilen in **einen** Token. Dann deckt er #37 und #40 ab und es
braucht keinen zweiten Dashboard-Gang:

| Gruppe | Recht | Wofür |
|---|---|---|
| Zone | Zone → **Read** | Zonen-Kennung auflösen (jedes Skript, zwingend) |
| Zone | Zone → **Edit** | Page Rules lesen und löschen (#37) |
| Zone | Config Rules → **Edit** | Redirect Rules / Single Redirect (#37) |
| Zone | Firewall Services → **Edit** | Custom Rules und Rate Limiting Rules (#40) |
| Zone | Bot Management → **Read** | Bestandsaufnahme Bot Fight Mode (#40) |
| Account | Account Filter Lists → **Edit** | Bulk-Redirect-Listen, hängen am Konto (#37) |

**Transform Rules wird nicht gebraucht** — das deckt nur Umschreibungen ab,
nicht Weiterleitungen. Weiterleitungen hängen an **Config Rules** (Phase
`http_request_dynamic_redirect`).

### Geltungsbereich

**Zone Resources:** `Include` → `All zones from an account` → SUN-Konto.

Grund: #40 arbeitet auf 18 Portal-Zonen (`ZONEN_VORGABE` in
`scripts/cloudflare-waf-regeln-setzen.sh`). Eine Aufzählung einzelner Zonen geht
auch, kostet aber 18 Zeilen und bricht, sobald ein Portal hinzukommt. Gelöscht
wird trotzdem nie in einer fremden Zone: beide Skripte führen ihre Zonenliste
selbst und holen sie nicht aus `/zones`.

**Client IP Address Filtering** und **TTL** leer lassen →
**Continue to summary** → **Create Token** → Wert einmalig kopieren.

---

## 3. Ablegen

Ein Befehl, von der Arbeitsmaschine aus. Das Skript fragt den Token verdeckt ab
(kein Shell-Verlauf, kein Prozessargument), prüft **vor** der Ablage jedes
einzelne Recht gegen die API und schiebt ihn als stdin-Strom zum Ursprung —
nie als `ssh`-Argument, sonst stünde er in der Prozessliste des Servers:

```
scripts/cloudflare-weiterleitungen-entfernen.sh --token-ablegen
```

Trägt derselbe Token die beiden #40-Rechte, legt das Skript ihn zusätzlich
unter `CLOUDFLARE_API_TOKEN_SUN_WAF` ab — das ist der Schlüssel, den
`scripts/cloudflare-waf-regeln-setzen.sh` liest. Abschaltbar mit
`SUN_AUCH_WAF=0`. Ein vorhandener Turnstile-Token bleibt unberührt; die Ablage
ersetzt nur die eigene Zeile und hängt nicht an.

Ohne Tastatureingabe, etwa aus einem Passwortspeicher:

```
pbpaste | scripts/cloudflare-weiterleitungen-entfernen.sh --token-ablegen
```

Von Hand geht es auch, ist aber der längere Weg:

```
ssh sun
printf 'CLOUDFLARE_API_TOKEN_SUN_REDIRECT=%s\n' '<token>' >> /root/sun-zugang.txt
printf 'CLOUDFLARE_API_TOKEN_SUN_WAF=%s\n'      '<token>' >> /root/sun-zugang.txt
chmod 600 /root/sun-zugang.txt
```

Der Token gehört **nicht** ins Repository, **nicht** in eine `.env` und
**nicht** in `.env.example`: er darf Zonen umkonfigurieren, die Anwendung
braucht ihn nie.

---

## 4. Probe

```
scripts/cloudflare-weiterleitungen-entfernen.sh --rechte-pruefen
```

Soll — Exit **0** und je Zeile `lesbar`:

```
== Rechte des Tokens
  Zone / Zone: Read                      lesbar (24 Zone(n) sichtbar)
  Probezone: firmenfreund.net
  Zone / Config Rules (Redirect Rules)   lesbar
  Zone / Zone: Edit (Page Rules)         lesbar
  Zone / Zone Settings (SSL-Modus)       lesbar
  Account / Account Filter Lists         lesbar
  Zone / Firewall Services (#40)         lesbar
  Zone / Bot Management: Read (#40)      lesbar

Der Token traegt alles, was #37 braucht.
Er traegt auch Firewall Services und Bot Management — #40 laeuft mit ihm.
```

Exit-Codes der Probe: **0** alles da, **65** kein Token / keine Zone sichtbar,
**71** mindestens ein Recht für #37 fehlt (die fehlende Zeile steht als
`FEHLT` samt Cloudflare-Fehlercode darüber). Fehlen nur die beiden
#40-Rechte, bleibt der Exit 0 und die letzte Zeile sagt es.

Lesend lassen sich Schreibrechte nicht beweisen; sie hängen bei Cloudflare aber
an denselben Berechtigungsgruppen wie der Lesezugriff. Ein fehlender
Lesezugriff ist damit der harte Befund, ein vorhandener der starke Hinweis —
der endgültige Beweis ist der erste `--entfernen`-Lauf.

---

## 5. Danach, ohne Dashboard

**#37** (Reihenfolge beachten: SSL-Modus zuerst auf „Full", sonst 526 — siehe
`docs/messungen/weiterleitungen-entfernen-anleitung.md`):

```
scripts/cloudflare-weiterleitungen-entfernen.sh              # listet, Exit 71 bei Fund
scripts/cloudflare-weiterleitungen-entfernen.sh --entfernen  # löscht
scripts/cloudflare-weiterleitungen-entfernen.sh --kante      # Gegenprobe, Soll Exit 0
```

**#40**:

```
scripts/cloudflare-waf-regeln-setzen.sh --bestandsaufnahme
scripts/cloudflare-waf-regeln-setzen.sh                      # setzen
scripts/cloudflare-waf-regeln-setzen.sh --pruefen            # Soll-Ist, Exit 71 bei Abweichung
scripts/waf-wirkung-pruefen.sh                               # ohne Token, von aussen
```

Der Token wird nur für Domain- und Kantenarbeit gebraucht, nicht im Betrieb.
Ist #37 und #40 abgenommen, darf er gelöscht werden — dann auch die beiden
Zeilen aus `/root/sun-zugang.txt` entfernen.
