<?php

$fp = fopen($_SERVER['argv'][1], 'r');
$seq = 0;
$total = 0;
while ($obj = json_decode(fgets($fp))) {
    $id = $obj->id;
    $total ++;
    if (!file_exists(__DIR__ . "/bill-html/{$id}.gz")) {
        $seq ++ ;
        sleep(1);
        error_log("{$seq}/{$total} fetching {$id}");
        $content = file_get_contents("https://ppg.ly.gov.tw/ppg/bills/{$id}/details");
        if (!$content) {
            error_log("https://ppg.ly.gov.tw/ppg/bills/{$id}/details failed");
            continue;
        }
        file_put_contents(__DIR__ . "/bill-html/{$id}.gz", gzencode($content));
    }
}
