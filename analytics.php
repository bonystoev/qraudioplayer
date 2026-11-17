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
    $post = $_POST;
    if (empty($post)) {
        // Fallback, falls der Content-Type nicht als Form-Post erkannt wird
        parse_str((string)file_get_contents('php://input'), $post);
    }
    $event = (string)($post['event'] ?? '');
    if (!in_array($event, ['play_click', 'play_started', 'listening_time'], true)) {
        http_response_code(400);
        exit('unknown event');
    }
    $row = [
        date('c'),                            // Server-Zeit
        $event,
        (string)($post['action'] ?? ''),      // play | pause (bei play_click)
        (string)($post['page'] ?? ''),        // Pfad der Seite
        (string)($post['t'] ?? ''),           // Client-Zeit
        $event === 'listening_time' ? (string)($post['duration_ms'] ?? '') : '', // ms gehört
    ];
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $fh = @fopen($logFile, 'a');
    if (!$fh) {
        http_response_code(500);
        exit('analytics-logs not writable - chmod 777 analytics-logs');
    }
    flock($fh, LOCK_EX);
    fputcsv($fh, $row);
    flock($fh, LOCK_UN);
    fclose($fh);
    http_response_code(204);
    exit;
}

if (isset($_GET['stats'])) {
    if ($STATS_KEY !== '' && (($_GET['key'] ?? '') !== $STATS_KEY)) {
        http_response_code(403);
        exit('forbidden');
    }
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>Play-Statistik</title>';
    echo '<h1>Play-Statistik</h1>';
    if (!is_file($logFile)) {
        echo '<p>Noch keine Daten.</p>';
        exit;
    }
    function fmtMs(int $ms): string {
        $s = (int)round($ms / 1000);
        $h = intdiv($s, 3600);
        $m = intdiv($s % 3600, 60);
        $sec = $s % 60;
        return $h > 0 ? sprintf('%d h %02d min', $h, $m) : sprintf('%d:%02d min', $m, $sec);
    }
    $rows = array_map('str_getcsv', file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    $byEvent = [];
    $byDay = [];
    $byDayListen = [];
    $listenMs = 0;
    $listenCount = 0;
    foreach ($rows as $r) {
        $ev = (string)($r[1] ?? '?');
        $byEvent[$ev] = ($byEvent[$ev] ?? 0) + 1;
        $day = substr((string)($r[0] ?? ''), 0, 10);
        $byDay[$day] = ($byDay[$day] ?? 0) + 1;
        if ($ev === 'listening_time') {
            $ms = (int)($r[5] ?? 0);
            $listenMs += $ms;
            $listenCount++;
            $byDayListen[$day] = ($byDayListen[$day] ?? 0) + $ms;
        }
    }
    ksort($byDay);
    echo '<p><strong>' . count($rows) . '</strong> Events gesamt</p><ul>';
    foreach ($byEvent as $ev => $n) {
        echo '<li>' . htmlspecialchars($ev, ENT_QUOTES) . ': ' . $n . '</li>';
    }
    echo '</ul>';
    if ($listenCount > 0) {
        echo '<h2>Hördauer</h2><ul>';
        echo '<li>gesamt: <strong>' . fmtMs($listenMs) . '</strong> (' . $listenCount . ' Sessions)</li>';
        echo '<li>Ø pro Session: ' . fmtMs((int)round($listenMs / $listenCount)) . '</li>';
        echo '</ul>';
    }
    echo '<h2>Pro Tag</h2><ul>';
    foreach ($byDay as $d => $n) {
        $line = htmlspecialchars($d, ENT_QUOTES) . ': ' . $n . ' Events';
        if (isset($byDayListen[$d])) {
            $line .= ', ' . fmtMs($byDayListen[$d]) . ' gehört';
        }
        echo '<li>' . $line . '</li>';
    }
    echo '</ul>';
    exit;
}

if (isset($_GET['debug'])) {
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'logDir: ' . $logDir . "\n";
    echo 'is_dir: ' . (is_dir($logDir) ? 'yes' : 'no') . "\n";
    echo 'is_writable(dir): ' . (is_dir($logDir) && is_writable($logDir) ? 'yes' : 'no') . "\n";
    echo 'logFile: ' . $logFile . "\n";
    echo 'logFile exists: ' . (is_file($logFile) ? 'yes' : 'no') . "\n";
    if (is_file($logFile)) {
        echo 'is_writable(file): ' . (is_writable($logFile) ? 'yes' : 'no') . "\n";
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        echo 'rows: ' . count($lines) . "\n";
        echo "last 5 rows:\n" . implode("\n", array_slice($lines, -5)) . "\n";
    }
    exit;
}

http_response_code(405);
echo 'POST events, oder GET ?stats=1 (Debug: ?debug=1)';
