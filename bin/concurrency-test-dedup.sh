#!/usr/bin/env bash
#
# N parallels requests, same external_event_id -> 1 inserted DB row only.
# Requires : stack up & DB up-to-date !
source "$(dirname "${BASH_SOURCE[0]}")/concurrency-test-common.sh"

echo "== N parallels requests, same external_event_id =="

N=10
DELIVERY="concurrency-dedup-$(date +%s%N)"
RAW="{\"delivery\":\"$DELIVERY\"}"
SIGNATURE=$(sign "$RAW")

seq "$N" | xargs -P "$N" -I{} curl -s -o /dev/null -X POST "$BASE_URL/webhook/github" \
    -H 'Content-Type: application/json' \
    -H "X-GitHub-Delivery: $DELIVERY" \
    -H "X-Hub-Signature-256: sha256=$SIGNATURE" \
    --data-raw "$RAW"

COUNT=$(sql_scalar "SELECT COUNT(*) FROM webhook_entity WHERE external_event_id='$DELIVERY';")
compose database mysql -uapp -papp app -e "DELETE FROM webhook_entity WHERE external_event_id='$DELIVERY';" >/dev/null 2>&1

if [ "$COUNT" -eq 1 ]; then
    ok "$N concurrent requests -> $COUNT row"
else
    ko "$N concurrent requests -> $COUNT rows(s) (expected : 1)"
fi
