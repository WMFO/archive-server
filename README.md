# WMFO Archive Serving System

Copyright Nicholas Andre

This is an on-demand transcoder with cache. Requests are served by generating a separate and dissociated transcode process. The script uses a simple file flag in the cache directory (the .done files) to identify whether the transcode was successful.

It has two modes of transmission. If the transcode is .done, it simply `cat all-the files` and sends it with passthru(). In this case, we send the file size header. If the .done file is not present, it reads the file in chunks, as available, until it sees that the .done file is created. We also can send partial files for browser streaming plugins.

Requires two mounts 

* ./archives/ - the entirety of .s16 files and other .aac archive files
* ./cache/ - a scratch directory to store transcodes. Recommend it be wiped periodically to save space (14-30 days or so).

## Versions

### 2.0 (8/13/2018)

- Removed support for direct MP3 archive files as ours were transcoded to AAC.
- Added support for AAC (m4a container) files (requires ffmpeg dependency). AAC files will download first if present.
- Switched to UTC timestamps with conversion from ET in script (filenames now end in U to differentiate).
- FLAC and AAC still block on download; page now refreshes periodically to display progress and start download.
- We now rely on presence of conversion process (convert.sh) via pgrep to determine if the transcode is stale/was killed prematurely

### 1.4 (3/16/2017)

Full support for partial files.

Todo: reimplement "stale file" logic to actually check for presence of transcode process. File modify time keeps breaking for unknown reason even with the php cache purging. Extended file modification thresholds in the interim.

### 1.3 (3/16/2017)

Archive server will error out and provide a status message while a FLAC file is transcoding. Downloads before transcode was complete had a botched checksum and were unplayable on many devices.

Added support for partial file transcodes. Caveats/todo: this behavior doesn't work before the transcode is complete so scrolling won't work the first time any human browses to the particular archive link. We can fix this by implementing a separate partial file support while the transcode is in process (currently it will just respond with 200 and again send the file which will confuse poor HTML5 audio element). Basically, before the transcode completes the app gives an "estimated" file size and then sends the data if it's available. That means someone could scroll ahead to a point that hasn't been transcoded, at which point we will have to either stall until the transcode "catches up" to that point or terminate the connection. We'll also need reasonable error handling. Anyways I figured that scrolling most of the time was better than "broken" so I've gone ahead and deployed it.

In addition, the "correct" way of doing this (at least in the post-transcode code paths) would be to use the mod_sendfile. That's probably low priority assuming that this server rarely gets loaded too significantly.

### 1.2 (6/28/2016)

Correct logic for error recovery

### 1.1 (5/22/2016)

Fix the following issues:

* Checks .mp3 files for incomplete transcode. Experimentally (total .s16 filesize)/(cache file size) should be at or slightly less than 12.
* Script detects stale cache files (> 10 seconds since modification at script execution). Only tests if .done file not present.


### 1.0 (5/17/2016)

Supports serving in .mp3 (128kbps CBR) and .flac through SoX. First checks for the existence of legacy .mp3 archives imported from the old system. If they exist it uses those, otherwise it uses the new transcode method.

Known Issues:

* The script will hang in the event that the transcode process is killed by a crash because it waits for the .done file. It will hit the php timeout.
* If a transcode is triggered in the final hour of a broadcast, the show is still being written. It will successfully complete the transcode but the file will be incomplete, missing less than 60 minutes of the broadcast. Fix scheduled for next version which involves checking the ratio of file size of input and output.
* No provision for serving compressed files (when we squash down to HE-AAC v2 for indefinite storage)
