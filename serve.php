<?php
$formats = array("flac","mp3");
global $formats;
function usage($m) {
	http_response_code(400);
	echo "<h1>Error 400 - Bad Request</h1>";
	echo "<p>WMFO's multi-format on-the-fly archive serving and transcoding script, written by Nick Andre in Summer of 2016.</p><p>Usage:</p><pre>http://";
	echo $_SERVER['SERVER_NAME'] . "/serve.php?date=YYYY-MM-DDTHH:MM:SS&length=HOURS&format=FORMAT</pre>";
	echo "<p>Available formats:</p><pre>";
	var_dump($GLOBALS['formats']);
	echo "</pre>";
	die("<p>Specific error info: " . $m . "</p>");
}

function serve_incomplete($name,$seekPos = false) {
	$file = fopen($name,"r");
	if ($seekPos !== false)
	    fseek($file,$seekPos);
	if (!$file) {
		return(false);
	}
	//feof can indicate that we're at the end of a file but it's still transcoding
	//only the presence of both feof and the done file indicate completion of transfer
	while (!feof($file) || !file_exists($name . ".done")) {
		if (feof($file))
			sleep(1);
		echo fread($file,4*1024);
	}
	return(true);
}

function serve_partial($filename, $offset, $filesize) {

    if ($_SERVER['REQUEST_METHOD'] == 'HEAD') die();
    $file = fopen($filename,"r");
    fseek($file,$offset);
    if (!$file) {
        return(false);
    }
    while (!feof($file)) {
        echo fread($file,4*1024);
    }
    return(true);
}

function serve_file($transcodeIncomplete = false, $cache_file, $format, $ts, $estimatedFilesize) {
    $measuredFilesize = filesize($cache_file);
    $offset = 0;
    if (isset($_SERVER['HTTP_RANGE'])) {
        // get byte bounds of range header
        preg_match('/bytes=(\d+)-(\d+)?/', $_SERVER['HTTP_RANGE'], $matches);

        $offset = intval($matches[1]);
        //$length = isset($matches[2]) ? intval($matches[2]) - $offset : $finalFileSize - $offset - 1;
    }

    if ($offset == 0) {
        //whole file
        send_file_headers($format, $ts, false);

        if ($_SERVER['REQUEST_METHOD'] != 'HEAD') {
            if ($transcodeIncomplete) {
                if ($estimatedFilesize) {
                    header('Content-Length: ' . $estimatedFilesize);
                }
                serve_incomplete($cache_file);
            } else {
                header('Content-Length: ' . $measuredFilesize);
                readfile($cache_file);
            }
        }

    } else {
        //partial file
        $filesize = $transcodeIncomplete ? $estimatedFilesize : $measuredFilesize;
        $netSize = $filesize - $offset;
        send_file_headers($format, $ts, true);
        header('Content-Length: ' . $netSize);
        header("Content-Range: bytes " . $offset . '-' . ($filesize - 1) . '/' . $filesize);
        if ($_SERVER['REQUEST_METHOD'] != 'HEAD') {
            if ($transcodeIncomplete) {
                if ($offset > $estimatedFilesize)
                    die("too big");
                if ($measuredFilesize < $offset) {
                    while (filesize($cache_file) < $offset) {
                        sleep(5);
                        clearstatcache();
                    }
                }
                serve_incomplete($cache_file,$offset);
            } else {
                serve_partial($cache_file, $offset, $measuredFilesize);
            }

        }
    }
    exit();
}

function send_file_headers($format,$ts,$partial) {
    $type = $format == "mp3" ? "mpeg" : "flac";
    if ($partial) {
        http_response_code(206);
        header("Content-Type: audio/$type");
        header('Accept-Ranges: bytes');
    } else {
        header('Content-Description: File Transfer');
        header("Content-Type: application/$type");
        header('Content-Disposition: attachment; filename="WMFO-Archive-' . strftime("%Y-%m-%d_%H.$format", $ts) . '"');
        header('Expires: 0');
        header('Accept-Ranges: bytes');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
    }
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
		    error_log("Failed sanity check: $cache_file has original $filesize_total and converted $cache_size with ratio $ratio");
			return false;
		}
	}
	return true;	
}

