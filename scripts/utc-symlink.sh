#!/bin/bash

file=$1

if [ -z "$1" ] ; then
        echo Usage: $0 FILENAMES_FILE
        exit 1
fi

while read input_file; do
	input_filename=$(basename $input_file)
	input_extension=${input_filename##*.}
	input_basefilename=${input_filename%.*}
	input_directory=$(dirname $input_file)
	input_date=`echo "$input_basefilename:00:00" | sed 's/_/ /g'`
	output_filename_base=`date -d "$input_date" +@%s | xargs date -u +%Y-%m-%d_%HU -d`
	output_filename=`echo  $input_directory/$output_filename_base.$input_extension`
        #output_file=`echo "$input_file" | sed 's/-\([0-9]*\)\.m4a/_\1\.m4a/g'`
        echo $input_file $output_filename
	unlink $output_filename
        #ln -s $input_file $output_filename
        #mv $input_file $output_file
done < $file
