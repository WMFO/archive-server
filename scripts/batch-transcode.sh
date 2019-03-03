#!/bin/bash

INPUT_DIR=/var/www/archive.wmfo.org/archives

MAX_AGE=` echo "$((365*24*3600))"`
CONVERTED_EXTENSION=m4a

for full_filename in $INPUT_DIR/2018-03*.s16 ; do
	filename=$(basename $full_filename)
        extension=${filename##*.}
        basefilename=${filename%.*}
        directory=$(dirname $full_filename)
        input_date=`echo "$basefilename:00:00" | sed 's/_/ /g' | sed 's/U//g'`
	utc_file_seconds=`date -u -d "$input_date" +%s`
	utc_current_seconds=`date -u +%s`
	seconds_age=`echo "$(($utc_current_seconds - $utc_file_seconds))"`
	output_filename="${full_filename%.s16}.m4a"
	if [ -f $output_filename ] ; then
		converted_filesize=`du -k "$output_filename" | cut -f1`
		original_filesize=`du -k "$full_filename" | cut -f1`
		ratio=`echo "$(($original_filesize / $converted_filesize))"`
		if [ $ratio -lt 13 ] ; then
			echo "File: $full_filename has $CONVERTED_EXTENSION file of sufficient size. Removing."
			mv -n $full_filename ${full_filename}.to-delete
		else
			echo "File: $full_filename has $CONVERTED_EXTENSION TOO SMALL. Removing."
			mv -n $output_filename ${output_filename}.to-delete
		fi	
	fi
	if ! [ -f $output_filename ] && [ $seconds_age -gt $MAX_AGE ] ; then
		echo "Age $seconds_age is greater than MAX_AGE, must convert: $full_filename"
        	/usr/local/bin/ffmpeg -f s16le -ar 48000 -ac 2 -i $full_filename -c:a libfdk_aac -b:a 128k $output_filename < /dev/null
	fi
done
