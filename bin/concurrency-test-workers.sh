#!/usr/bin/env bash
#
# 2 workers on the same queue, no double dispatch
# Requires : stack up & DB up-to-date !
source "$(dirname "${BASH_SOURCE[0]}")/concurrency-test-common.sh"

echo "== 2 workers on the same queue =="

DELIVERY="concurrency-worker-$(date +%s%N)"
RAW="{\"delivery\":\"$DELIVERY\"}"
SIGNATURE=$(sign "$RAW")

curl -s -o /dev/null -X POST "$BASE_URL/webhook/github" \
    -H 'Content-Type: application/json' \
    -H "X-GitHub-Delivery: $DELIVERY" \
    -H "X-Hub-Signature-256: sha256=$SIGNATURE" \
    --data-raw "$RAW"

compose php bin/console messenger:consume async --limit=1 --time-limit=10 -q &
worker1=$!
compose php bin/console messenger:consume async --limit=1 --time-limit=10 -q &
worker2=$!
wait "$worker1" "$worker2"

read -r STATUS ATTEMPTS <<<"$(sql_scalar "SELECT status, attempts FROM webhook_entity WHERE external_event_id='$DELIVERY';" | tr '\t' ' ')"
compose database mysql -uapp -papp app -e "DELETE FROM webhook_entity WHERE external_event_id='$DELIVERY';" >/dev/null 2>&1

if [ "$STATUS" = "s" ] && [ "$ATTEMPTS" -eq 1 ]; then
    ok "événement traité une seule fois (status=$STATUS attempts=$ATTEMPTS)"
else
    ko "événement traité $ATTEMPTS fois, statut final '$STATUS' (attendu : status=s attempts=1)"
fi