function estimate_content_length($filesize_total, $format) {
	if ($format == "mp3") {
		return $filesize_total / 12 + 384;
	}
	return false;
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

if ($mp3filenames !== false) {
	//concatenate MP3s
	send_file_headers(".mp3",$ts,false);

	header('Content-Length: ' . mult_file_size("%Y-%m-%d-%H.mp3",$ts,$length));
	
	passthru("/bin/cat $mp3filenames");
	exit();

}
//use new fancy transcode method
if (!in_array($format,$formats))
    usage("format not supported");

$filenames = generate_filenames("%Y-%m-%d_%H.s16",$ts,$length);

if (!$filenames) {
    http_response_code(404);
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

$originalFilesize = mult_file_size("%Y-%m-%d_%H.s16",$ts,$length);
$estimatedFilesize = estimate_content_length($originalFilesize,$format);

//check whether .done file exists (indicates completed transcode for this time & duration)
clearstatcache();
if (file_exists($cache_file . ".done")) {
    // verify whether file size is appropriate
    // e.g. if someone tried to download the archive mid way through a show, the result would be shorter
    if (sanity_check($cache_file,$originalFilesize,$format)) {

        serve_file(false,$cache_file,$format,$ts,$estimatedFilesize);
        exit();
    } else {
        unlink($cache_file);
        unlink($cache_file . ".done");
    }
}

//atomic file locking to avoid all hell breaking loose
$cache_file_existed = FALSE;
$f = @fopen($cache_file,'x');
if ($f === FALSE) { //fopen 'x' mode returns FALSE if file exists
    $cache_file_existed = TRUE;
} else {
    fclose($f);
}

if ($cache_file_existed)
{
    //conversion is in progress
    clearstatcache();
    $filemtime = filemtime($cache_file);
    $time_delta = time() - $filemtime;
    $mp3_mtime_error = ($time_delta > 600) && $format == "mp3";
    $flac_mtime_error = ($time_delta  > 1200) && $format == "flac";
    // we should probably have a better way of doing this that checks for convert process
    if ($mp3_mtime_error || $flac_mtime_error) {
        //stale cache file (probably transcode interrupted)
        //experimentally this should never exceed 0 on an active transcode for mp3 but not flac
        $currentFilesize = filesize($cache_file);
        $doneExists = file_exists($cache_file . ".done") ? "yes" : "no";
        error_log("$cache_file determined to be stale (> 10 secs, no .done file). Caused by crash or bug. Time delta = $time_delta, filemtime = $filemtime, currentFilesize = $currentFilesize, doneExists = $doneExists");
        unlink($cache_file);
    } else {
        if ($format == "flac") {
            $percent = round(filesize($cache_file) * $length * 100 / 419846856);
            die("<p>The server is hard at work converting your archive into FLAC. Please wait for this to complete and refresh the page to download the file.</p><p>The conversion is roughly $percent % complete.</p><p>For an in-depth discussion of why this happens, see <a href='https://groups.google.com/d/msg/wmfo-ops/HJN-B0G6GpE/u5yFT47dBQAJ'>this ops list post.</a></p>");
        }

        serve_file(true, $cache_file, $format, $ts, $estimatedFilesize);
        exit();
    }
}
//start new conversion
//This was really annoying to get to happen async. Not sure why. This appears to work
// (I haven't yet tried killing apache to see if it stops the conversion but that's supposedly
// what setsid should help with).

exec("setsid ./convert.sh \"$filenames\" $format $cache_file >/dev/null 2>/dev/null &");
if ($format == "flac") {
    die("<p>We've begun converting your file to FLAC. This takes a minute or so per hour of archive. Refresh the page to view conversion status; the file will download if it's done converting.</p><p>For an in-depth discussion of why this happens, see <a href='https://groups.google.com/forum/#!topic/wmfo-ops/HJN-B0G6GpE/u5yFT47dBQAJ'>this ops list post.</a></p>");
}

//serve conversion as it rolls out
serve_file(true,$cache_file,$format,$ts,$estimatedFilesize);
