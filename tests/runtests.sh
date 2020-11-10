#! /usr/bin/env bash
#
#Script to run all unit-tests of phpcd
#
#Script dependencies:
#bash-version 4.4 or above
#vimunit installed parallel to phpcds install dir
#for vimunit see dsummersl/vimunit on github

declare -a TESTSTORUN

set -eu

#by default we rebuild phpcds index every-time
REBUILDIDX=1

function help(){
	echo "USAGE: runtests.sh[ options][ list of tests]"
	echo ""
	echo "-e    vim-executeable e.g. vim nvim etc"
	echo "-n    do not recreate phpcds index if exist"
	echo "-h|--help|-?    prints this help"
} 

function recreateIndex(){
	#delete and rebuild the index
	#we do this because changes on the code can affect the contents of the index
	if [ -d $DIR/fixtures/.phpcd ]; then
		if [ 1 -ne $REBUILDIDX ]; then
			#rebuild of index disabled by user
			return;
		fi
		rm -r $DIR/fixtures/.phpcd
	fi
	OLDPWD=$(pwd)
	cd $DIR/fixtures
	#@TODO find a better and more secure alternative for the 2sleep
	$VIM -c "call phpcd#Index() | 2sleep | quit" ./PHPCD/SameName/A/Same.php
	if [ ! -f $DIR/fixtures/.phpcd/extends/PHPCD_SameName_A_SuperSame.json ]
	then
		echo -e "\033[31mRebuilding PHPCD's index failed!\033[0m"
		exit 2
	fi
	cd $OLDPWD
}

function setVim(){
	if [[ -n ${VIM+x} ]]; then
		#script called with -e
			VIM=$(which $VIM)
	else
		#first look for nvim
		#nvim was used in earlier version, try to keep backward compatible
		VIM=$(which nvim)
		if [ 0 -ne $? ]; then
			#nvim not found, look for vim.nox
			VIM=$(which vim.nox)
			if [ 0 -ne $? ]; then
				VIM=$(which vim)
			fi
		fi
	fi

	if [ -z $VIM ] || [ ! -x $VIM ]; then
		echo -e "\033[31mNo vim executable found or given!\033[0m"
		exit 1
	fi
}
while [ 0 -lt $# ]; do
	case "$1" in
	'-e' )
		shift
		if [ 0 -eq $# ]; then
			echo -e "\033[31m-e musst be followed by a vim like program!\033[0m"
			exit 4
		fi
		VIM="$1"
      ;;
	'--help'|'-h'|'-?')
      help
	  exit 0
      ;;
	'-n' )
		REBUILDIDX=0
	;;
	*)
		if [[ "$1" =~ "^-" ]]; then
			echo -e "\033[31mUnknow parameter \"$1\"\033[0m"
			help
			exit 3
		fi
		TESTSTORUN+=("$1")
  esac
  shift
done

setVim

DIR=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )

# vimunit in the plugin root's parent dir (think of ~/.vim/bundle)
VU="$(dirname $(dirname $DIR))/vimunit/vutest.sh"

if [ ! -f "$VU" ]; then
	echo -e "\033[31mCould not run tests. Vimunit executeable not found at: '$VU'\033[0m"
	exit 1
fi


recreateIndex

if [[ ${TESTSTORUN[@]} ]]; then
	#run tests given as param
	cnt=${#TESTSTORUN[*]}
	for ((i = 0; i < $cnt; i++)); do
		$VU "-e $VIM -u $DIR/vimrc" ${TESTSTORUN[$i]}
	done
else
	#run all tests
	for f in "$DIR/"*.vim; do
		$VU "-e $VIM -u $DIR/vimrc" $f
	done
fi
