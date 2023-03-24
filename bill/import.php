<?php

include(__DIR__ . '/../init.inc.php');
include(__DIR__ . '/Parser.php');

$list = Parser::getListFromWeb();
foreach ($list as $idx => $v) {
    list($filename, $time) = $v;
    list($billNo) = explode('.', $filename);
    error_log($idx . ' ' . $billNo);
    $mtime = filemtime(__DIR__ . "/bill-html/{$billNo}.gz");
    $content = gzdecode(file_get_contents(__DIR__ . "/bill-html/{$billNo}.gz"));
    $values = Parser::parseBillDetail($billNo, $content);
    $values->mtime = $mtime;
    LYLib::dbBulkInsert('bill', $billNo, $values);
}
LYLib::dbBulkCommit();
