#!/bin/bash
# Se ejecuta solo la primera vez (cuando el volumen de datos está vacío).
# Crea la base transaccional y la analítica, y carga los respaldos que haya en docker/mariadb/dumps:
#   smartpyme_prod.sql(.gz)        -> base principal
#   smartpyme_analitica.sql(.gz)   -> base analítica
set -euo pipefail
ROOT=(mariadb -uroot -p"${MARIADB_ROOT_PASSWORD}")

"${ROOT[@]}" <<SQL
CREATE DATABASE IF NOT EXISTS \`${MARIADB_DATABASE}\`;
CREATE DATABASE IF NOT EXISTS \`smartpyme_analitica\`;
GRANT ALL PRIVILEGES ON \`${MARIADB_DATABASE}\`.* TO '${MARIADB_USER}'@'%';
GRANT ALL PRIVILEGES ON \`smartpyme_analitica\`.* TO '${MARIADB_USER}'@'%';
FLUSH PRIVILEGES;
SQL

load() {
  local db="$1" base="/dumps/$2"
  if   [ -f "$base.sql.gz" ]; then echo "Cargando $base.sql.gz en $db"; gunzip -c "$base.sql.gz" | "${ROOT[@]}" "$db"
  elif [ -f "$base.sql" ];    then echo "Cargando $base.sql en $db";    "${ROOT[@]}" "$db" < "$base.sql"
  else echo "Sin respaldo para $db ($base.sql[.gz]); queda vacía"; fi
}
load "${MARIADB_DATABASE}" smartpyme_prod
load smartpyme_analitica smartpyme_analitica
