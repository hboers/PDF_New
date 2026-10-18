# PDF_New

Eine kleine, lokal laufende PHP-Webanwendung zum Sichten und Verwalten eines PDF-Eingangsordners. PDF_New zeigt eine Dateiliste mit Vorschau und unterstützt Belegnummern, Archivierung und das reversible Entfernen von Dateien.

## Funktionen

- PDFs im Projektordner und seinen Unterordnern auflisten und im Browser ansehen
- Mehrere Dateien auswählen und gemeinsam bearbeiten
- Ausgewählten PDFs eine Belegnummer im Format `YYYYMMDDNN-Dateiname.pdf` geben
- Belegnummern des aktuellen Tages mit den Dateien im Eingangsordner und im passenden Archiv-Jahresordner abgleichen
- Nummerierte PDFs nach `../PDF_Archive/YYYY/` verschieben
- Dateien durch Anhängen von `.deleted` aus der Liste nehmen, ohne sie direkt zu löschen

Die Belegnummer dient als stabiler Verweis zwischen einem Eintrag in der Einnahmenüberschussrechnung (EÜR) und dem zugehörigen archivierten Beleg.

## Voraussetzungen

- PHP 8.0 oder neuer mit CLI-Unterstützung
- Bash und `curl` für das Startskript
- LibreWolf für den automatischen Browserstart über `serv.sh`
- Internetverbindung zum Laden des Bootstrap-Stylesheets von jsDelivr

Alternativ kann die Anwendung mit einem anderen Browser geöffnet werden. Dafür muss PHP separat gestartet und die angezeigte lokale Adresse manuell im Browser geöffnet werden.

## Installation und Start

Lege den Ordner `PDF_New` neben den Ordner `PDF_Archive`. Die Anwendung erwartet diese Struktur:

```text
projektordner/
├── PDF_New/
└── PDF_Archive/   # wird bei Bedarf angelegt
```

Starte die Anwendung aus `PDF_New`:

```bash
./serv.sh
```

Das Skript startet den PHP-Entwicklungsserver auf `http://127.0.0.1:8002` und öffnet die Anwendung in einer temporären LibreWolf-Instanz. Beim Beenden des Browserfensters wird auch der Server beendet.

Ohne `serv.sh` kann der Server direkt gestartet werden:

```bash
php -S 127.0.0.1:8002
```

Danach `http://127.0.0.1:8002` im Browser öffnen. Der Server muss aus dem Verzeichnis `PDF_New` gestartet werden.

### Desktop-Starter und LibreWolf-Profil

`PDF_New.desktop` ist ein Linux-Desktop-Starter. Er führt `./serv.sh` im Verzeichnis `PDF_New` aus und öffnet so die Anwendung über den lokalen Server in LibreWolf. Du kannst den Starter zum Beispiel im Anwendungsmenü oder auf dem Desktop ablegen.

Das Verzeichnis `librewolf-profile/` enthält die Einstellungen für das separate, temporäre LibreWolf-Profil, das `serv.sh` bei jedem Start erstellt und beim Beenden wieder entfernt. `user.js` stellt die Sprache auf Deutsch, unterdrückt Erststart- und Standardbrowser-Abfragen und aktiviert benutzerdefinierte Stylesheets. `chrome/userChrome.css` blendet Browserleisten, Seitenleiste, Statusanzeige und Suchleiste aus, damit die PDF-Verwaltung wie ein schlichtes Anwendungsfenster wirkt. Das normale LibreWolf-Profil bleibt davon unberührt.

## Bedienung

Links steht die alphabetisch sortierte PDF-Liste, rechts die Vorschau der geöffneten Datei. Über die Checkboxen lassen sich Dateien für gemeinsame Aktionen auswählen.

### Belegnummer vergeben

Für ausgewählte, noch nicht nummerierte PDFs vergibt die Anwendung das aktuelle Datum in der Zeitzone `Europe/Berlin` und eine zweistellige laufende Nummer:

```text
YYYYMMDDNN-Dateiname.pdf
```

Zum Beispiel: `2026080801-Rechnung.pdf`. Pro Tag stehen die Nummern `01` bis `99` zur Verfügung. Bereits belegte Nummern im Eingangsordner und im passenden Jahresordner des Archivs werden übersprungen. Bereits nummerierte Dateien bleiben unverändert.

### Archivieren

Ausgewählte PDFs mit Belegnummer werden anhand des Jahres aus der Nummer in den entsprechenden Archivordner verschoben. Der Jahresordner wird bei Bedarf erstellt. Eine gleichnamige Datei im Ziel verhindert das Verschieben und wird nicht überschrieben.

```text
PDF_New/2026080801-Rechnung.pdf
  → PDF_Archive/2026/2026080801-Rechnung.pdf
```

### Entfernen

Nach Bestätigung benennt die Anwendung ausgewählte PDFs um, indem sie `.deleted` an den Dateinamen anhängt. So wird die Datei aus der PDF-Liste ausgeblendet, aber nicht endgültig gelöscht. Eine vorhandene Datei mit diesem Zielnamen wird nicht überschrieben.

## Dateien

- `index.php` – Oberfläche und Verarbeitung der PDF-Dateien
- `serv.sh` – startet den lokalen PHP-Server und LibreWolf
- `librewolf-profile/` – Browser-Einstellungen für die temporäre LibreWolf-Instanz

## Hinweise

Die Anwendung ist für den lokalen Betrieb gedacht. Der Server bindet ausschließlich an `127.0.0.1` und ist nicht direkt aus dem Netzwerk erreichbar. Sie verwendet keine Datenbank; Dateiaktionen wirken unmittelbar auf die Dateien im Eingangs- und Archivordner. Vor der Veröffentlichung sollten keine persönlichen Dokumente oder lokalen Konfigurationsdateien im Repository liegen.
