#!/bin/bash

file=$1

if [ -z "$1" ] ; then
        echo Usage: $0 FILENAMES_FILE
        exit 1
fi

while read input_file; do
        output_file=`echo "$input_file" | sed 's/-\([0-9]*\)\.m4a/_\1\.m4a/g'`
        echo $input_file $output_file
        mv $input_file $output_file
done < $file
