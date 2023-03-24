<?php

include(__DIR__ . '/../init.inc.php');
include(__DIR__ . '/Parser.php');
$billNo = $_SERVER['argv'][1];
$content = gzdecode(file_get_contents(__DIR__ . "/bill-html/{$billNo}.gz"));
$values = Parser::parseBillDetail($billNo, $content);
print_r($values);

