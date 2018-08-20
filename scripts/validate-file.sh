#!/bin/bash
date_adj=`date`-1HOUR
output_filename_base=`date -d "$date_adj" +@%s | xargs date -u +%Y-%m-%d_%H -d`
output_filename=`echo /home/wmfo-admin/archives/${output_filename_base}U.s16`
windows_file=`echo /home/wmfo-admin/windows-archives/${output_filename_base}W.s16`

if [ -f $output_filename ] ; then
	archive_size=`wc -c < $output_filename`
	archive_runtime=`expr $archive_size / 4 / 48000`
	windows_archive_size=`wc -c < $windows_file`
	windows_archive_runtime=`expr $windows_archive_size / 4 / 48000`
	if [ $archive_runtime -ne 3600 ] ; then
		echo "Archive runtime isn't 1 hour: $archive_runtime"
		if [ $windows_archive_runtime -eq 3600 ] ; then 
			echo "Windows archive is correct length: $windows_archive_runtime"
		fi
	fi
	exit 0
fi
echo "Error! Archive file missing!"

