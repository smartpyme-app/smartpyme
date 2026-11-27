#!/bin/bash
# Instala lo que GitHub Actions deja en /var/lib/spdeploy/entrada (corre como root cada minuto).
#
# La llave del CI solo puede ESCRIBIR en esa carpeta (rrsync -wo): no puede leer, ni ejecutar,
# ni tocar producción. Este script es el único que instala, y solo en QA y DEMO.
#
#   entrada/qa/<sha>/              backend.tgz, frontend.tgz, SHA256SUMS, LISTO  -> QA
#   entrada/demo/<sha>/            frontend.tgz, SHA256SUMS, LISTO               -> frontend DEMO
#   entrada/demo-reiniciar/<id>/   LISTO                                         -> reiniciar-demo.sh
set -eEuo pipefail
exec 9>/run/smartpyme-despliegues.lock
flock -n 9 || exit 0

E=/var/lib/spdeploy/entrada
HECHOS=/root/ambientes/procesados
LOG=/root/ambientes/despliegues.log
PHP=/opt/cpanel/ea-php84/root/usr/bin/php
COMPOSER=/usr/local/bin/composer
MAIL_TO="${MAIL_TO:-david.c@smartpyme.app}"
mkdir -p "$HECHOS"

log() { echo "$(date -u +%FT%TZ) $*" >> "$LOG"; }
fallo() {
  log "ERROR en la línea $1"
  { echo "Falló un despliegue a QA/DEMO (línea $1). Producción no se toca desde aquí."; echo; tail -n 30 "$LOG"; } \
    | mail -s "ERROR despliegue QA/DEMO SmartPyme" "$MAIL_TO" || true
}
trap 'fallo $LINENO' ERR

# Paquete más reciente que ya terminó de subir (LISTO se sube al final). Los anteriores se descartan.
tomar() {
  local dir="$1" ultimo=""
  for d in $(ls -1t "$dir" 2>/dev/null); do
    if [ -f "$dir/$d/LISTO" ]; then ultimo="$d"; break; fi
  done
  [ -n "$ultimo" ] || return 1
  local destino="$HECHOS/$(basename "$dir")-$(date -u +%Y%m%d%H%M%S)-$ultimo"
  mv "$dir/$ultimo" "$destino"
  for d in $(ls -1 "$dir" 2>/dev/null); do   # paquetes completos más viejos: ya no sirven
    [ -f "$dir/$d/LISTO" ] && mv "$dir/$d" "$HECHOS/descartado-$(basename "$dir")-$d"
  done
  echo "$destino"
}

verificar() { (cd "$1" && sha256sum -c --quiet SHA256SUMS); }

instalar_frontend() {  # $1 = paquete, $2 = carpeta pública
  local t; t=$(mktemp -d)
  tar -xzf "$1/frontend.tgz" -C "$t"
  [ -f "$t/index.html" ] || { log "frontend sin index.html"; false; }
  rsync -a --delete --exclude=/.htaccess --exclude='*.map' --chown=smartpyme:smartpyme "$t/" "$2/"
  rm -rf "$t"
}

# ---------- QA ----------
if p=$(tomar "$E/qa"); then
  sha=${p##*-}
  log "QA: instalando $sha"
  verificar "$p"
  Q=/home/smartpyme/repositories/qa/Backend
  t=$(mktemp -d)
  tar -xzf "$p/backend.tgz" -C "$t"
  [ -f "$t/artisan" ] && [ -f "$t/composer.lock" ] || { log "QA: backend incompleto"; false; }
  cambio_lock=1; cmp -s "$t/composer.lock" "$Q/composer.lock" && cambio_lock=0
  rsync -a --delete --exclude=/vendor --exclude=/storage --exclude=/bootstrap/cache --exclude=/.env \
    --chown=smartpyme:smartpyme "$t/" "$Q/"
  rm -rf "$t"
  cd "$Q"
  if [ $cambio_lock = 1 ]; then
    log "QA: composer.lock cambió, composer install"
    sudo -u smartpyme -H $PHP $COMPOSER install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader >> "$LOG" 2>&1
  else
    sudo -u smartpyme -H $PHP $COMPOSER dump-autoload --optimize --no-scripts --no-interaction >> "$LOG" 2>&1
  fi
  cd /home/smartpyme/ambientes/qa
  sudo -u smartpyme $PHP artisan migrate --force --no-interaction >> "$LOG" 2>&1
  sudo -u smartpyme $PHP cachear-config.php >> "$LOG" 2>&1
  sudo -u smartpyme $PHP artisan view:clear >> "$LOG" 2>&1
  instalar_frontend "$p" /home/smartpyme/public_html/test
  echo "$sha $(date -u +%FT%TZ)" > /home/smartpyme/ambientes/qa/DESPLEGADO
  log "QA: listo $sha"
fi

# ---------- DEMO (frontend; el backend es el de producción) ----------
if p=$(tomar "$E/demo"); then
  sha=${p##*-}
  log "DEMO: instalando frontend $sha"
  verificar "$p"
  instalar_frontend "$p" /home/smartpyme/public_html/demo
  echo "$sha $(date -u +%FT%TZ)" > /home/smartpyme/ambientes/demo/DESPLEGADO
  log "DEMO: listo $sha"
fi

# ---------- DEMO: reinicio pedido ----------
if p=$(tomar "$E/demo-reiniciar"); then
  log "DEMO: reinicio pedido desde GitHub (${p##*-})"
  /root/ambientes/reiniciar-demo.sh >> "$LOG" 2>&1
fi

# Conservar solo los últimos 10 paquetes procesados.
ls -1dt "$HECHOS"/* 2>/dev/null | tail -n +11 | xargs -r rm -rf
