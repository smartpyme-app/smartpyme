#!/bin/bash
# Deja la DEMO como su base guardada (/root/ambientes/demo-base.sql.gz).
# Corre cada domingo 2:00 am (El Salvador) y cuando se pide desde GitHub (acción "reiniciar-demo").
# Solo toca smartpyme_demo; el nombre está fijo y se valida.
set -euo pipefail
exec 8>/run/smartpyme-demo.lock
flock 8

BASE=smartpyme_demo
COPIA=/root/ambientes/demo-base.sql.gz
PHP=/opt/cpanel/ea-php84/root/usr/bin/php
[ "$BASE" = smartpyme_demo ] || exit 1
[ -s "$COPIA" ] || { echo "no existe $COPIA"; exit 1; }
gzip -t "$COPIA"

echo "$(date -u +%FT%TZ) reiniciando $BASE desde $COPIA"
mysql -e "DROP DATABASE IF EXISTS \`$BASE\`; CREATE DATABASE \`$BASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
zcat "$COPIA" | mysql --database="$BASE"
mysql -e "GRANT ALL PRIVILEGES ON \`$BASE\`.* TO \`smartpyme_demo\`@\`localhost\`"

cd /home/smartpyme/ambientes/demo
sudo -u smartpyme $PHP artisan migrate --force --no-interaction   # por si producción ya tiene migraciones nuevas
sudo -u smartpyme $PHP artisan cache:clear
echo "$(date -u +%FT%TZ) demo reiniciada"
