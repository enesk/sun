# Branchen-Standardbilder (#16)

Letzte Rueckfallebene des `HeroImageGenerator`: erreicht weder fal.ai noch
Unsplash ein Titelbild, nimmt der `GenerateAssetsJob` die Datei
`<branch>.webp` aus diesem Verzeichnis. `<branch>` ist der Schluessel aus
`App\Guide\Support\BranchResolver`; `default.webp` greift fuer Mandanten
ohne erkannte Branche.

Seit #72 liegen hier **echte Fotos** statt der abstrakten Platzhalter. Alle
Dateien sind WebP im Format 1600x900 (16:9), zugeschnitten aus dem jeweiligen
Original.

## Anforderungen an einen Ersatz

- Format WebP, Querformat 16:9, mindestens 1600 px breit.
- Rechte fuer die kommerzielle Nutzung ohne Attributionspflicht. Bilder mit
  Namensnennung gehoeren nicht hierher, sondern ueber den Unsplash-Weg, der
  den Nachweis in `hero_image_credit` mitfuehrt.
- Kein Text im Bild, keine erkennbaren Gesichter, keine Logos oder
  Markenzeichen — dieselben Vorgaben, die auch im Bild-Prompt stehen.
- Kein Bild, das inhaltlich etwas behauptet (keine Preisschilder, keine
  Foerderplaketten).

Der `ImageOptimizer` schneidet die Datei auf 16:9 und rechnet sie in die
konfigurierten Breiten; die Ausgangsdatei muss also nicht exakt passen.

## Herkunft und Lizenz

Alle Vorlagen stammen aus Wikimedia Commons und stehen unter **CC0 1.0**
(Public Domain Dedication, Wikidata-Aussage P275 = Q6938433). CC0 verlangt
keine Namensnennung; der Nachweis hier ist dokumentarisch, nicht rechtlich
gefordert. Die Dateien wurden auf 16:9 beschnitten, auf 1600x900 skaliert und
mit Qualitaet 82 als WebP gespeichert; sonst wurden sie nicht veraendert.

| Datei | Motiv | Quelle (Commons-Dateiseite) | Lizenz |
| --- | --- | --- | --- |
| `default.webp` | Handwerkzeug-Set auf hellem Untergrund | [Hand-tool set with bits and accessories arranged on a white surface.](https://commons.wikimedia.org/wiki/File:Hand-tool_set_with_bits_and_accessories_arranged_on_a_white_surface..jpg) | CC0 1.0 |
| `elektro.webp` | Kabeltrommel mit Erdkabel | [9756Bulacan Baliuag Town Proper 20](https://commons.wikimedia.org/wiki/File:9756Bulacan_Baliuag_Town_Proper_20.jpg) | CC0 1.0 |
| `energieberatung.webp` | Steinwolle-Einblasdaemmung, Makro | [Løsull av steinull for innblåsing](https://commons.wikimedia.org/wiki/File:L%C3%B8sull_av_steinull_for_innbl%C3%A5sing.JPG) | CC0 1.0 |
| `fahrschule.webp` | Landstrasse unter Baumdach | [Car driving along a road surrounded by trees](https://commons.wikimedia.org/wiki/File:Car_driving_along_a_road_surrounded_by_trees.jpg) | CC0 1.0 |
| `fliesen.webp` | Blau-weisse Wandfliesen (Azulejos) | [Azulejos in Belem](https://commons.wikimedia.org/wiki/File:Azulejos_in_Belem.jpg) | CC0 1.0 |
| `gartenbau.webp` | Rasenflaeche mit Beetkante und Gehoelz | [Japanese Garden - Kew Gardens, London - DSC03044](https://commons.wikimedia.org/wiki/File:Japanese_Garden_-_Kew_Gardens,_London_-_DSC03044.jpg) | CC0 1.0 |
| `geruestbau.webp` | Geruestrohre mit Kupplungen | [Metal scaffolding at WP Welding 2](https://commons.wikimedia.org/wiki/File:Metal_scaffolding_at_WP_Welding_2.jpg) | CC0 1.0 |
| `gutachter.webp` | Greifzirkel auf Holzplatte | [Outside spring caliper 1](https://commons.wikimedia.org/wiki/File:Outside_spring_caliper_1.jpg) | CC0 1.0 |
| `hoch-tiefbau.webp` | Bewehrung und Schalung auf der Baustelle | [Rebar combo](https://commons.wikimedia.org/wiki/File:Rebar_combo.jpg) | CC0 1.0 |
| `kfz.webp` | Motorraum eines Fahrzeugs | [1949 Nash 600 four-door sedan ... 15of16](https://commons.wikimedia.org/wiki/File:1949_Nash_600_four-door_sedan_in_green_and_black_at_2017_Rockville_Maryland_show_15of16.jpg) | CC0 1.0 |
| `maler.webp` | Pinsel mit Farbpigmenten | [Art-brush-painting-colors (24030758590)](https://commons.wikimedia.org/wiki/File:Art-brush-painting-colors_(24030758590).jpg) | CC0 1.0 |
| `medizin.webp` | Stethoskop auf dunkler Ablage | [Stethoscope No.120](https://commons.wikimedia.org/wiki/File:Stethoscope_No.120.JPG) | CC0 1.0 |
| `metallbau.webp` | Stahltraeger, Rohmaterial | [I-beam raw material at WP Welding 4](https://commons.wikimedia.org/wiki/File:I-beam_raw_material_at_WP_Welding_4.jpg) | CC0 1.0 |
| `sanitaer.webp` | Anschluesse und Siphon unter einem Waschbecken | [247 Home Rescue back of sink plumbing](https://commons.wikimedia.org/wiki/File:247_Home_Rescue_back_of_sink_plumbing.jpg) | CC0 1.0 |
| `solar-pv.webp` | Photovoltaikmodule mit Himmel und Baum | [SolarCellPanel](https://commons.wikimedia.org/wiki/File:SolarCellPanel.jpg) | CC0 1.0 |
| `spedition.webp` | Gueterwagen mit Ladung | [Trailer Train Flatcar with Auto Truck Frames (10589066814)](https://commons.wikimedia.org/wiki/File:Trailer_Train_Flatcar_with_Auto_Truck_Frames_(10589066814).jpg) | CC0 1.0 |
| `tierarzt.webp` | Behandlungstisch einer Tierarztpraxis | [Veterinary table](https://commons.wikimedia.org/wiki/File:Veterinary_table.jpg) | CC0 1.0 |

## Warum Commons und nicht ein Stockportal

Geprueft wurden Openverse, PxHere und Flickr. Openverse liefert entweder nur
Vorschauen unter 1600 px oder blockt nach wenigen Abfragen, PxHere gibt ohne
Konto hoechstens 1200 px heraus, Flickr ohne API-Schluessel hoechstens 1024 px.
Commons ist die einzige erreichbare Quelle, die CC0-Originale in voller
Aufloesung ausliefert. Wer spaeter lizenziertes Stockmaterial einkauft, kann
die Dateien einfach ueberschreiben — Dateiname und Format bleiben gleich.
