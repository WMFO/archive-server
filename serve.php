<?php
$formats = array("flac","mp3");
global $formats;
function usage($m) {
	http_response_code(400);
	echo "<h1>Error 400 - Bad Request</h1>";
	echo "<p>WMFO's multi-format on-the-fly archive serving and transcoding script, written by Nick Andre Sumer of 2016.</p><p>Usage:</p><pre>http://";
	echo $_SERVER['SERVER_NAME'] . "/serve.php?date=YYYY-MM-DDTHH:MM:SS&length=HOURS&format=FORMAT</pre>";
	echo "<p>Available formats:</p><pre>";
	var_dump($GLOBALS['formats']);
	echo "</pre>";
	die("<p>Specific error info: " . $m . "</p>");
}

function serve_incomplete($name) {
	$file = fopen($name,"r");
	if (!$file) {
		return(false);
	}
	//feof can indicate that we're at the end of a file but it's still transcoding
	//only the presence of both feof and the done file indicate completion of transfer
	while (!feof($file) || !file_exists($name . ".done")) {
		if (feof($file))
			sleep(1);
		print fread($file,4*1024);
	}
	return(true);
}

function send_file_headers($format,$ts) {
	header('Content-Description: File Transfer');
	header('Content-Type: application/octet-stream');
	header('Content-Disposition: attachment; filename="WMFO-Archive-'.strftime("%Y-%m-%d_%H.$format",$ts).'"');
	header('Expires: 0');
	header('Cache-Control: must-revalidate');
	header('Pragma: public');
}

function mult_file_size($format,$ts,$length) {
	$size = 0;
	for ($i = 0; $i < $length ; $i++) {
		$fn = strftime($format,$ts + $i*3600);
		if (file_exists('./archives/' . $fn)){
			$size += filesize('./archives/' . $fn);
		}
	}
	return $size;
}

function generate_filenames($format,$ts,$length) {
	$filenames = '';
	
	for ($i = 0; $i < $length ; $i++) {
		$fn = strftime($format,$ts + $i*3600);
		if (!file_exists('./archives/' . $fn)){
			return false;
		}
		$filenames .= " ./archives/" . $fn;
	}
	return($filenames);
}

function sanity_check($cache_file, $filesize_total, $format) {
	$cache_size = filesize($cache_file);
	$ratio = $filesize_total / $cache_size;
	if ($format == "mp3") {
		if ($ratio > 12.01) {
			return false;
		}
	}
	return true;	
}

if (!isset($_GET['date'])) {
	usage("please specify a date parameter");
}



$date = $_GET['date'];
$length = isset($_GET['length']) ? $_GET['length'] : 1;
$format = isset($_GET['format']) ? $_GET['format'] : 'mp3';

if ($length > 5)
	usage("lengths greater than 5 not supported. Please concatenate files yourself.");

$ts = strtotime($date);

$mp3filenames = generate_filenames("%Y-%m-%d-%H.mp3",$ts,$length);

//die($mp3filenames);

if ($mp3filenames !== false) {
//if ($ts + $length*3600 < strtotime("2016-05-13T11:00:00")) {
	//concatenate MP3s
	send_file_headers(".mp3",$ts);

	header('Content-Length: ' . mult_file_size("%Y-%m-%d-%H.mp3",$ts,$length));
	
	passthru("/bin/cat $mp3filenames");
	exit();

} else {
	//use new fancy transcode method
	if (!in_array($format,$formats))
		usage("format not supported");
	
	$filenames = generate_filenames("%Y-%m-%d_%H.s16",$ts,$length);

	if (!$filenames) {
		header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found", true, 404);
		die("<h1>Not found</h1>
		<p>The requested file is not in this archive.<p>
		<p>This is usually caused by requesting a file:</p><ol>
		<li>in the future.</li>
		<li>from before 1/17/2016</li>
		<li>from during a power outage</li></ol>
		<p>Contact team ops if you have any questions.</p>
		<p>PS: this error is caused if any of the files requested are unavailable.");
	}
	
	$cache_file = './cache/' . strftime("%Y-%m-%d_%H.$length.$format",$ts);

	send_file_headers($format,$ts);

	//cache implementation
	if (file_exists($cache_file . ".done")) {
		//I used this to support concatenating rather than a more complex file read job
		$filesize = mult_file_size("%Y-%m-%d_%H.s16",$ts,$length);
		if (sanity_check($cache_file,$filesize,$format)) {
			header('Content-Length: ' . filesize($cache_file));
			passthru("/bin/cat $cache_file");
			//TODO: support partial transfer requests? Or not...
			exit();
		} else { 
			//transcode too small, purge cache
			unlink($cache_file);
			unlink($cache_file . ".done");
		}
	} 
	if (file_exists($cache_file)) {
		//conversion is in progress
		if ((time() - filemtime($cache_file)) > 10) {
			//stale cache file (probably transcode interrupted)
			//experimentally this should never exceed 0 on an active transcode
			error_log($cache_file . " determined to be stale (> 120 secs, no .done file). Caused by crash or bug.");
			unlink($cache_file);
			unlink($cache_file . ".done");
		} else {
			serve_incomplete($cache_file);
			exit();
		}
	}
	//start new conversion
	//This was really annoying to get to happen async. Not sure why. This appears to work
	// (I haven't yet tried killing apache to see if it stops the conversion but that's supposedly
	// what setsid should help with).
	exec("setsid ./convert.sh \"$filenames\" $format $cache_file >/dev/null 2>/dev/null &");
	
	//wait for file to exist. If it doesn't exist next step fails
	while(!file_exists($cache_file))
		usleep(50);

	//serve conversion as it rolls out
	serve_incomplete($cache_file);
}
