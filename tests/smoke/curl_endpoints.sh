#!/usr/bin/env bash
# Minimal smoke: hit key endpoints and assert status or body.
# Usage: BASE_URL=http://localhost:8080 ./tests/smoke/curl_endpoints.sh
# No auth; only checks that routes respond (200/302/404 as expected).

set -e
BASE_URL="${BASE_URL:-http://localhost:8080}"

assert_status() {
  local expected=$1
  local url=$2
  local status
  status=$(curl -s -o /dev/null -w "%{http_code}" "$url")
  if [ "$status" = "$expected" ]; then
    echo "OK $url -> $status"
  else
    echo "FAIL $url -> $status (expected $expected)"
    exit 1
  fi
}

assert_contains() {
  local needle=$1
  local url=$2
  if curl -s "$url" | grep -q "$needle"; then
    echo "OK $url contains '$needle'"
  else
    echo "FAIL $url does not contain '$needle'"
    exit 1
  fi
}

echo "Smoke: $BASE_URL"
# Sign-in page is served at / when not authenticated; /auth/login redirects to /
assert_status 301 "$BASE_URL/auth/login"
assert_status 200 "$BASE_URL/"
assert_contains "Hillmeet" "$BASE_URL/"
assert_contains "Sign in" "$BASE_URL/"
echo "Smoke done."
