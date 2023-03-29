<?php

ini_set('memory_limit', '2G');
include(__DIR__ . '/../libs/LYLib.php');
include(__DIR__ . '/../config.php');
include(__DIR__ . '/Parser.php');

error_log("更新最近一年資料");
$oldt = $oldp = 0;
$list_files = [];
$start = time();
$end = time() - 86400 * 365 * 10;
for ($ym = $start; $ym > $end; $ym = strtotime('-1 month', $ym)) {
    list($term, $period) = LYLib::getTermPeriodByDate($ym);
    if ($oldt != $term or $oldp != $period) {
        $oldt = $term;
        $oldp = $period;

        $target = sprintf(__DIR__ . "/list/%02d%02d.csv", $term, $period);
        if (!file_exists($target)) {
            $content = LYLib::getListFromTermPeriod($term, $period);
            file_put_contents($target, $content);
        }
        $list_files[] = $target;
    }
}

$meet_info = new StdClass;
$meet_titles = new StdClass;

error_log("抓取 DOC 檔");
// 抓取沒有的 doc 檔
foreach ($list_files as $file) {
    $fp = fopen($file, 'r');
    error_log($file);
    $columns = fgetcsv($fp);
    if (strpos($columns[0], 'comYear') === false) {
        error_log("skip {$file}");
        continue;
    }
    $columns[0] = 'comYear';
    $docfull = array();
    while ($rows = fgetcsv($fp)) {
        $values = array_map('trim', array_combine($columns, $rows));
        unset($values['']);
        $docfilename = basename($values['docUrl']);
        $meet_id = str_replace('.doc', '', $docfilename);
        if (strpos($meet_id, 'LCIDC01_112') !== 0) {
            //continue;
        }
        if (!property_exists($meet_info, $meet_id)) {
            $values['subject'] = [$values['subject']];
            $meet_info->{$meet_id} = $values;
        } else {
            $meet_info->{$meet_id}['subject'][] = $values['subject'];
        }
        if (!array_key_Exists($docfilename, $docfull)) {
            $docfull[$docfilename] = $values['docUrl'];
        } else if ($docfull[$docfilename] != $values['docUrl']) {
            throw new Exception("{$values['docUrl']}");
        }
        if (file_exists("txtfile/{$docfilename}") and filesize("txtfile/{$docfilename}")) {
            continue;
        }
        if (file_exists("docfile/{$docfilename}") and filesize("docfile/{$docfilename}")) {
            continue;
        }
        //continue;
        error_log($values['docUrl']);
        system(sprintf("wget -O %s %s", escapeshellarg("tmp.doc"), escapeshellarg($values['docUrl'])));
        copy("tmp.doc", "docfile/{$docfilename}");
    }
}

$prev_meet_id = null;
$prev_info = null;
$prev_count = 2;
foreach ($meet_info as $meet_id => $meet_data) {
    $same_title = false;
    try {
        LYLib::parseTxtFile($meet_id . ".doc");
    } catch (Exception $e) {
        //readline("$meet_id error " . $e->getMessage());
        continue;
    }
    $file = __DIR__ . "/txtfile/{$meet_id}.doc";
    if (!file_exists($file)) {
        continue;
    }
    $info = Parser::parse(file_get_contents($file));
    foreach ($info->votes as $vote) {
        $vote_id = "{$meet_id}-{$vote->line_no}";
        $data = [
            'meet_id' => $meet_id,
            'date' => 19110000 + intval(substr($meet_data['meetingDate'], 0, 7)),
            'term' => $meet_data['term'],
            'line_no' => $vote->line_no,
        ];
        foreach (['贊成', '反對', '棄權'] as $c) {
            $data[$c] = $vote->{$c};
            unset($vote->{$c});
        }
        unset($vote->line_no);
        $data['extra'] = json_encode($vote, JSON_UNESCAPED_UNICODE);

        LYLib::dbBulkInsert('vote', $vote_id, $data);
    }
    if (!property_exists($info, 'title') or ($info->title != '國是論壇' and !strpos($info->title, '會議紀錄') and !strpos($info->title, '公聽會紀錄'))) {
        if (!property_exists($meet_info, $prev_meet_id)) {
            throw new Exception("{$prev_meet_id} not found");
        }
        if (!property_exists($meet_info, $meet_id)) {
            throw new Exception("{$meet_id} not found");
        }
        if (strpos($meet_info->{$meet_id}['subject'][0], '索引')) {
            continue;
        }
        if (strpos($meet_info->{$meet_id}['subject'][0], '質詢事項') !== false) {
            continue;
        }
        if (strpos($meet_info->{$meet_id}['subject'][0], '議事錄') !== false) {
            continue;
        }
        if (strpos($meet_info->{$meet_id}['subject'][0], '行政院答復部分') !== false) {
            continue;
        }
        if (strpos($meet_info->{$meet_id}['subject'][0], '委員質詢部分') !== false) {
            continue;
        }
        if ($meet_info->{$prev_meet_id}['meetingDate'] == $meet_info->{$meet_id}['meetingDate']) {
            $info->title = $prev_info->title . '(' . $prev_count . ')';
            $prev_count ++;
            $same_title = true;
            if (property_exists($prev_info, '時間')) {
                $info->{'時間'} = $prev_info->{'時間'};
            }
        } else {
            print_r(json_encode($meet_info->{$prev_meet_id}, JSON_UNESCAPED_UNICODE));
            echo "\n";
            print_r(json_encode($meet_info->{$meet_id}, JSON_UNESCAPED_UNICODE));
            continue;
            exit;
        }
    }
    if (!intval($meet_data['meetingDate']) and preg_match('/中華民國(\d+)年(\d+)月(\d+)日/', $info->{'時間'}, $matches)) {
        $meet_data['meetingDate'] = sprintf("%03d%02d%02d", $matches[1], $matches[2], $matches[3]);
    }
    foreach ($info as $k => $v) {
        if (in_array($k, array('person_count', 'blocks', 'block_lines', 'persons'))) {
            continue;
        }
        $meet_data[$k] = $v;
    }
    $data = [
        'title' => $meet_data['title'],
        'term' => $meet_data['term'],
        'sessionPeriod' => $meet_data['sessionPeriod'],
        'date' => 19110000 + intval(substr($meet_data['meetingDate'], 0, 7)),
        'extra' => json_encode($meet_data),
    ];

    LYLib::dbBulkInsert('meet', $meet_id, $data);

    $blocks = $info->blocks;
    $block_lines = $info->block_lines;;

    foreach ($blocks as $idx => $block) {
        if (strpos($block[0], '：')) {
            list($speaker, $block[0]) = explode('：', $block[0], 2);
        } else {
            $speaker = '';
        }
        $speaker = preg_replace('/（.*）/', '', $speaker);
        $speaker_type = 1; // other
        $term = $meet_data['term'];
        if ($n = LYLib::isLyerName($term, $speaker)) {
            $speaker = $n;
            $speaker_type = 0;
        }
        $lineno = $block_lines[$idx];
        $data = [
            'meet_id' => $meet_id,
            'term' => $meet_data['term'],
            'lineno' => $lineno,
            'speaker' => $speaker,
            'speaker_type' => intval($speaker_type),
            'content' => $block,
        ];
        LYLib::dbBulkInsert('speech', "{$meet_id}-{$lineno}", $data);
    }

    if (!$same_title) {
        $prev_meet_id = $meet_id;
        $prev_count = 2;
        $prev_info = $info;
    }
}
LYLib::dbBulkCommit();
