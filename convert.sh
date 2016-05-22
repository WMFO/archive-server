#!/bin/bash

/bin/cat $1 | /usr/bin/sox -t s16 -r 48000 -c 2 - -t $2 $3

touch ${3}.done
