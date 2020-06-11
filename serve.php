<?php
$formats = array("flac","mp3");
global $formats;
function usage($m) {
	http_response_code(400);
	echo "<h1>Error 400 - Bad Request</h1>";
	echo "<p>Details: $m</p><hr>";
	echo "<p>WMFO's multi-format on-the-fly archive serving and transcoding script, written by Nick Andre in Summer of 2016.</p><p>Usage:</p><pre>http://";
	echo $_SERVER['SERVER_NAME'] . "/serve.php?date=YYYY-MM-DDTHH:MM:SS&length=HOURS&format=FORMAT</pre>";
	echo "<p>Available formats:</p><pre>";
	var_dump($GLOBALS['formats']);
	echo "</pre>";
	echo "<p>Archives older than approximately 1 year are compressed to AAC at 128 kbps and will be served in that format.</p>";
	die("<p>Please try again.</p>");
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

function serve_file($transcodeIncomplete = false, $cache_file, $format, $dt_est, $estimatedFilesize) {
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
        send_file_headers($format, $dt_est, false);

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
        send_file_headers($format, $dt_est, true);
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

function send_file_headers($format,$dt_est,$partial) {
    if ($format == "mp3") {
        $type = "mpeg";
    } else {
        $type = $format;
    }
    if ($partial) {
        http_response_code(206);
        header("Content-Type: audio/$type");
        header('Accept-Ranges: bytes');
    } else {
        header('Content-Description: File Transfer');
        header("Content-Type: audio/$type");
        header('Content-Disposition: attachment; filename="WMFO-Archive-' . $dt_est->format("Y-m-d_H.") . "$format\"");
        header('Expires: 0');
        header('Accept-Ranges: bytes');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
    }
}

function mult_file_size($format,$dt,$length) {
	$size = 0;
	for ($i = 0; $i < $length ; $i++) {
		$fn = $dt->add(new DateInterval("PT" . $i . "H"))->format($format);
		if (file_exists('./archives/' . $fn)){
			$size += filesize('./archives/' . $fn);
		}
	}
	return $size;
}

function generate_filenames($format,$dt,$length) {
	$filenames = '';
	
	for ($i = 0; $i < $length ; $i++) {
		//apparently this is the santione way to add with PHP dt ?XD
		$fn = $dt->add(new DateInterval("PT" . $i . "H"))->format($format);
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
	if ($format == "mp3" && $ratio > 12.01) {
		error_log("Failed sanity check: $cache_file has original $filesize_total and converted $cache_size with ratio $ratio");
		return false;
	} else if ($format == "m4a" && $ratio > 1.1) {
		error_log("Failed m4a sanity: ratio: $cache_file has $ratio, filesize_total: $filesize_total, cache_size: $cache_size");
		return false;
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
date_default_timezone_set('America/New_York');

// We store files in UTC due to the DST shenanigans which result in differing-length files when the clocks shuffle.
$dt_est = new DateTimeImmutable($date);
$dt_utc = $dt_est->setTimeZone(new DateTimeZone("UTC"));

// first check for m4a compressed files
$filenames = generate_filenames("Y-m-d_H\U.\m4\a",$dt_utc,$length);

$originalFilesize = 0;

if ($filenames !== false) {
	// This means we found M4A files; serve them and concatenate M4As
	$originalFilesize = mult_file_size("Y-m-d_H\U.\m4\a",$dt_utc,$length);

	if ($length == 1) {
		// If we have a request for a single hour, we just send the whole file
		send_file_headers("m4a",$dt_est,false);
		header('Content-Length: ' . $originalFilesize);
		passthru("/bin/cat $filenames");
		exit();
	}
	// This overrides the original format preference.
	// TODO: swap this to send s16 if available.
	$format = "m4a";	
} else {
	// Check to see if we support the requested format
	if (!in_array($format,$formats))
	    usage("format not supported");
	// check for s16 files
	$filenames = generate_filenames("Y-m-d_H\U.\s16",$dt_utc,$length);
	
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
	$originalFilesize = mult_file_size("Y-m-d_H\U.\s16",$dt_utc,$length);
}
// If we haven't finished transcoding the file, we need to bulshit a value for the output size
$estimatedFilesize = estimate_content_length($originalFilesize,$format);
$cache_file = './cache/' . $dt_utc->format("Y-m-d_H\U"). ".$length.$format";

//check whether .done file exists (indicates completed transcode for this time & duration)
clearstatcache();
if (file_exists($cache_file . ".done")) {
    // verify whether file size is appropriate
    // e.g. if someone tried to download the archive mid way through a show, the result would be shorter
    if (sanity_check($cache_file,$originalFilesize,$format)) {
	// In this case, we have a .done file which we write when transcode finishes
        serve_file(false,$cache_file,$format,$dt_est,$estimatedFilesize);
        exit();
    } else {
	// We get here when the ratio between the s16 file and the compressed result is insufficient.
	// The most likely scenario is that someone requested an archive before the file was totally written to disk (i.e. before the show ended)
	// This doesn't happen when we get killed before the transcode is complete, which would result in no .done file
        unlink($cache_file);
        unlink($cache_file . ".done");
    }
}

atomic:
// atomic file locking to avoid all hell breaking loose
// This ensures that only one transcode happens at a time, and all other requests are served the same file
$cache_file_existed = FALSE;
$f = @fopen($cache_file,'x');
if ($f === FALSE) { //fopen 'x' mode returns FALSE if file exists
    $cache_file_existed = TRUE;
} else {
    fclose($f);
}

if ($cache_file_existed)
{
    // If everything is going to plan, this means we should find a conversion script running. We check:
    $pid = exec("pgrep -xf \"/bin/bash ./convert.sh $filenames $format $cache_file\"", $pida, $code);
    if ($code == 1) {
        // We didn't find a transcode process for this file. That means it died. We now remove the file and restart.
        error_log("$cache_file determined to be stale. Something broke.");
        unlink($cache_file);
        goto atomic;
    } else {
	// Transcode appears to be transcoding away in the background, we now continue:
        if ($format == "flac" || $format == "m4a") {
	    // Try as I might, I couldn't get the m4a or flac containers to work properly by serving incrementally. We use this clever stall page which refreshes itself.
            $percent = round(filesize($cache_file) * $length * 100 / 419846856);
            die("<html><head><meta http-equiv=\"refresh\" content=\"5\"></head><body><p>The server is hard at work converting your archive into FLAC. The page will refresh periodically to update progress.</p><p>The conversion is roughly $percent % complete.</p><p>For an in-depth discussion of why this happens, see <a href='https://groups.google.com/d/msg/wmfo-ops/HJN-B0G6GpE/u5yFT47dBQAJ'>this ops list post.</a></p></body></html>");
        }
	// If we got here, we are all set to serve the partial file. This is supported only for mp3 at present.
        serve_file(true, $cache_file, $format, $dt_est, $estimatedFilesize);
        exit();
    }
}
// start new conversion
//	This was really annoying to get to happen async -- something to do with the output rdirection. This works now.
exec("setsid ./convert.sh \"$filenames\" $format $cache_file >/dev/null 2>/dev/null &");
if ($format == "flac" || $format == "m4a") {
    // Same as above; the file will be corrupt on download. We stall here and refresh. Next time we will hit above and provide perentage.
    die("<html><head><meta http-equiv=\"refresh\" content=\"5\"></head><body><p>We've begun converting your file to '$format'. This takes a minute or so per hour of archive. Hang tight and this page will refresh with a progress update.</p><p>For an in-depth discussion of why this happens, see <a href='https://groups.google.com/forum/#!topic/wmfo-ops/HJN-B0G6GpE/u5yFT47dBQAJ'>this ops list post.</a></p></body></html>");
}

// The newly-begun-transcoding file is now going to be served. This function will wait for the file to appear if there's a lag.
serve_file(true,$cache_file,$format,$dt_est,$estimatedFilesize);
