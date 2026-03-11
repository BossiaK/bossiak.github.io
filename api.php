<?php
header("Access-Control-Allow-Origin: *");
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Europe/Warsaw');

$status_url = "https://stream.dxpoland.eu/status.xsl";
$history_file = "history.json";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $status_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
$html = curl_exec($ch);
curl_close($ch);

$current_song = "Nieznany utwór";

if ($html) {
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    $xpath = new DOMXPath($dom);
    $nodes = $xpath->query("//td[contains(text(), 'Currently playing') or contains(text(), 'Current Song')]/following-sibling::td[1]");
    if ($nodes->length > 0) {
        $current_song = trim($nodes->item(0)->nodeValue);
    }
}

$history = [];
if (file_exists($history_file)) {
    $history = json_decode(file_get_contents($history_file), true) ?: [];
}

$limit_date = date('Y-m-d', strtotime('-7 days'));
$history = array_filter($history, function($item) use ($limit_date) {
    return $item['date'] >= $limit_date;
});
$history = array_values($history);

$last_title = !empty($history) ? $history[0]['title'] : "";

if (!empty($current_song) && $current_song !== "Nieznany utwór" && $current_song !== $last_title) {
    array_unshift($history, [
        'title' => $current_song,
        'time'  => date('H:i:s'),
        'date'  => date('Y-m-d')
    ]);
    file_put_contents($history_file, json_encode($history, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

$dates = array_unique(array_column($history, 'date'));
sort($dates);
$dates = array_reverse($dates);

echo json_encode([
    'current' => $current_song,
    'history' => $history,
    'available_dates' => array_values($dates)
], JSON_UNESCAPED_UNICODE);