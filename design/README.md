# Gestaltungsvorgaben Content-Pipeline

Verbindliche Klammer: Dokument **„UX-Leitbild Ratgeber-Pipeline (Epic #1)"**.
Tokens: `resources/css/content/theme.css` (Quelle) und `tailwind.content.config.cjs` (Export).

| Datei | Inhalt | Umsetzung in |
|---|---|---|
| `content-dashboard.md` | Sieben Panel-Screens, Desktop; drei davon zusätzlich Mobil | #4, #19, #20, #25 |
| `ratgeber-template.md` | Leserseite nach `article_blueprint`, Desktop und Mobil | #14, #17, #27 |

## Warum Markdown statt `.fig`

Das Ticket nennt `design/content-dashboard.fig` und `design/ratgeber-template.fig`.
Eine Figma-Datei ist ein Binärformat eines gehosteten Dienstes; sie lässt sich weder
aus dem Repository heraus erzeugen noch dort sinnvoll versionieren oder prüfen.
Die Screens liegen deshalb als vollständige Layout- und Verhaltensspezifikation vor:
Raster, Maße, Zustände, Interaktion, Barrierefreiheit, jeweils mit konkreten Werten
aus den Token-Dateien. Damit ist die Umsetzung ohne Rückfrage möglich, und die Vorgabe
bleibt im selben Git-Verlauf wie der Code, der sie umsetzt.

Wird zusätzlich eine Figma-Datei für die Abnahme gebraucht, ist sie aus diesen beiden
Dokumenten in einem Zug nachbaubar. Sie bleibt dann Anschauungsmaterial; verbindlich
sind die Werte hier und in `theme.css`.
