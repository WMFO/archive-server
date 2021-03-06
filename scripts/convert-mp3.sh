#!/bin/bash

file=$1

if [ -z "$1" ] ; then
	echo Usage: $0 FILENAMES_FILE
	exit 1
fi

while read input_file; do
	output_file="${input_file%.mp3}.m4a"
	# echo $input_file $output_file
	# need to redirect input to /dev/null or else stdin contention with loop
	./ffmpeg -i $input_file -c:a libfdk_aac -b:a 128k $output_file < /dev/null
	#exit 1
done < $file
