#!/usr/bin/env bash
# Corre las pruebas del backend excluyendo las de tests/cuarentena.txt.
# Uso (desde Backend/):  ./tests/correr-pruebas.sh [opciones extra de phpunit]
# Las pruebas siempre usan sqlite en memoria: ver tests/Support/GuardiaBaseDePruebas.php.
set -euo pipefail
cd "$(dirname "$0")/.."

patron=$(grep -v -e '^#' -e '^[[:space:]]*$' tests/cuarentena.txt \
  | sed -e 's/[][\\.^$*+?(){}|\/]/\\&/g' \
  | paste -sd '|' -)

if [ -n "$patron" ]; then
  exec php -d memory_limit=2G vendor/bin/phpunit --exclude-filter "/^(${patron})( with data set .*)?$/" "$@"
else
  exec php -d memory_limit=2G vendor/bin/phpunit "$@"
fi
