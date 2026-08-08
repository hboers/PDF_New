#!/usr/bin/env bash

address="127.0.0.1:8002"
url="http://${address}"

php -S "$address" &
server_pid=$!

cleanup() {
    kill "$server_pid" 2>/dev/null
}

trap cleanup EXIT INT TERM

for _ in {1..50}; do
    if curl --silent --output /dev/null "$url"; then
        break
    fi

    if ! kill -0 "$server_pid" 2>/dev/null; then
        wait "$server_pid"
        exit $?
    fi

    sleep 0.1
done

librewolf "$url" >/dev/null 2>&1 &

wait "$server_pid"
