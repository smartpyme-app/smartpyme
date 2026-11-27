#!/bin/bash
# Guarda el estado ACTUAL de la demo como la base a la que vuelve cada reinicio.
# Usar cuando ventas deja la demo como quiere (p. ej. después de preparar una empresa para un prospecto).
# La base anterior queda en demo-base.anterior.sql.gz.
set -euo pipefail
exec 8>/run/smartpyme-demo.lock
flock 8
umask 077
D=/root/ambientes
mysqldump --single-transaction --routines --hex-blob --default-character-set=utf8mb4 smartpyme_demo | gzip > "$D/demo-base.sql.gz.part"
gzip -t "$D/demo-base.sql.gz.part"
zcat "$D/demo-base.sql.gz.part" | tail -1 | grep -q "Dump completed"
[ -f "$D/demo-base.sql.gz" ] && mv "$D/demo-base.sql.gz" "$D/demo-base.anterior.sql.gz"
mv "$D/demo-base.sql.gz.part" "$D/demo-base.sql.gz"
echo "$(date -u +%FT%TZ) base de la demo guardada ($(du -h "$D/demo-base.sql.gz" | cut -f1))"
