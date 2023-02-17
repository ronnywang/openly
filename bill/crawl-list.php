<?php

for ($p = 1; ; $p ++) {
    $url = sprintf("https://ppg.ly.gov.tw/ppg/api/v1/all-bills?size=1000&page=%d&sortCode=11", $p);
    error_log($url);
    $ret = json_decode(file_get_contents($url));
    $empty = true;
    foreach ($ret->items as $item) {
        $empty = false;
        echo json_encode($item, JSON_UNESCAPED_UNICODE) . "\n";
    }
    if ($empty) {
        break;
    }
}
