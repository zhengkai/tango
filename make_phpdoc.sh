#!/bin/bash
cd $(dirname `readlink -f $0`)
phpdoc_bin='./vendor/bin/phpdoc'
doc_dir='./doc'

if [ ! -f "$phpdoc_bin" ]; then
	echo 'no phpdoc, file = '$phpdoc_bin
	exit
fi

if [ ! -d "$doc_dir" ]; then
	echo 'no doc, dir = '$doc_dir
	exit
fi

\rm -r $doc_dir/*
$phpdoc_bin
