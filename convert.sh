#!/bin/bash

if [ $2 == "m4a" ] ; then
	/usr/local/bin/ffmpeg -y -f concat -safe 0 -i <(printf "file '/var/www/archive.wmfo.org/%s'\n" $1 ) -c copy $3 </dev/null 2>&1 >/dev/null
else
	/bin/cat $1 | /usr/bin/sox -t s16 -r 48000 -c 2 - -t $2 $3
fi

touch ${3}.done
