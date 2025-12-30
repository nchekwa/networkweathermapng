#!/usr/bin/env bash
set -euo pipefail

# Manual Zabbix API smoke test
# Usage:
#   ZABBIX_URL='https://your-zabbix-server/zabbix/api_jsonrpc.php' \
#   ZABBIX_USER='Admin' \
#   ZABBIX_PASS='your_password' \
#   HOST_QUERY='ke-mba02-r01-core' \
#   ./zabbix-test.sh
#
# Requirements: curl, jq

if ! command -v curl >/dev/null 2>&1; then
  echo "ERROR: curl not found" >&2
  exit 1
fi
if ! command -v jq >/dev/null 2>&1; then
  echo "ERROR: jq not found (install jq)" >&2
  exit 1
fi

ZABBIX_URL="${ZABBIX_URL:-}"
ZABBIX_USER="${ZABBIX_USER:-}"
ZABBIX_PASS="${ZABBIX_PASS:-}"
HOST_QUERY="${HOST_QUERY:-}"

ZABBIX_URL="https://zabbix.sharenetwork.io/api_jsonrpc.php"
ZABBIX_USER="Admin"
ZABBIX_PASS="zabbix"
HOST_QUERY="ke-mba02-r01-core"



if [[ -z "$ZABBIX_URL" || -z "$ZABBIX_USER" || -z "$ZABBIX_PASS" ]]; then
  echo "ERROR: Set env vars: ZABBIX_URL, ZABBIX_USER, ZABBIX_PASS" >&2
  exit 1
fi

req() {
  local method="$1"
  local params_json="$2"
  curl -sS \
    -H 'Content-Type: application/json-rpc' \
    -X POST \
    --data "{\"jsonrpc\":\"2.0\",\"method\":\"${method}\",\"params\":${params_json},\"id\":1}" \
    "$ZABBIX_URL"
}

req_auth() {
  local token="$1"
  local method="$2"
  local params_json="$3"
  curl -sS \
    -H 'Content-Type: application/json-rpc' \
    -H "Authorization: Bearer ${token}" \
    -X POST \
    --data "{\"jsonrpc\":\"2.0\",\"method\":\"${method}\",\"params\":${params_json},\"id\":1}" \
    "$ZABBIX_URL"
}

echo "== Zabbix API test =="
echo "URL: $ZABBIX_URL"

# 1) apiinfo.version
echo
echo "-- apiinfo.version --"
req apiinfo.version '{}' | jq .

# 2) user.login -> token
echo
echo "-- user.login --"
LOGIN_JSON=$(req user.login "{\"username\":$(jq -R -n --arg v "$ZABBIX_USER" '$v'),\"password\":$(jq -R -n --arg v "$ZABBIX_PASS" '$v')}" )
echo "$LOGIN_JSON" | jq .
TOKEN=$(echo "$LOGIN_JSON" | jq -r '.result // empty')
if [[ -z "$TOKEN" || "$TOKEN" == "null" ]]; then
  echo "ERROR: No token returned from user.login" >&2
  exit 2
fi

echo "Token: ${TOKEN:0:8}..."

# 3) host.get (limit 20)
echo
echo "-- host.get (limit 20) --"
HOSTS_JSON=$(req_auth "$TOKEN" host.get '{"output":["hostid","host","name","status"],"sortfield":"name","limit":20}')
echo "$HOSTS_JSON" | jq .

# Pick hostId by HOST_QUERY if provided
HOST_ID=""
if [[ -n "$HOST_QUERY" ]]; then
  echo
echo "-- host.get (search by name: $HOST_QUERY) --"
  HOST_BY_NAME=$(req_auth "$TOKEN" host.get "{\"output\":[\"hostid\",\"host\",\"name\"],\"search\":{\"name\":$(jq -R -n --arg v "$HOST_QUERY" '$v')},\"sortfield\":\"name\",\"limit\":5}")
  echo "$HOST_BY_NAME" | jq .
  HOST_ID=$(echo "$HOST_BY_NAME" | jq -r '.result[0].hostid // empty')
fi

if [[ -z "$HOST_ID" ]]; then
  HOST_ID=$(echo "$HOSTS_JSON" | jq -r '.result[0].hostid // empty')
fi

if [[ -z "$HOST_ID" ]]; then
  echo "ERROR: Could not determine hostid" >&2
  exit 3
fi

echo "Selected hostid: $HOST_ID"

# 4) hostinterface.get
echo
echo "-- hostinterface.get --"
req_auth "$TOKEN" hostinterface.get "{\"output\":[\"interfaceid\",\"ip\",\"dns\",\"port\",\"type\",\"main\"],\"hostids\":$(jq -R -n --arg v "$HOST_ID" '$v')}" | jq .

# 5) item.get: net.if.in/out keys
# We intentionally fetch more and then grep keys locally so we can see the exact key_ format.
echo
echo "-- item.get (network interface items: net.if.in/out) --"
ITEMS_JSON=$(req_auth "$TOKEN" item.get "{\"output\":[\"itemid\",\"name\",\"key_\",\"value_type\",\"units\",\"lastvalue\"],\"hostids\":$(jq -R -n --arg v "$HOST_ID" '$v'),\"search\":{\"key_\":\"net.if.\"},\"searchByAny\":true,\"limit\":500}")
echo "$ITEMS_JSON" | jq '.result | map({itemid,name,key_,units,value_type}) | .[0:50]'

echo
echo "Keys (first 50):"
echo "$ITEMS_JSON" | jq -r '.result[].key_' | head -n 50

echo
echo "Detected interface names from keys (best-effort parsing):"
echo "$ITEMS_JSON" | jq -r '.result[].key_' \
  | sed -n -E 's/^net\.if\.(in|out)\[([^\]]+)\].*$/\2/p' \
  | sed -E 's/,.*$//; s/^"//; s/"$//' \
  | sort -u \
  | head -n 200

# 6) history.get for first item (if any)
FIRST_ITEM_ID=$(echo "$ITEMS_JSON" | jq -r '.result[0].itemid // empty')
FIRST_VALUE_TYPE=$(echo "$ITEMS_JSON" | jq -r '.result[0].value_type // empty')
if [[ -n "$FIRST_ITEM_ID" && -n "$FIRST_VALUE_TYPE" ]]; then
  # value_type->history mapping (Zabbix): 0 float->0, 3 uint->3
  HISTORY_TYPE=0
  if [[ "$FIRST_VALUE_TYPE" == "3" ]]; then HISTORY_TYPE=3; fi

  TIME_FROM=$(($(date +%s) - 3600))
  echo
echo "-- history.get (last 1h) for itemid=$FIRST_ITEM_ID history=$HISTORY_TYPE --"
  req_auth "$TOKEN" history.get "{\"output\":\"extend\",\"history\":${HISTORY_TYPE},\"itemids\":[\"${FIRST_ITEM_ID}\"],\"sortfield\":\"clock\",\"sortorder\":\"DESC\",\"time_from\":${TIME_FROM},\"limit\":10}" | jq .
else
  echo
echo "-- history.get skipped (no net.if.* items found) --"
fi

echo
echo "Done."
