#!/bin/bash
# Instala en PRODUCCIÓN el paquete que GitHub sube tras la aprobación (cron de root, cada minuto).
#
#   /var/lib/spdeploy-prod/entrada/prod/<versión>-<sha>/  backend.tgz frontend.tgz VERSION SHA256SUMS LISTO
#
# Orden (los clientes siguen en la versión actual hasta el paso 7):
#   1. Verifica el paquete.            2. Prepara la versión en releases/<versión>, aparte.
#   3. Dependencias (--no-dev).        4. Arranque y cachés de la versión nueva.
#   5. Revisa las migraciones pendientes (bloquea destructivas o pesadas sin marca).
#   6. Si hay migraciones: respaldo de la base y migrate.
#   7. CAMBIO: el enlace prod/actual apunta a la versión nueva (instantáneo) + recarga de PHP.
#   8. Revisa /api/salud. Si falla: vuelve solo a la versión anterior y avisa.
#   9. Frontend (sin borrar los archivos de versiones anteriores que tengan abiertos los usuarios).
#  10. Limpieza: quedan 3 versiones, 3 frontends y 3 respaldos previos al despliegue.
# Nunca se borran datos: storage, .env, imágenes y la base son compartidos y no están en releases/.
# Las migraciones NUNCA se deshacen automáticamente (el down() puede borrar datos).
set -eEuo pipefail
exec 9>/run/smartpyme-prod-despliegue.lock
flock -n 9 || exit 0

E=/var/lib/spdeploy-prod/entrada/prod
P=/home/smartpyme/prod
RELEASES=$P/releases
COMPARTIDO=$P/compartido
FRONT=/home/smartpyme/public_html/app
FRONT_LISTAS=$P/frontend
RESPALDOS=/root/prod/respaldos
HECHOS=/root/prod/procesados
LOG=/root/prod/despliegues.log
PHP=/opt/cpanel/ea-php84/root/usr/bin/php
COMPOSER=/usr/local/bin/composer
SALUD=https://apiunificado.smartpyme.site/api/salud
MAIL_TO="${MAIL_TO:-david.c@smartpyme.app}"
CONSERVAR=3
mkdir -p "$RESPALDOS" "$HECHOS" "$FRONT_LISTAS"

log() { echo "$(date -u +%FT%TZ) $*" | tee -a "$LOG"; }
correo() { { echo "$2"; echo; tail -n 40 "$LOG"; } | mail -s "$1" "$MAIL_TO" || true; }
fallo() {
  log "ERROR en la línea $1 (versión ${REL:-?}). Los clientes siguen en: $(readlink "$P/actual" 2>/dev/null || echo 'repositories/unificado')"
  correo "ERROR despliegue PRODUCCIÓN ${REL:-}" "El despliegue se detuvo antes de cambiar de versión (o ya volvió a la anterior)."
}
trap 'fallo $LINENO' ERR
artisan() { (cd "$1" && sudo -u smartpyme $PHP artisan "${@:2}" --no-interaction); }

# ---------- 1. Paquete más reciente completo ----------
REL=""
for d in $(ls -1t "$E" 2>/dev/null); do
  [ -f "$E/$d/LISTO" ] && { REL=$d; break; }
done
[ -n "$REL" ] || exit 0
[[ "$REL" =~ ^[0-9]{2}\.[0-9]{2}\.[0-9]{2}[a-z]?-[0-9a-f]{7}$ ]] || { log "nombre de versión inválido: $REL"; mv "$E/$REL" "$HECHOS/invalido-$REL"; false; }
PAQ="$HECHOS/$REL"
rm -rf "$PAQ"; mv "$E/$REL" "$PAQ"
log "=== PRODUCCIÓN: instalando $REL"
(cd "$PAQ" && sha256sum -c --quiet SHA256SUMS)
[ "$(cat "$PAQ/VERSION")" = "$REL" ]

R=$RELEASES/$REL
B=$R/Backend
[ ! -e "$R" ] || { log "la versión $REL ya existe en releases/, no se reinstala"; false; }
ACTUAL=$(readlink -f "$P/actual" 2>/dev/null || echo /home/smartpyme/repositories/unificado)

