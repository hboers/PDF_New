#!/usr/bin/env bash
# SPDX-License-Identifier: MIT

address="127.0.0.1:8002"
url="http://${address}"
script_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
browser_pid=""
profile_dir=""

php -S "$address" &
server_pid=$!

cleanup() {
    trap - EXIT INT TERM

    if [[ -n "$browser_pid" ]]; then
        kill "$browser_pid" 2>/dev/null
        wait "$browser_pid" 2>/dev/null
    fi

    kill "$server_pid" 2>/dev/null
    wait "$server_pid" 2>/dev/null

    if [[ "$profile_dir" == /tmp/pdf-new-librewolf.* && -d "$profile_dir" ]]; then
        rm -rf -- "$profile_dir"
    fi
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

profile_dir="$(mktemp -d /tmp/pdf-new-librewolf.XXXXXX)"
cp -R "$script_dir/librewolf-profile/." "$profile_dir/"

librewolf \
    --new-instance \
    --profile "$profile_dir" \
    --new-window "$url" \
    >/dev/null 2>&1 &
browser_pid=$!

echo "Die Anwendung wurde im Browser gestartet."

# Das Skript endet, sobald entweder das Browserfenster geschlossen wird oder
# der PHP-Server unerwartet beendet wird. cleanup beendet den jeweils anderen.
wait -n "$browser_pid" "$server_pid"
