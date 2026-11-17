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
- Lautstärke-Slider startet beim Erstbesuch voll (100 %), die gewählte Lautstärke wird lokal gespeichert; dezentes Design (dünne graue Spur, kleiner Griff, gedimmtes Icon); läuft über einen GainNode der Web Audio API, damit er auch auf iOS funktioniert, wo `audio.volume` ignoriert wird
- Fortschrittsbalken mit Sprungfunktion und Zeitanzeige (Gain-Ducking unterdrückt das Knacken beim Springen)
- Animierte Wellenform während der Wiedergabe (Web Audio API, `AnalyserNode`)
- Quadratisches Cover, mobil optimiert (Safe-Area oben/unten, große Touch-Flächen, funktioniert ab ~320 px Breite; zentriert per `margin: auto`, damit bei wenig Viewport-Höhe nichts oben abgeschnitten wird)
- Optionaler Credits-Block unten in der Karte (eine Zeile pro Eintrag in `CONFIG.credits`; leeres Array versteckt den Block)

## Klick-Analyse (Play-Button)

Der Player meldet Klicks per `navigator.sendBeacon` (POST) an `analytics.php` im selben Ordner wie die Seite. Dort landen die Events in `analytics-logs/events.csv`.

- Events: `play_click` (jeder Klick auf Play/Pause, mit `action=play|pause`), `play_started` (tatsächlicher Wiedergabestart) und `listening_time` (Hördauer in `duration_ms` — wird akkumuliert und beim Ende der Wiedergabe, Tab-Wechsel oder Schließen der Seite per `sendBeacon` geflusht)
- Auswertung im Browser öffnen: `analytics.php?stats=1` (gesamt, pro Event, Hördauer gesamt/Ø/pro Tag, Events pro Tag)
- `analytics-logs/` muss für den Webserver beschreibbar sein (z. B. `chmod 777 analytics-logs`); wird sonst selbst angelegt, wenn möglich
- Abschaltbar in `app.js` (`CONFIG.analyticsEndpoint: ''`); schlägt das Endpoint fehl, passiert still nichts — der Player funktioniert trotzdem
- Anonym: keine Cookies, keine IP, keine personenbezogenen Daten — nur Zeitstempel, Event, Aktion, Seitenpfad
- Optional in `analytics.php` oben `$STATS_KEY` setzen und die Statistik nur mit `analytics.php?stats=1&key=...` abrufen

### Troubleshooting: „Noch keine Daten“

- In der deployed `app.js` muss in `CONFIG` `analyticsEndpoint: 'analytics.php'` stehen (fehlt die Zeile, greift der Default `analytics.php`; `''` schaltet ab)
- Einmal Play drücken, danach `analytics.php?stats=1` neu laden
- `analytics-logs/` neben `analytics.php` muss beschreibbar sein (`chmod 777 analytics-logs`); sonst antwortet das Endpoint mit **500** (sichtbar im Network-Tab der Browser-DevTools)
- Im Network-Tab prüfen, ob der POST auf `analytics.php` mit **204** durchgeht

## Hinweise

- Auf iOS startet der Ton erst nach dem ersten Tippen (Play-Button) – das ist ein Browser-Schutz und wird automatisch gehandhabt.
- Falls die Audiodatei fehlt, zeigt die Seite einen entsprechenden Hinweis statt stumm zu bleiben.
