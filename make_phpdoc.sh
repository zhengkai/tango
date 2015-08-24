#!/bin/bash

cd $(dirname `readlink -f $0`)
phpdoc_bin='./vendor/bin/phpdoc'
doc_dir='./doc'

set -e

if [ ! -f "$phpdoc_bin" ]; then
	echo 'no phpdoc, file = '$phpdoc_bin
	exit
fi

if [ ! -d "$doc_dir" ]; then
	echo 'no doc, dir = '$doc_dir
	exit
fi

chk_file=($doc_dir/*)
if [ ${#chk_file[*]} -gt 1 ]; then
	rm -r $doc_dir/*
fi

if [[ "$1" == "-v" ]]; then
	$phpdoc_bin | grep '\[37;41m'
	exit;
else
	error_num=`$phpdoc_bin | grep '\[37;41m' | wc -l`
fi
rm -r $doc_dir/phpdoc-cache-*

echo
echo "phpdoc"
if [ $error_num -ge 1 ]; then
	echo
	$phpdoc_bin | grep -e '^Parsing \|37;41m'
	echo
	echo 'error = '$error_num
	exit 1
else
	echo 'no error'
fi
