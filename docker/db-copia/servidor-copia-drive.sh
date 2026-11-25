#!/bin/bash
# Copia nocturna de las bases de SmartPyme a Google Drive (Unidad compartida).
# Corre en el servidor como root, desde cron. Solo LEE la base: mysqldump con
# --single-transaction no bloquea tablas ni modifica datos.
#
# Requisitos (una sola vez):
#   dnf install -y rclone
#   /root/.config/rclone/drive-sa.json   -> llave de la cuenta de servicio (chmod 600)
#   remoto rclone "smartpyme-drive" apuntando a la Unidad compartida (ver docker/README.md)
#
# Cron (3:30 am El Salvador = 09:30 UTC):
#   30 9 * * * /root/bin/servidor-copia-drive.sh >> /root/backups/drive/copia.log 2>&1
set -eEuo pipefail

REMOTO="${REMOTO:-smartpyme-drive:copias-bd}"   # remoto:carpeta dentro de la Unidad compartida
LOCAL=/root/backups/drive
BASES="${BASES:-smartpyme_prod smartpyme_analitica}"
CONSERVAR="${CONSERVAR:-3}"                     # copias diarias que quedan en Drive
FECHA=$(TZ=America/El_Salvador date +%F)
DIR="$LOCAL/$FECHA"

MAIL_TO="${MAIL_TO:-david.c@smartpyme.app}"     # mismo destinatario que las alertas del ETL
LOG="${LOG:-/root/backups/drive/copia.log}"

# Si cualquier paso falla, se manda un correo (igual que run-etl.sh) y no se borra
# ninguna copia anterior de Drive.
alerta() {
  local linea="$1"
  rm -f "$DIR"/*.part 2>/dev/null || true
  {
    echo "Falló la copia diaria de la base a Google Drive ($FECHA), en la línea $linea del script."
    echo "Las copias anteriores en Drive no se tocaron."
    echo
    echo "Últimas líneas de $LOG:"
    tail -n 25 "$LOG" 2>/dev/null
  } | mail -s "ERROR copia BD a Drive SmartPyme" "$MAIL_TO"
}
trap 'alerta $LINENO' ERR

exec 9>/run/smartpyme-copia-drive.lock
flock -n 9 || { echo "$(date -u +%FT%TZ) ya hay una copia en curso"; exit 0; }

echo "$(date -u +%FT%TZ) inicio copia $FECHA"
mkdir -p "$DIR"; chmod 700 "$LOCAL" "$DIR"

for db in $BASES; do
  f="$DIR/$db.sql.gz"
  mysqldump --single-transaction --quick --routines --triggers --events \
    --hex-blob --default-character-set=utf8mb4 "$db" | pigz -p 2 > "$f.part"
  gzip -t "$f.part"                                  # si el archivo está dañado, se detiene aquí
  zcat "$f.part" | tail -1 | grep -q "Dump completed" || { echo "dump incompleto: $db"; false; }
  mv "$f.part" "$f"
  echo "$(date -u +%FT%TZ) $db $(du -h "$f" | cut -f1)"
done
( cd "$DIR" && sha256sum *.sql.gz > SHA256SUMS )

# Subir a una carpeta con la fecha y marcar cuál es la última
rclone copy "$DIR" "$REMOTO/$FECHA" --transfers 2 --retries 5 --low-level-retries 10
echo "$FECHA" | rclone rcat "$REMOTO/ULTIMA.txt"
rclone check "$DIR" "$REMOTO/$FECHA" --one-way
echo "$(date -u +%FT%TZ) subida verificada a $REMOTO/$FECHA"

# Retención: solo se borran copias viejas de ESTE proceso (carpetas AAAA-MM-DD) y solo
# después de que la de hoy quedó verificada en Drive.
rclone lsf "$REMOTO" --dirs-only | tr -d / | grep -E '^[0-9]{4}-[0-9]{2}-[0-9]{2}$' | sort -r \
  | tail -n +$((CONSERVAR + 1)) | while read -r vieja; do
      rclone purge "$REMOTO/$vieja" && echo "$(date -u +%FT%TZ) quitada de Drive: $vieja"
    done
# En el servidor solo se deja la copia de hoy
find "$LOCAL" -mindepth 1 -maxdepth 1 -type d -name '20??-??-??' ! -name "$FECHA" -exec rm -rf {} +

echo "$(date -u +%FT%TZ) fin"
