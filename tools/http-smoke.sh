#!/bin/sh
set -eu

test_root="/tmp/flexilist-http-$$"
mkdir -p "$test_root/data" "$test_root/rate-limits"
export FLEXILIST_DATA_DIR="$test_root/data"
export FLEXILIST_RATE_LIMIT_DIR="$test_root/rate-limits"
export FLEXILIST_BACKEND_URL="http://127.0.0.1:18089"
export FLEXILIST_TEST_URL="http://127.0.0.1:18089"

server_pid=''
cleanup() {
    if [ -n "$server_pid" ]; then
        kill "$server_pid" 2>/dev/null || true
    fi
    rm -rf "$test_root"
}
trap cleanup EXIT INT TERM

php -S 127.0.0.1:18089 -t public public/index.php >"$test_root/server.log" 2>&1 &
server_pid=$!
sleep 1
php tests/http-smoke.php

