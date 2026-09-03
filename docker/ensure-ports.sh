#!/usr/bin/env bash
set -euo pipefail

ENV_LOCAL=".env.local"

is_port_free() {
  ! (exec 3<>/dev/tcp/127.0.0.1/"$1") 2>/dev/null
}

find_free_port() {
  local port=$1
  while ! is_port_free "$port"; do
    port=$((port + 1))
  done
  echo "$port"
}

if [ -f "$ENV_LOCAL" ] && grep -q "^HTTP_PORT=" "$ENV_LOCAL" 2>/dev/null; then
  exit 0
fi

HTTP_PORT=$(find_free_port 8080)
DB_PORT=$(find_free_port 3306)

{
  echo "HTTP_PORT=$HTTP_PORT"
  echo "DB_PORT=$DB_PORT"
} >> "$ENV_LOCAL"

echo "Ports assignés (.env.local) : HTTP_PORT=$HTTP_PORT DB_PORT=$DB_PORT"
