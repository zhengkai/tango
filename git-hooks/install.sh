#!/bin/bash

cd $(dirname `readlink -f $0`)

DIR=$(basename `pwd`)

cd ../.git/hooks/

ln -s ../../$DIR/pre-commit .
