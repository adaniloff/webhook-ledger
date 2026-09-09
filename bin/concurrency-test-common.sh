#!/usr/bin/env bash
#
# Utilities: shared code & tools for other scripts
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

compose() { docker compose --env-file .env --env-file .env.local exec -T "$@"; }

BASE_URL="http://localhost:${HTTP_PORT:-8080}"

ok() {
    echo "  OK   - $1"
    exit 0
}
ko() {
    echo "  FAIL - $1"
    exit 1
}

sql_scalar() {
    compose database mysql -uapp -papp app -N -e "$1" 2>/dev/null
}

sign() {
    local secret
    secret=$(compose php php -r 'echo getenv("GITHUB_WEBHOOK_SECRET");' 2>/dev/null)
    printf '%s' "$1" | openssl dgst -sha256 -hmac "$secret" | sed 's/^.* //'
}
