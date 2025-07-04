# Kartenverwaltung-Plugin

Interaktive Karten-Module: Regionen (aus Profilfeldern) definieren, Genaue(re) Wohnorte (aus Profilfeldern) zuweisen, eigene Kartenbilder hochladen, Crop-Tool um aus Bild frei Markierungen zu setzen, Legende mit Icons.

Falls jobliste-Plugin (Jule) aktiv: sollte kompatibel sein. (Noch nicht getestet.) 

---

## 1. Features

- **Übersicht** aller Kartenbilder  
- **Detail-Ansicht** mit Hotspots, Zoom‐Toggle & Marker  
- **Cropper.js**-Integration für präzise Bereichsdefinition  
- **Home/Work-Zuweisung** per Profilfeld & eigener Tabelle  
- **Kategorien & Icons** für Legende (Font Awesome oder Bild)  
- **Admin-Module** zum Hinzufügen, Bearbeiten, Löschen, Mapping & Upload  

---

## 2. Installation

1. **Dateien** ins Forum kopieren:  
siehe 'main'
stylesheet 'karten.css' ?
bildverzeichnis für karten erstellen (bspw. images/karten) - falls später keine font-awesome-icons genutzt werden sollen ein zusätzliches unterverzeichnis (images/karten/icons)

2. **Plugin aktivieren** im ACP → Plugins → „Kartenverwaltung“.  
Dabei werden bei Bedarf angelegt:
- Datenbank-Tabellen & Spalten  
- ACP-Einstellungen  
- Templates  
- CSS-Datei  (?)

3. **Rechte** prüfen:  
Ordner `images/karten/` (oder Pfad aus Einstellung) benötigt Schreibrechte.

---

## 3. Dateien & Struktur
/inc/plugins/karten.php # Plugin-Logik 
/jscripts/karten/map_interaction.js # Frontend-JS für Zoom, Hotspots, Popups 
/admin/modules/config/karten/ # ACP-Module: add, edit, delete, upload, mapping /inc/languages/deutsch_du/karten.lang.php 
/inc/languages/deutsch_du/admin/karten.lang.php


---

## 4. Datenbank-Schema

### `karte_region_map`
| Spalte        | Typ               | Beschreibung                      |
|---------------|-------------------|-----------------------------------|
| id            | INT PK AI         | Mapping-ID                        |
| region_value  | VARCHAR(100)      | Regions-Label aus Profilfeld      |
| fid_location  | INT               | Profilfeld-FID für Standortwerte  |

### `map_images`
| Spalte      | Typ          | Beschreibung             |
|-------------|--------------|--------------------------|
| image_file  | VARCHAR(255) | Dateiname des Kartenbild |
| image_title | VARCHAR(255) | Anzeigename              |

### `map_areas`
Erweiterung von Standard-`map_areas` um:
| Spalte          | Typ                | Beschreibung                    |
|-----------------|--------------------|---------------------------------|
| category        | VARCHAR(100)       | Kategorie-Name                  |
| marker_title    | VARCHAR(50)        | Marker-Nummer/Text              |
| allow_home      | TINYINT(1)         | Home-Zuweisung erlauben         |
| allow_work      | TINYINT(1)         | Work-Zuweisung erlauben         |
| cap_home_max    | INT UNSIGNED       | Max. Bewohner (0 = unbegrenzt)  |
| cap_work_max    | INT UNSIGNED       | Max. Arbeiter (0 = unbegrenzt)  |
| icon_home       | VARCHAR(255)       | Icon (Home)                     |
| icon_work       | VARCHAR(255)       | Icon (Work)                     |
| icon_category   | VARCHAR(255)       | Icon für Kategorie-Legende      |

### `user_locations`
| Spalte    | Typ                  | Beschreibung                    |
|-----------|----------------------|---------------------------------|
| uid       | INT UNSIGNED         | Nutzer-ID                       |
| area_id   | INT UNSIGNED         | Bereichs-ID                     |
| type      | ENUM('home','work')  | Art der Zuordnung               |
| set_at    | DATETIME             | Zeitpunkt der Wahl              |

---

## 5. ACP-Einstellungen

**Gruppe „Kartenverwaltung“** mit:
| Name                    | Typ      | Beschreibung                                      |
|-------------------------|----------|---------------------------------------------------|
| karten_image_path       | Text     | Pfad zu Kartenbilder (relativ zur Board-URL)      |
| karten_region_fid       | Text     | FID des Profilfelds mit Regionswerten            |
| karten_region_fids      | Textarea | FIDs der Profilfelder für Standorte (Zeilen/Komma)|
| karten_allowed_groups   | Textarea | Gruppen-IDs, die Home/Work wählen dürfen          |
| icon_home               | Text     | Font-Awesome-Klasse oder Pfad für Home-Icon       |
| icon_work               | Text     | Font-Awesome-Klasse oder Pfad für Work-Icon       |
| karte_ext_categories    | Textarea | Zusätzliche Kategorien (je Zeile)                 |

> **Hinweis:** In „Icon (Kategorie)“ im Admin kann eingetragen werden:
> - z.B. `fas fa-store` (FA-Klasse)  
> - oder relativer Pfad `icons/shop.svg`

---

## 6. Templates

Bei Aktivierung erzeugt das Plugin (sid = ‑1):

| Template                               | Verwendungszweck                                     |
|----------------------------------------|------------------------------------------------------|
| karten_list                            | HTML-Gerüst für Karten-Übersicht                     |
| karten_detail                          | Detail-Seite mit Hotspots & Popup                    |
| karten_button_home                     | Button „Wohnort wählen“                              |
| karten_button_work                     | Button „Arbeitsplatz wählen“                         |
| karten_button_delete_home              | Button „Wohnort löschen“                             |
| karten_button_delete_work              | Button „Arbeitsplatz löschen“                        |
| karten_admin_title_row                 | Zusätzliche Zeile im ACP (Bild-Titel editieren)      |

---

## 7. Icon- und Bildnutzung

- **Font Awesome**: Trage im Admin-Feld z.B. `fas fa-store` ein.  
- **Eigene Icons**:  
  1. Lege Datei in `images/karten/icons/` ab  
  2. Fülle Admin-Feld mit `icons/dein_icon.svg`  

Im Frontend wird automatisch `<i class="...">` oder `<img src="...">` generiert.

---

## 8. Aktivierung / Deaktivierung / Deinstallation

- **Aktivierung**: Legt Tabellen, Spalten, Settings, Templates, CSS an (nur falls noch nicht vorhanden).  
- **Deaktivierung**: Hinterlässt alle Daten unverändert.  
- **Deinstallation**: Löscht ausschließlich die vom Plugin angelegten Tabellen, Spalten, Settings, Templates und CSS. Andere Daten (Profilfelder, Jobliste, Nutzer-Profile) bleiben erhalten.

---

## 9. Screenshots

![Übersicht](screenshots/overview.png)  
*Übersicht aller Kartenbilder*

![Detail](screenshots/detail.png)  
*Detailansicht mit Markern, Popup & Legende*

![Admin Add](screenshots/admin_add.png)  
*Neuen Bereich anlegen*

![Admin Edit](screenshots/admin_edit.png)  
*Bereich bearbeiten mit Cropper*

---


