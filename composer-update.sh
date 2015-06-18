#!/bin/bash

# 用 git submodule + 独立 git branch 来提交和保存 composer 的 vendor 目录，
# 以便在生产环境使用 composer 时减少问题

# readme:
# https://gist.github.com/zhengkai/6cc06df2467b22598dae#file-readme-md
#
# code:
# https://gist.github.com/zhengkai/6cc06df2467b22598dae#file-composer-update-sh
#
# author: Zheng Kai (zhengkai@gmail.com)
# 2015-06-12

VENDOR_DIR='tmp-vendor'
VENDOR_GIT=''
VENDOR_BRANCH='vendor'

VENDOR_CLEAN_DOTFILES=1

cd $(dirname `readlink -f $0`)

if [ -z "$VENDOR_GIT" ]; then
	VENDOR_GIT=`git config --get remote.origin.url`
fi

echo 'vendor git url: '$VENDOR_GIT' '$VENDOR_BRANCH
echo

if [ ! -d $VENDOR_DIR ]; then
	if [ -e $VENDOR_DIR ]; then
		echo 'ERROR: '$VENDOR_DIR' is not a directory'
		exit 1
	fi
	git clone --single-branch --depth 1 -b $VENDOR_BRANCH $VENDOR_GIT $VENDOR_DIR
fi

cd $VENDOR_DIR

git co .
git clean -df
if [ -z "`git branch | grep -x \"\\*\\ $VENDOR_BRANCH\"`" ]; then
	echo git checkout $VENDOR_BRANCH
	git checkout $VENDOR_BRANCH
fi
git pull --rebase

cp ../mod-composer.json ./composer.json

echo
echo '                   'run composer update
echo

composer update

if [ -d vendor ]; then
	cp -R vendor/* ./
fi

find -depth -name '.git' -type d -not -path './.git*' -not -path './vendor/*' -exec rm -rf {} \;
if [[ "$VENDOR_CLEAN_DOTFILES" -ne 0 ]]; then
	find -name '.*' -type f -not -path './.git*' -not -path './vendor/*' -exec rm {} \;
fi
find -depth -empty -type d -not -path './.git*' -exec rm -r {} \;

DATE=`date --date='TZ="Asia/Shanghai" now' +'%Y-%m-%d %H:%M:%S'`
echo $DATE

# # for debug
# echo $DATE > datetime.txt

CHANGE=`git status --porcelain | wc -l`
if [ "$CHANGE" -lt 1 ]; then
	echo
	echo '                   'no change, exit
	echo
	exit
fi

echo
echo '                   'git commit
echo

git add .
git commit -m "$DATE"
git push

COMMIT=`git log -n 1 --format=%H`

cd ../vendor/
echo
echo '                   'git checkout "$COMMIT"
echo
git fetch
git checkout "$COMMIT"
