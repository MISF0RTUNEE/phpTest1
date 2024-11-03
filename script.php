<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $query = trim($_POST['query']);
    $region = $_POST['region'];
    $token = $_POST['token'];
    $lines = explode("\n", $query);
    $lines = array_map("trim", $lines);
}
    echo "</ul>";
$url = 'https://api-sandbox.direct.yandex.ru/v4/json/';

$headers = array(
    'Content-Type' => 'application/json; charset=utf-8'    
);

$data = array(
    "method" => "CreateNewWordstatReport",
    "param" => array( 
        'Phrases' => $lines,
        'GeoID' => array($region),
    ),
    'locale' => 'ru', 
    'token' => $token
);

$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data,JSON_UNESCAPED_UNICODE));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$result = curl_exec($ch);
curl_close($ch);


$reportId = implode(json_decode($result, true));
print_r($reportId);

$data = array(
    "method" => "GetWordstatReport",
    "param" => $reportId,
    'locale' => 'ru',
    'token' => $token
);

$maxRetries = 10;
$retryCount = 0;
do{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);


    $result = curl_exec($ch);
    curl_close($ch);

    $responseData = json_decode($result, true);

    if (isset($responseData['data'])) {
        break;
    } else {
        sleep(5);
        $retryCount++;
    }
} while ($retryCount < $maxRetries);


if (isset($responseData['data']) && !empty($responseData['data'])) {
    $csvFile = 'report_data.csv';
    $fp = fopen($csvFile, 'w');

    fputcsv($fp, ['Запрос', 'Частота']);

    $options = [
        'delimiter' => ';',
    ];
    foreach ($responseData['data'] as $dataItem) {
        if (isset($dataItem['SearchedAlso'])) {
            foreach ($dataItem['SearchedAlso'] as $searchedItem) {
                $phrase = isset($searchedItem['Phrase']) ? $searchedItem['Phrase'] : '';
                $shows = isset($searchedItem['Shows']) ? $searchedItem['Shows'] : '';
                fputcsv($fp, [$phrase, $shows], $options['delimiter']);
            }
        }
    }
    fclose($fp);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . basename($csvFile) . '"');
    

    readfile($csvFile);

    unlink($csvFile);
    exit;
} else {
    echo "Не удалось получить данные отчета.";
}
?>