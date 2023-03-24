<?php

include(__DIR__ . '/../init.inc.php');
include(__DIR__ . '/Parser.php');

$list = Parser::getListFromWeb();
$ret = LYLib::dbQuery("/bill/_search?format=json&human", "GET", json_encode([
    'size' => 0,
    'aggs' => [
        'max_mtime' => [ 'max' => [ 'field' => 'mtime']],
    ],
]));
$max_value = json_decode($ret)->aggregations->max_mtime->value;

foreach ($list as $idx => $v) {
    list($filename, $time) = $v;
    if ($time < $max_value) {
        continue;
    }
    list($billNo) = explode('.', $filename);
    error_log($idx . ' ' . $billNo);
    $mtime = filemtime(__DIR__ . "/bill-html/{$billNo}.gz");
    $content = gzdecode(file_get_contents(__DIR__ . "/bill-html/{$billNo}.gz"));
    $values = Parser::parseBillDetail($billNo, $content);
    $values->mtime = $mtime;
    LYLib::dbBulkInsert('bill', $billNo, $values);
}
LYLib::dbBulkCommit();
