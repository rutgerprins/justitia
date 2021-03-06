#!/bin/sh

# Java compile wrapper-script for 'test_solution.sh'.
# See that script for syntax and more info.
#
# This script byte-compiles with the Sun javac compiler and generates
# a shell script to run it with the java interpreter later.

SOURCE="$1"
DEST="$2"
ERROR="$3"
FLAGS="$4"
MEMLIMIT="$5"
MYDIR=`dirname "$0"`

if [ "$TERM" = cygwin ]; then
	# We are on windows, where shell scripts must be named .sh
	echo "(Using windows shell script workaround)"
	[ -f "$DEST" ] && rm "$DEST"
	DEST="$DEST.sh"
	MYDIR=`echo "$0" | sed 's/\\\\/\//g' | xargs dirname`
fi

# Compile with -verbose, stop in case of error
# This allows us to determine the name of the main class
javac -verbose $SOURCE 2>verbose.log
exitcode_compile=$?
if [ $exitcode_compile -ne "0" ]; then
	grep -v '^\[.*]$' verbose.log >$ERROR
	rm verbose.log
	exit $exitcode_compile
fi

# Find the classname in the output, and write executing script
perl $MYDIR/java_make_runner.pl "$SOURCE" $MEMLIMIT < verbose.log > $DEST 2> $ERROR
result=$?

# Done
rm verbose.log

exit $result
