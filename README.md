# WMFO Archive Serving System

Copyright Nicholas Andre, 5/22/2016

This is an on-demand transcoder with cache. Requests are served by generating a separate and dissociated transcode process. The script uses a simple file flag in the cache directory (the .done files) to identify whether the transcode was successful.

It has two modes of transmission. If the transcode is .done, it simply `cat all-the files` and sends it with passthru(). In this case, we send the file size header.

If the .done file is not present, it reads the file in chunks, as available, until it sees that the .done file is created.

Requires two mounts 

* ./archives/ - the entirety of .s16 files and other archive files like .mp3s or (eventually) .aac files
* ./cache/ - a scratch directory to store transcodes. Recommend it be wiped periodically to save space (14-30 days or so).

## Versions

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
