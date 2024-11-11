<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $query = trim($_POST['query']);
    $region = $_POST['region'];
    $token = $_POST['token'];
    $lines = array_map("trim", explode("\n", $query));

    $url = 'https://api-sandbox.direct.yandex.ru/v4/json/';
    $headers = array('Content-Type' => 'application/json; charset=utf-8');


    $chunks = array_chunk($lines, 10);
    $reportIds = [];

    foreach ($chunks as $chunk) {
        $data = array(
            "method" => "CreateNewWordstatReport",
            "param" => array( 
                'Phrases' => $chunk,
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
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $result = curl_exec($ch);

        curl_close($ch);

        $reportId = json_decode($result, true)['data'] ?? null;
        if ($reportId) {
            $reportIds[] = $reportId;
        }
        usleep(500000);
    }

    $maxRetries = 10;  
    $retryCount = 0;   
    $results = [];

    while (!empty($reportIds)) {
        $currentBatch = array_splice($reportIds, 0, 5); 
        foreach ($currentBatch as $reportId) {
            $data = array(
                "method" => "GetWordstatReport",
                "param" => $reportId,
                'locale' => 'ru',
                'token' => $token
            );
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
            } while($retryCount < $maxRetries);


            if (isset($responseData['data']) && !empty($responseData['data'])) {
                foreach ($responseData['data'] as $dataItem) {                    
                    foreach ($dataItem['SearchedWith'] as $searchedWithItem) {
                        if (in_array($searchedWithItem['Phrase'], $lines)) {
                            if (isset($searchedWithItem['Shows'])) {
                                $results[] = [
                                    'phrase' => $searchedWithItem['Phrase'], 
                                    'shows' => $searchedWithItem['Shows'],   
                                ];
                            }
                        }
                    }
                }
            } else {
                echo "Нет данных для обработки." . PHP_EOL;
            }
            
        }
    }



    $csvFile = 'report_data.csv';
    $fp = fopen($csvFile, 'w');

    fputcsv($fp, ['phrase', 'shows'], ';');

    foreach ($results as $resultItem) {
        if (isset($resultItem['phrase']) && isset($resultItem['shows'])) {
            $phrase = mb_convert_encoding($resultItem['phrase'], 'Windows-1251', 'UTF-8');
            $shows = $resultItem['shows'];
            fputcsv($fp, [$phrase, $shows], ';');
        }
    }
    fclose($fp);

    header('Content-Type: text/csv; charset=Windows-1251');
    header('Content-Disposition: attachment; filename="'. basename($csvFile) .'"');

    readfile($csvFile);
    unlink($csvFile);
    exit;
}
?>