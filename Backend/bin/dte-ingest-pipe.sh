#!/bin/sh
# cPanel/Exim entrypoint. Do NOT pipe the .php file directly:
# cPanel runs .php as CGI and PHP prints "Content-type: text/html",
# which Exim treats as a permanent pipe failure.
DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
LOG="$DIR/../storage/logs/dte-ingest-pipe.log"
if [ -n "$DTE_INGEST_PHP" ] && [ -x "$DTE_INGEST_PHP" ]; then
    PHP="$DTE_INGEST_PHP"
elif [ -x /usr/local/bin/php ]; then
    PHP=/usr/local/bin/php
elif [ -x /usr/bin/php ]; then
    PHP=/usr/bin/php
else
    PHP=$(command -v php 2>/dev/null) || true
fi
if [ -z "$PHP" ] || [ ! -x "$PHP" ]; then
    printf '%s no php cli\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" >> "$LOG" 2>/dev/null || true
    exit 0
fi
exec "$PHP" -q -d display_errors=0 -d display_startup_errors=0 "$DIR/dte-ingest-pipe.php" "$@"
