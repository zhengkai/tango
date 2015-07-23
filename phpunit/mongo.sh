#!/bin/bash

cd $(dirname `readlink -f $0`)

../vendor/bin/phpunit -c ./mongo_phpunit.xml.dist
