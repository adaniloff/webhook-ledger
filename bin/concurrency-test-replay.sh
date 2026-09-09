#!/usr/bin/env bash
#
# 2 simultaneous replays, 1 works, the other one throws an OptimisticLockException
# Requires : stack up & DB up-to-date !
source "$(dirname "${BASH_SOURCE[0]}")/concurrency-test-common.sh"

echo "== 2 simultaneous replays =="

UUID=$(compose php php -r 'require "vendor/autoload.php"; echo (string) Symfony\Component\Uid\Uuid::v7();' 2>/dev/null)
NOW=$(date '+%Y-%m-%d %H:%M:%S')

compose database mysql -uapp -papp app -e "
    INSERT INTO webhook_entity
        (uuid, source, external_event_id, payload, headers, signature_valid, status, attempts, last_error, received_at, updated_at, version)
    VALUES
        ('$UUID', 'github', 'concurrency-replay-$UUID', '{}', '{}', 1, 'dead', 6, 'simulated failure', '$NOW', '$NOW', 1);
" 2>/dev/null

out1=$(mktemp)
out2=$(mktemp)
compose php bin/console app:webhook:replay "$UUID" 1 >"$out1" 2>&1 &
replay1=$!
compose php bin/console app:webhook:replay "$UUID" 1 >"$out2" 2>&1 &
replay2=$!

set +e
wait "$replay1"
code1=$?
wait "$replay2"
code2=$?
set -e

VERSION=$(sql_scalar "SELECT version FROM webhook_entity WHERE uuid='$UUID';")
compose database mysql -uapp -papp app -e "DELETE FROM webhook_entity WHERE uuid='$UUID';" >/dev/null 2>&1

successes=$(((code1 == 0) + (code2 == 0)))
if [ "$successes" -eq 1 ] && [ "$VERSION" -eq 2 ]; then
    rm -f "$out1" "$out2"
    ok "only one event has been replayed (final version=$VERSION)"
else
    echo "  --- replay 1 output ---"
    sed 's/^/  /' "$out1"
    echo "  --- replay 2 output ---"
    sed 's/^/  /' "$out2"
    rm -f "$out1" "$out2"
    ko "exit codes : $code1 / $code2, final version=$VERSION (expected : only one success, version=2)"
fi
