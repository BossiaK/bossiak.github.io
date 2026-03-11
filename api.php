<?php
// Wymuszamy zwracanie poprawnego formatu JSON bez błędów HTML
ini_set('display_errors', 0);
error_reporting(E_ALL);
header("Access-Control-Allow-Origin: *");
header('Content-Type: application/json; charset=utf-8');

$status_url = "http://82.145.41.8:33745/status.xsl";
$history_file = "history.json";

// 1. Sprawdź i utwórz plik historii, łap błędy z uprawnieniami
if (!file_exists($history_file)) {
    if (@file_put_contents($history_file, json_encode([])) === false) {
        echo json_encode(['error' => 'Brak uprawnień. Serwer nie może utworzyć pliku history.json. Nadaj CHMOD 777 lub 755 dla folderu.']);
        exit;
    }
    @chmod($history_file, 0666);
}

// 2. Pobierz dane za pomocą cURL (rozwiązuje problem z zablokowanym allow_url_fopen)
$html = '';
if (function_exists('curl_version')) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $status_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
    $html = curl_exec($ch);
    
    if (curl_errno($ch)) {
        $html = false; // Połączenie nieudane
    }
    curl_close($ch);
} else {
    // Awaryjne pobieranie (jeśli na serwerze dziwnym trafem brakuje cURL)
    $opts = [
        "http" => [
            "method" => "GET",
            "header" => "User-Agent: Mozilla/5.0\r\n",
            "timeout" => 5
        ]
    ];
    $context = stream_context_create($opts);
    $html = @file_get_contents($status_url, false, $context);
}

$current_song = "Nieznany utwór";

// Jeśli udało się pobrać HTML, wyciągamy tytuł
if ($html) {
    if (strpos($html, 'Current Song:') !== false) {
        $parts = explode('Current Song:', $html);
        if (isset($parts[1])) {
            $parts = explode('<td class="streamdata">', $parts[1]);
            if (isset($parts[1])) {
                $parts = explode('</td>', $parts[1]);
                $current_song = trim(strip_tags($parts[0]));
            }
        }
    }
}

// 3. Zarządzanie historią
$history_content = @file_get_contents($history_file);
$history = $history_content ? json_decode($history_content, true) : [];
if (!is_array($history)) $history = [];

$last_saved = !empty($history) ? $history[0]['title'] : "";

// Zapisz nowy utwór, jeśli jest poprawny i inny niż ostatni
if ($current_song !== "Nieznany utwór" && $current_song !== "" && $current_song !== $last_saved) {
    $entry = [
        'title' => $current_song,
        'time' => date('H:i:s'),
        'date' => date('d.m.Y'),
        'ts' => time()
    ];
    array_unshift($history, $entry);
    $history = array_slice($history, 0, 100); // Trzymamy max 100 ostatnich piosenek
    
    @file_put_contents($history_file, json_encode(array_values($history)));
}

// 4. Zwróć gotowe dane do przeglądarki
echo json_encode([
    'current' => $current_song,
    'history' => $history
]);
?>