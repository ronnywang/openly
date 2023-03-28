<?php

if (!file_exists(__DIR__ . "/bill-doc-parsed/html")) {
	mkdir(__DIR__ . "/bill-doc-parsed/html");
	mkdir(__DIR__ . "/bill-doc-parsed/pic");
}

foreach (glob(__DIR__ . "/bill-docgz/*.doc.gz") as $idx =>  $input_file) {
	if (array_key_exists(2, $_SERVER['argv'])) {
		if ($idx % $_SERVER['argv'][2] != $_SERVER['argv'][1]) {
			continue;
		}
	}
	$filename = basename($input_file);
	if (file_exists(__DIR__ . "/bill-doc-parsed/html/{$filename}")) {
		continue;
	}
	$cmd = sprintf("zcat %s > %s", escapeshellarg($input_file), 'tmp.doc');
	system($cmd);
	$url = "https://soffice.ronny.tw/index.php";
	$cmd = sprintf("curl -X POST -F %s -F \"output_type=html\" %s",
		escapeshellarg('file=@tmp.doc'),
		escapeshellarg($url)
	);
	error_log($input_file);
	$fp = popen($cmd, 'r');
	$images = new StdClass;
	$ret = new StdClass;
	while ($line = fgets($fp)) {
		if (!$obj = json_decode($line)) {
			echo $line;
			echo 'error line';
			exit;
		}
		if ($obj[0] == 'attachments') {
			$attachment = $obj[1];
			$img_name = explode('_html_', $attachment->file_name)[1];
			file_put_contents(__DIR__ . "/bill-doc-parsed/pic/{$filename}-{$img_name}", base64_decode($attachment->content));
			$images->{$img_name} = true;
		} elseif ($obj[0] == 'content') {
			$ret->content = $obj[1];
			$content = base64_decode($obj[1]);
			preg_match_all('#<img src="([^"]+)"[^>]*"#', $content, $matches);
			$pics = [];
			foreach ($matches[1] as $idx => $file_name) {
				$img_name = explode('_html_', $file_name)[1];
				if (!preg_match('/width="(\d+)" height="(\d+)"/', $matches[0][$idx], $matches2)) {
					error_log($matches[0][$idx]);
				}
				$pics[] = [$img_name, $matches2[1], $matches2[2], $idx];
			}
			$ret->pics = $pics;
		} else {
			$ret->{$obj[0]} = $obj[1];
		}
	}

	file_put_contents(__DIR__ . "/bill-doc-parsed/html/{$filename}", gzencode(json_encode($ret)));
}