# ---------- 2. Código ----------
mkdir -p "$B"
tar -xzf "$PAQ/backend.tgz" -C "$B"
cp "$PAQ/VERSION" "$B/VERSION"
rm -rf "$B/storage" "$B/.env"
ln -s "$COMPARTIDO/storage" "$B/storage"
ln -s "$COMPARTIDO/.env" "$B/.env"
mkdir -p "$B/bootstrap/cache"

# ---------- 3. Dependencias ----------
if [ -d "$ACTUAL/Backend/vendor" ]; then cp -a "$ACTUAL/Backend/vendor" "$B/vendor"; fi
chown -R smartpyme:smartpyme "$R"
chown -h smartpyme:smartpyme "$B/storage" "$B/.env"
(cd "$B" && sudo -u smartpyme -H $PHP $COMPOSER install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader) >> "$LOG" 2>&1

# ---------- 4. Cachés de la versión nueva (leen el .env de producción, compartido) ----------
artisan "$B" config:cache >> "$LOG"
artisan "$B" route:cache >> "$LOG"
artisan "$B" event:cache >> "$LOG"
artisan "$B" route:list --path=api/salud >> "$LOG"   # arranca la app completa

# ---------- 5. Migraciones pendientes ----------
pendientes=()
ya=$(mysql -N -B smartpyme_prod -e "SELECT migration FROM migrations")
for f in "$B"/database/migrations/*.php; do
  n=$(basename "$f"); n=${n//.php/}   # igual que Laravel: quita todos los ".php" (hay archivos *.php.php)
  grep -qxF "$n" <<< "$ya" || pendientes+=("$f")
done
log "migraciones pendientes: ${#pendientes[@]}"

# Primera vez: el sitio todavía se sirve desde repositories/unificado. Solo se prepara la versión;
# el cambio inicial (index.php, workers, cron, demo) se hace a mano (ver ops/prod/README.md).
if [ ! -L "$P/actual" ]; then
  [ ${#pendientes[@]} -eq 0 ] || { log "primera versión con migraciones pendientes: se detiene"; false; }
  ln -sfn "$R" "$P/actual"
  log "=== PRIMERA VERSIÓN PREPARADA: $REL (prod/actual -> $REL). Falta el cambio inicial manual."
  correo "PRODUCCIÓN: primera versión $REL preparada" "Falta el cambio inicial manual. Los clientes siguen en repositories/unificado."
  exit 0
fi

if [ ${#pendientes[@]} -gt 0 ]; then
  printf '  %s\n' "${pendientes[@]##*/}" | tee -a "$LOG"
  $PHP "$B/scripts/revisar-migraciones.php" "${pendientes[@]}" 2>&1 | tee -a "$LOG"
  [ "${PIPESTATUS[0]}" = 0 ]

  # ---------- 6. Respaldo y migrate (los clientes siguen en la versión anterior) ----------
  log "respaldo de la base antes de migrar"
  umask 077
  mysqldump --single-transaction --quick --routines --triggers --hex-blob --default-character-set=utf8mb4 smartpyme_prod \
    | pigz -p 2 > "$RESPALDOS/$REL.sql.gz.part"
  zcat "$RESPALDOS/$REL.sql.gz.part" | tail -1 | grep -q "Dump completed"
  mv "$RESPALDOS/$REL.sql.gz.part" "$RESPALDOS/$REL.sql.gz"
  umask 022
  log "respaldo listo ($(du -h "$RESPALDOS/$REL.sql.gz" | cut -f1)); migrando"
  artisan "$B" migrate --force >> "$LOG" 2>&1
fi

# ---------- 7. Cambio de versión ----------
ANTERIOR=$(readlink "$P/actual" 2>/dev/null || true)
ln -sfn "$R" "$P/actual.nuevo"
mv -T "$P/actual.nuevo" "$P/actual"
systemctl reload ea-php84-php-fpm
artisan "$B" queue:restart >> "$LOG" 2>&1 || true
log "CAMBIO: actual -> $REL (antes: ${ANTERIOR:-repositories/unificado})"

