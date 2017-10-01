#!/bin/bash

PORT=$1
TEMPLATE=$2

if (("$PORT" <= 0)); then
        cat << EOM
Link port to a template, create configs & start arma3server
usage: ./a3up.sh <port> [<template>]
  <port>     ...    desired game server port
  <template> ...    one of the directory entries in ./config/templates/ .
                    If not set, the last used template for given port will be used

EOM

        exit 1
fi
if (($PORT > 9999)); then
        echo "plz use a port < 10000"
        exit 2
fi

if [[ "$TEMPLATE" =~ ^[a-z0-9_-]*$ ]]; then
        echo "template name: '${TEMPLATE}'"
else
        echo "unsafe template name '${TEMPLATE}'"
        exit 3
fi

CONFIGPATH=config/servers/$PORT
TEMPLATEPATH=config/templates/$TEMPLATE
TEMPLATELINKPATH=config/templates/$PORT
LOGPATH=/var/log/arma3server
SERVEROUTFILE=$LOGPATH/$PORT-out.log
GAMEPORT=$PORT
STEAMPORT=$(($GAMEPORT - 2))
STEAMQUERYPORT=$(($GAMEPORT - 1))

umask 0013

if [[ "$TEMPLATE" == "" ]]; then
        if [[ ! -d "$CONFIGPATH" ]]; then
                echo "no template parameter, and no last config path '$CONFIGPATH' :( "
                exit 4
        fi
else
        if [[ ! -d "$TEMPLATEPATH" ]]; then
                echo "template path '$TEMPLATEPATH' does not exist"
                exit 8
        fi
        rm "$TEMPLATELINKPATH" # remove symlink
        pushd config/templates
        ln -s $TEMPLATE $PORT
        popd
fi

if [[ -f $SERVEROUTFILE ]]; then
        mv $SERVEROUTFILE $SERVEROUTFILE.1
fi
if [[ -f $SERVERLOGFILE ]]; then
        mv $SERVERLOGFILE $SERVERLOGFILE.1
fi

export GAMEPORT
export STEAMPORT
export STEAMQUERYPORT
export LOGPATH

rm -r $CONFIGPATH
mkdir $CONFIGPATH

for FILENAME in `ls -1p $TEMPLATELINKPATH | grep -v '/'`; do
        echo "substituting  $TEMPLATELINKPATH/$FILENAME into $CONFIGPATH/$FILENAME..."
        envsubst < "$TEMPLATELINKPATH/$FILENAME" > $CONFIGPATH/$FILENAME

done

if [[ -f $CONFIGPATH/mods.cfg ]]; then
        MODS=`cat $CONFIGPATH/mods.cfg | sed -E 's/^([a-z0-9])/@\1/' | tr '\n' ';' | sed -E 's/;{2,}/;/g'`
        echo "-mod=\"$MODS\"" >> $CONFIGPATH/arma3server.args
fi

BINARY="arma3server-$PORT"

echo "killing previous process..."
killall arma3server-$PORT
cp -f arma3server arma3server-$PORT
echo "starting server..."
nohup ./arma3server-$PORT -filePatching -par=$CONFIGPATH/arma3server.args -sock_host=::1 -sock_port=2$PORT -sock_log=$LOGPATH/$PORT-sock.log > $SERVEROUTFILE 2>&1 &
echo "done, me thinks."
