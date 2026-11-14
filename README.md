# Audioplayer für die Ausstellung

Statischer, responsiver Audioplayer (reines HTML/CSS/JS, kein Build-Schritt, keine Abhängigkeiten).
Ablauf: Besucher:in scannt QR-Code → landet auf dieser Seite → Audioplayer auf dem Smartphone.

## Dateien

- `index.html` – Aufbau der Seite
- `style.css` – Gestaltung (mobil-first, dunkles Galerie-Design, Touch-optimiert)
- `app.js` – Logik und `CONFIG` (Audio, Cover, Titel, Künstler:in) – ganz oben in der Datei
- `assets/audio.wav` – Demo-Audio (Platzhalter, bitte ersetzen!)
- `assets/cover.svg` – Demo-Cover (Platzhalter, bitte ersetzen!)

## Anpassen

In `app.js` ganz oben:

```js
const CONFIG = {
  audioSrc: 'assets/mein-audio.mp3',
  coverSrc: 'assets/cover.jpg',
  title: 'Titel des Werks',
  artist: 'Name der Künstlerin / des Künstlers',
  credits: ['Zeile 1', 'Zeile 2', 'Zeile 3'], // leer lassen: []
};
```

- Audiodatei (mp3/ogg/wav) nach `assets/` kopieren und in `CONFIG.audioSrc` eintragen.
- Cover-Bild (jpg/png/svg) nach `assets/` kopieren und in `CONFIG.coverSrc` eintragen.
  Das Cover wird automatisch quadratisch zugeschnitten (`aspect-ratio: 1/1` + `object-fit: cover`).

## Lokal testen

```bash
cd audioplayer
python3 -m http.server 8000
# Browser: http://localhost:8000
```

## Veröffentlichen + QR-Code

1. Den Ordner bei einem statischen Hoster deployen (z. B. GitHub Pages, Netlify, Uberspace, eigener Webspace). HTTPS wird empfohlen.
2. Die öffentliche URL (z. B. `https://ausstellung.example.org/audioplayer/`) in einen beliebigen QR-Code-Generator eingeben, Bild herunterladen und ausdrucken/hinlegen.

## Funktionen

- Play/Pause-Button (Icon wechselt), Stop-Button (Pause + zurück auf 0:00)
- Lautstärke-Slider startet beim Erstbesuch in der Mitte (50 %), die gewählte Lautstärke wird lokal gespeichert; läuft über einen GainNode der Web Audio API, damit er auch auf iOS funktioniert, wo `audio.volume` ignoriert wird
- Fortschrittsbalken mit Sprungfunktion und Zeitanzeige (Gain-Ducking unterdrückt das Knacken beim Springen)
- Animierte Wellenform während der Wiedergabe (Web Audio API, `AnalyserNode`)
- Quadratisches Cover, mobil optimiert (Safe-Area oben/unten, große Touch-Flächen, funktioniert ab ~320 px Breite; zentriert per `margin: auto`, damit bei wenig Viewport-Höhe nichts oben abgeschnitten wird)
- Optionaler Credits-Block unten in der Karte (eine Zeile pro Eintrag in `CONFIG.credits`; leeres Array versteckt den Block)

## Hinweise

- Auf iOS startet der Ton erst nach dem ersten Tippen (Play-Button) – das ist ein Browser-Schutz und wird automatisch gehandhabt.
- Falls die Audiodatei fehlt, zeigt die Seite einen entsprechenden Hinweis statt stumm zu bleiben.
