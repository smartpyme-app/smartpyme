#!/bin/bash
# Restaura la copia nocturna de smartpyme_prod en smartpyme_staging (base de trabajo, solo root).
# Nunca escribe en smartpyme_prod: el dump no trae USE/CREATE DATABASE y la base destino va explícita.
set -euo pipefail
DESTINO=smartpyme_staging
F=${1:-$(ls -d /root/backups/drive/20*/ | sort | tail -1)smartpyme_prod.sql.gz}
[ "$DESTINO" != smartpyme_prod ] || exit 1
zcat "$F" | grep -qE "^(USE |CREATE DATABASE)" && { echo "el dump trae USE/CREATE DATABASE, abortado"; exit 1; }
echo "$(date -u +%FT%TZ) restaurando $F en $DESTINO"
mysql -e "DROP DATABASE IF EXISTS \`$DESTINO\`; CREATE DATABASE \`$DESTINO\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
pv -q -L 6m "$F" | zcat | sed -E "s#/\*!50013 DEFINER=\`[^\`]*\`@\`[^\`]*\` SQL SECURITY DEFINER \*/##" | mysql --database="$DESTINO"
echo "$(date -u +%FT%TZ) listo: $(mysql -N -e "select count(*) from information_schema.tables where table_schema=\"$DESTINO\"") tablas"
