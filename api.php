<?php
header("Access-Control-Allow-Origin: *");
header('Content-Type: application/json');

$status_url = "http://82.145.41.8:33745/status.xsl";
$history_file = "history.json";

// Inicjalizacja pliku historii
if (!file_exists($history_file)) {
    file_put_contents($history_file, json_encode([]));
    chmod($history_file, 0666);
}

$history = json_decode(file_get_contents($history_file), true);

// Pobieranie danych przez cURL (stabilniejsze)
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $status_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 3);
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0");
$html = curl_exec($ch);
curl_close($ch);

$current_song = "Brak danych";

if ($html) {
    // Bardzo skuteczny sposób na wyciągnięcie tytułu z Icecast
    // Szukamy tekstu po "Current Song:" aż do zamknięcia komórki </td>
    if (preg_match('/Current Song:.*?<td.*?>(.*?)<\/td>/is', $html, $matches)) {
        $current_song = trim(strip_tags($matches[1]));
    }
}

// Logika zapisu
$last_saved = !empty($history) ? $history[0]['title'] : "";

if ($current_song !== "Brak danych" && $current_song !== "" && $current_song !== $last_saved) {
    $entry = [
        'title' => $current_song,
        'time' => date('H:i:s'),
        'date' => date('d.m.Y'),
        'ts' => time()
    ];
    array_unshift($history, $entry);
    $history = array_slice($history, 0, 500); // Trzymaj max 500 wpisów
    file_put_contents($history_file, json_encode(array_values($history)));
}

echo json_encode([
    'current' => $current_song,
    'history' => $history,
    'debug_status' => ($html ? "Połączono z radiem" : "Błąd połączenia z serwerem radia")
]);