#!/bin/sh

set -eu

backup() {
    temporary_dump="$(mktemp /tmp/auction-bot-postgres.XXXXXX.dump)"
    trap 'rm -f "$temporary_dump"' EXIT

    pg_dump --format=custom --no-owner --no-privileges --file "$temporary_dump"
    restic snapshots >/dev/null 2>&1 || restic init
    restic backup "$temporary_dump" --tag postgres --tag auction-bot
    restic forget --keep-daily 7 --keep-weekly 4 --keep-monthly 6 --prune

    rm -f "$temporary_dump"
    trap - EXIT
}

while :; do
    backup || true
    sleep 24h
done
