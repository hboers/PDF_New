# PDF New Web Interface

Kleine lokale Weboberfläche zum Sichten, Nummerieren, Archivieren und Entfernen von PDF-Dateien im Ordner `PDF_New`.

## Voraussetzungen

- PHP mit CLI-Unterstützung
- Bash und `curl` für das Startskript
- LibreWolf, wenn die Oberfläche automatisch im Browser geöffnet werden soll
- Internetzugriff für das über ein CDN eingebundene Bootstrap-Stylesheet

## Start

```bash
./serv.sh
```

Das Skript startet den PHP-Entwicklungsserver unter:

```text
http://127.0.0.1:8002
```

Anschließend wird die Seite automatisch in LibreWolf geöffnet. Alternativ kann der Server direkt gestartet werden:

```bash
php -S 127.0.0.1:8002
```

## Bedienung

Die linke Seite zeigt alle PDF-Dateien aus `PDF_New` und seinen Unterordnern. Nach Auswahl einer Datei erscheint rechts die PDF-Vorschau.

### Belegnummern

Der Button **Belegnummern** versieht alle noch nicht nummerierten PDF-Dateien mit einer Belegnummer. Sie besteht aus dem aktuellen Datum und einer zweistelligen laufenden Nummer:

```text
YYYYMMDDNN-Dateiname.pdf
```

Beispiel:

```text
2026080801-Rechnung.pdf
```

Pro Tag sind die laufenden Nummern `01` bis `99` möglich. Bereits nummerierte Dateien bleiben unverändert.

### Archivieren

Der Button **Archivieren** wird nur aktiviert, wenn die ausgewählte Datei eine Belegnummer besitzt. Die PDF-Datei wird anhand der ersten vier Stellen der Belegnummer in einen Jahresordner verschoben:

```text
../PDF_Archive/YYYY/
```

Beispiel:

```text
PDF_New/2026080801-Rechnung.pdf
    -> PDF_Archive/2026/2026080801-Rechnung.pdf
```

Der Zielordner wird bei Bedarf automatisch angelegt. Existiert dort bereits eine gleichnamige Datei, wird sie nicht überschrieben und die Archivierung schlägt fehl.

### Entfernen

Der Button **Entfernen** benennt die ausgewählte PDF-Datei nach einer Sicherheitsabfrage um. Dabei wird die Endung `.deleted` angehängt:

```text
Rechnung.pdf -> Rechnung.pdf.deleted
```

Die Datei wird dadurch nicht endgültig gelöscht und erscheint nicht mehr in der PDF-Liste.

## Dateien

```text
index.php   Weboberfläche und Dateiverarbeitung
serv.sh     Startet den lokalen Server und öffnet LibreWolf
```

## Hinweis

Die Anwendung ist für den lokalen Betrieb vorgesehen. Der PHP-Entwicklungsserver ist ausschließlich an `127.0.0.1` gebunden und daher nicht direkt aus dem Netzwerk erreichbar.
