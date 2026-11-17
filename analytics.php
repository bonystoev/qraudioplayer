<?php
declare(strict_types=1);

// analytics.php — zählt Events vom Audioplayer (app.js via navigator.sendBeacon).
//
// POST  event=play_click&action=play|pause&page=/soundcollage/louis-cyprien/&t=...
//       event=play_started
//       -> hängt eine Zeile an analytics-logs/events.csv (204 No Content)
//
// GET   analytics.php?stats=1  -> einfache Auswertung (gesamt, pro Event, pro Tag)
//
// Optional: $STATS_KEY setzen, dann heißt die Auswertung
//           analytics.php?stats=1&key=DEIN_KEY

$STATS_KEY = '';

$logDir  = __DIR__ . '/analytics-logs';
$logFile = $logDir . '/events.csv';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $event = (string)($_POST['event'] ?? '');
    if (!in_array($event, ['play_click', 'play_started'], true)) {
        http_response_code(400);
        exit('unknown event');
    }
    $row = [
        date('c'),                            // Server-Zeit
        $event,
        (string)($_POST['action'] ?? ''),      // play | pause (bei play_click)
        (string)($_POST['page'] ?? ''),        // Pfad der Seite
        (string)($_POST['t'] ?? ''),           // Client-Zeit
    ];
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $fh = @fopen($logFile, 'a');
    if ($fh) {
        flock($fh, LOCK_EX);
        fputcsv($fh, $row);
        flock($fh, LOCK_UN);
        fclose($fh);
    }
    http_response_code(204);
    exit;
}

if (isset($_GET['stats'])) {
    if ($STATS_KEY !== '' && (($_GET['key'] ?? '') !== $STATS_KEY)) {
        http_response_code(403);
        exit('forbidden');
    }
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>Play-Statistik</title>';
    echo '<h1>Play-Statistik</h1>';
    if (!is_file($logFile)) {
        echo '<p>Noch keine Daten.</p>';
        exit;
    }
    $rows = array_map('str_getcsv', file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    $byEvent = [];
    $byDay = [];
    foreach ($rows as $r) {
        $ev = (string)($r[1] ?? '?');
        $byEvent[$ev] = ($byEvent[$ev] ?? 0) + 1;
        $day = substr((string)($r[0] ?? ''), 0, 10);
        $byDay[$day] = ($byDay[$day] ?? 0) + 1;
    }
    ksort($byDay);
    echo '<p><strong>' . count($rows) . '</strong> Events gesamt</p><ul>';
    foreach ($byEvent as $ev => $n) {
        echo '<li>' . htmlspecialchars($ev, ENT_QUOTES) . ': ' . $n . '</li>';
    }
    echo '</ul><h2>Pro Tag</h2><ul>';
    foreach ($byDay as $d => $n) {
        echo '<li>' . htmlspecialchars($d, ENT_QUOTES) . ': ' . $n . '</li>';
    }
    echo '</ul>';
    exit;
}

http_response_code(405);
echo 'POST events, oder GET ?stats=1';
