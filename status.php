<?php

//current filename
date_default_timezone_set('America/New_York');
$dt_est = new DateTimeImmutable($date);
$dt_utc = $dt_est->setTimeZone(new DateTimeZone("UTC"));
$fn = './archives/' . $dt_utc->format("Y-m-d_H\U.\s16");
$dt_last = $dt_utc->sub(new DateInterval("PT1H"));
$last_fn = './archives/' . $dt_last->format("Y-m-d_H\U.\s16");

//seconds elasped in current hour
$seconds = time() % 3600;

$seconds_behind = 0;

function verify_amount_of_data($fn, $seconds, $threshold) {
	//for signed 16, amount of data should be 48000 Hz * 4 bytes/sample * seconds elasped
	clearstatcache();
	$seconds_behind = ($seconds - filesize($fn) / 48000 / 4);
	$GLOBALS['seconds_behind']  = $seconds_behind;
	//to allow for SMB and cache flushing, allow $threshold seconds
	return ($seconds_behind <= $threshold);
}

//at turn of hour, allow 120 seconds before alerting as long as the previous hour file present
if (!file_exists($fn)) {
	if ($seconds <= 120 && file_exists($last_fn)) {
		echo "OK: Current file not yet written, last file present, within threshold";
	} else {
		header($_SERVER["SERVER_PROTOCOL"]." 503 Internal Server Error", true, 503);
		echo "ERROR: Neither current nor previous recording file present.";
	}
} else {
	if (verify_amount_of_data($fn, $seconds, 120)) {
		echo "OK: System Normal<br>";
		echo "Stats: disk recording trails timestamp by $seconds_behind seconds which is within threshold";
	} else {
		header($_SERVER["SERVER_PROTOCOL"]." 503 Internal Server Error", true, 503);
		echo "ERROR: Archive file present but shorter than expected";
	}
}
