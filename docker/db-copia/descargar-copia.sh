#!/bin/bash
# Descarga la última copia de la base desde Google Drive y recrea tu base local de Docker.
# Uso (desde la carpeta del proyecto):  ./docker/db-copia/descargar-copia.sh
# Requisito una vez: rclone instalado y el remoto "smartpyme-drive" configurado con TU cuenta
# de Google (ver docker/README.md). No necesita ninguna llave del servidor.
set -euo pipefail
cd "$(dirname "$0")/../.."

REMOTO="${REMOTO:-smartpyme-drive:copias-bd}"
DESTINO=docker/mariadb/dumps

command -v rclone >/dev/null || { echo "Falta rclone: https://rclone.org/install/"; exit 1; }
FECHA=$(rclone cat "$REMOTO/ULTIMA.txt" | tr -d '[:space:]')
[ -n "$FECHA" ] || { echo "No encontré ULTIMA.txt en $REMOTO"; exit 1; }
echo "Última copia: $FECHA"

rclone copy "$REMOTO/$FECHA" "$DESTINO" --progress --include "*.sql.gz" --include SHA256SUMS
( cd "$DESTINO" && { command -v sha256sum >/dev/null && sha256sum -c SHA256SUMS || shasum -a 256 -c SHA256SUMS; } )

echo
echo "Esto BORRA tu base local de Docker (no la de producción) y la vuelve a crear con la copia del $FECHA."
read -r -p "¿Continuar? Escribe si: " ok
[ "$ok" = "si" ] || { echo "Cancelado. Los archivos quedaron en $DESTINO."; exit 0; }

docker compose down -v
docker compose up -d mariadb redis
echo "Cargando la copia (varios minutos)..."
# La imagen de MariaDB carga los respaldos en un arranque temporal y al terminar escribe
# "init process done"; recién ahí la base está lista.
until docker compose logs mariadb 2>&1 | grep -q "init process done"; do sleep 10; printf "."; done; echo
until docker compose exec -T mariadb healthcheck.sh --connect --innodb_initialized >/dev/null 2>&1; do sleep 3; done
docker compose up -d
docker compose exec app php artisan migrate --force
echo "Listo: base local con la copia del $FECHA."