# ---------- 8. Salud ----------
sano=0
for i in 1 2 3 4 5 6; do
  sleep 5
  if curl -fsS -m 15 "$SALUD" 2>/dev/null | grep -q "\"version\":\"$REL\""; then sano=1; break; fi
done
if [ $sano = 0 ]; then
  if [ -n "$ANTERIOR" ]; then
    ln -sfn "$ANTERIOR" "$P/actual.nuevo"; mv -T "$P/actual.nuevo" "$P/actual"
    systemctl reload ea-php84-php-fpm
    log "SALUD FALLÓ: se volvió a $ANTERIOR. Las migraciones aplicadas se quedan (son compatibles con la versión anterior)."
  else
    log "SALUD FALLÓ y no hay versión anterior en releases/: revisar a mano."
  fi
  correo "PRODUCCIÓN: $REL falló la revisión de salud" "Se volvió automáticamente a la versión anterior."
  exit 1
fi
log "salud OK"

# La demo usa el código de producción: sus migraciones y su config siguen a la versión actual.
(cd /home/smartpyme/ambientes/demo && flock /run/smartpyme-demo.lock sh -c \
  "sudo -u smartpyme $PHP artisan migrate --force --no-interaction && sudo -u smartpyme $PHP cachear-config.php") >> "$LOG" 2>&1 \
  || log "aviso: no se pudo actualizar la demo (producción está bien)"

# ---------- 9. Frontend ----------
t=$(mktemp -d)
tar -xzf "$PAQ/frontend.tgz" -C "$t"
[ -f "$t/index.html" ]
(cd "$t" && find . -type f ! -name '*.map' | sed 's#^\./##' | sort) > "$FRONT_LISTAS/$REL.lista"
# Primero los archivos nuevos (con hash en el nombre) y al final index.html/ngsw: nunca queda a medias.
rsync -a --exclude=/index.html --exclude='/ngsw*.json' --exclude=/.htaccess --exclude='*.map' --chown=smartpyme:smartpyme "$t/" "$FRONT/"
rsync -a --include=/index.html --include='/ngsw*.json' --exclude='*' --chown=smartpyme:smartpyme "$t/" "$FRONT/"
rm -rf "$t"
log "frontend $REL instalado"

# ---------- 10. Limpieza ----------
# Versiones de código: quedan las 3 más nuevas (nunca la actual).
ACTUAL_REAL=$(readlink -f "$P/actual")
ls -1dt "$RELEASES"/*/ 2>/dev/null | sed 's#/$##' | tail -n +$((CONSERVAR + 1)) | while read -r v; do
  [ "$(readlink -f "$v")" = "$ACTUAL_REAL" ] && continue
  rm -rf "$v"; log "versión de código eliminada: ${v##*/}"
done
# Frontend: se borran solo archivos que produjo una compilación vieja y que ya no usa ninguna de las 3 últimas.
mapfile -t listas < <(ls -1t "$FRONT_LISTAS"/*.lista)
if [ ${#listas[@]} -gt $CONSERVAR ]; then
  cat "${listas[@]:0:$CONSERVAR}" | sort -u > /tmp/front-vigentes.$$
  for vieja in "${listas[@]:$CONSERVAR}"; do
    comm -23 <(sort -u "$vieja") /tmp/front-vigentes.$$ | while read -r f; do
      case "$f" in index.html|ngsw*.json|.htaccess) continue ;; esac
      [ -f "$FRONT/$f" ] && rm -f "$FRONT/$f"
    done
    rm -f "$vieja"
  done
  rm -f /tmp/front-vigentes.$$
fi
# Respaldos previos al despliegue: quedan 3 (el diario en Drive no se toca).
ls -1t "$RESPALDOS"/*.sql.gz 2>/dev/null | tail -n +$((CONSERVAR + 1)) | xargs -r rm -f
ls -1dt "$HECHOS"/*/ 2>/dev/null | tail -n +11 | xargs -r rm -rf

log "=== PRODUCCIÓN: $REL listo"
correo "PRODUCCIÓN: $REL desplegada" "Versión $REL instalada y revisada (/api/salud OK)."
