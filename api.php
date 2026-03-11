<?php
header("Access-Control-Allow-Origin: *");
header('Content-Type: application/json; charset=utf-8');

// Adres statusu Twojego radia
$status_url = "http://82.145.41.8:33745/status.xsl";
$history_file = "history.json";

// Pobieranie danych
$html = @file_get_contents($status_url);

$current_song = "Nieznany utwór";
if ($html) {
    // Proste wycinanie tytułu
    $p1 = explode('Current Song:', $html);
    if(isset($p1[1])) {
        $p2 = explode('<td class="streamdata">', $p1[1]);
        if(isset($p2[1])) {
            $p3 = explode('</td>', $p2[1]);
            $current_song = trim(strip_tags($p3[0]));
        }
    }
}

// Historia
$history = [];
if (file_exists($history_file)) {
    $history = json_decode(file_get_contents($history_file), true) ?: [];
}

$last_title = !empty($history) ? $history[0]['title'] : "";

if ($current_song !== "Nieznany utwór" && $current_song !== "" && $current_song !== $last_title) {
    array_unshift($history, [
        'title' => $current_song,
        'time' => date('H:i:s'),
        'date' => date('d.m.Y')
    ]);
    $history = array_slice($history, 0, 50);
    file_put_contents($history_file, json_encode($history, JSON_UNESCAPED_UNICODE));
}

echo json_encode(['current' => $current_song, 'history' => $history], JSON_UNESCAPED_UNICODE);