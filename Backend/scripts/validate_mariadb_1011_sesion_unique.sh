#!/usr/bin/env bash
# Validación REAL en MariaDB 10.11 (Docker: sp-mariadb-1011).
# Resultado 2026-08-09: functional index MySQL = INCOMPATIBLE;
#                        GENERATED STORED + UNIQUE = COMPATIBLE (solución final).
set -euo pipefail

MYSQL_V=(docker exec -i sp-mariadb-1011 mariadb -uroot -proot -e)

echo "VERSION:"; ${MYSQL_V[@]} "SELECT VERSION();"

${MYSQL_V[@]} "
DROP DATABASE IF EXISTS sp_rest_probe;
CREATE DATABASE sp_rest_probe;
USE sp_rest_probe;
CREATE TABLE empresas (id INT UNSIGNED PRIMARY KEY);
INSERT INTO empresas VALUES (1);
CREATE TABLE restaurante_mesas (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_empresa INT UNSIGNED NOT NULL,
  numero VARCHAR(20) NOT NULL,
  CONSTRAINT fk_mesa_emp FOREIGN KEY (id_empresa) REFERENCES empresas(id)
) ENGINE=InnoDB;
INSERT INTO restaurante_mesas (id_empresa, numero) VALUES (1,'M1');
CREATE TABLE restaurante_sesiones_mesa (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  mesa_id BIGINT UNSIGNED NOT NULL,
  usuario_id BIGINT UNSIGNED NOT NULL,
  id_empresa INT UNSIGNED NOT NULL,
  estado ENUM('abierta','pre_cuenta','cerrada') NOT NULL DEFAULT 'abierta',
  opened_at TIMESTAMP NULL,
  closed_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  KEY idx_mesa (mesa_id),
  CONSTRAINT fk_ses_mesa FOREIGN KEY (mesa_id) REFERENCES restaurante_mesas(id) ON DELETE CASCADE,
  CONSTRAINT fk_ses_emp FOREIGN KEY (id_empresa) REFERENCES empresas(id) ON DELETE CASCADE
) ENGINE=InnoDB;
"

echo "=== A) Functional unique index (MySQL-style) ==="
set +e
${MYSQL_V[@]} "USE sp_rest_probe; CREATE UNIQUE INDEX uq_func ON restaurante_sesiones_mesa ((CASE WHEN estado IN ('abierta','pre_cuenta') THEN mesa_id ELSE NULL END));"
FUNC_RC=$?
set -e
echo "FUNC_RC=$FUNC_RC (expect non-zero on MariaDB 10.11)"

echo "=== B) GENERATED STORED + UNIQUE (solución final) ==="
${MYSQL_V[@]} "USE sp_rest_probe;
ALTER TABLE restaurante_sesiones_mesa
  ADD COLUMN mesa_sesion_activa_id BIGINT UNSIGNED
  GENERATED ALWAYS AS (IF(estado IN ('abierta','pre_cuenta'), mesa_id, NULL)) STORED;
CREATE UNIQUE INDEX uq_restaurante_mesa_sesion_activa ON restaurante_sesiones_mesa (mesa_sesion_activa_id);
SELECT 'MIGRATE_UP_OK' AS r;
INSERT INTO restaurante_sesiones_mesa (mesa_id,usuario_id,id_empresa,estado,opened_at) VALUES (1,1,1,'abierta',NOW());
SELECT 'INSERT_OK' AS r;
"
set +e
${MYSQL_V[@]} "USE sp_rest_probe; INSERT INTO restaurante_sesiones_mesa (mesa_id,usuario_id,id_empresa,estado,opened_at) VALUES (1,1,1,'abierta',NOW());"
echo "DUP_ABIERTA_RC=$? (expect 1)"
${MYSQL_V[@]} "USE sp_rest_probe; INSERT INTO restaurante_sesiones_mesa (mesa_id,usuario_id,id_empresa,estado,opened_at) VALUES (1,1,1,'pre_cuenta',NOW());"
echo "DUP_PRECUENTA_RC=$? (expect 1)"
set -e
${MYSQL_V[@]} "USE sp_rest_probe;
INSERT INTO restaurante_sesiones_mesa (mesa_id,usuario_id,id_empresa,estado,opened_at,closed_at) VALUES (1,1,1,'cerrada',NOW(),NOW()),(1,1,1,'cerrada',NOW(),NOW());
SELECT 'MULTI_CLOSED_OK' AS r;
UPDATE restaurante_sesiones_mesa SET estado='cerrada', closed_at=NOW() WHERE estado='abierta';
INSERT INTO restaurante_sesiones_mesa (mesa_id,usuario_id,id_empresa,estado,opened_at) VALUES (1,1,1,'abierta',NOW());
SELECT 'REOPEN_OK' AS r;
DROP INDEX uq_restaurante_mesa_sesion_activa ON restaurante_sesiones_mesa;
ALTER TABLE restaurante_sesiones_mesa DROP COLUMN mesa_sesion_activa_id;
SELECT 'ROLLBACK_OK' AS r;
ALTER TABLE restaurante_sesiones_mesa
  ADD COLUMN mesa_sesion_activa_id BIGINT UNSIGNED
  GENERATED ALWAYS AS (IF(estado IN ('abierta','pre_cuenta'), mesa_id, NULL)) STORED;
CREATE UNIQUE INDEX uq_restaurante_mesa_sesion_activa ON restaurante_sesiones_mesa (mesa_sesion_activa_id);
SELECT 'RE_MIGRATE_OK' AS r;
DROP INDEX uq_restaurante_mesa_sesion_activa ON restaurante_sesiones_mesa;
ALTER TABLE restaurante_sesiones_mesa DROP COLUMN mesa_sesion_activa_id;
SELECT 'ROLLBACK2_OK' AS r;
ALTER TABLE restaurante_sesiones_mesa
  ADD COLUMN mesa_sesion_activa_id BIGINT UNSIGNED
  GENERATED ALWAYS AS (IF(estado IN ('abierta','pre_cuenta'), mesa_id, NULL)) STORED;
CREATE UNIQUE INDEX uq_restaurante_mesa_sesion_activa ON restaurante_sesiones_mesa (mesa_sesion_activa_id);
SELECT 'MIGRATE3_OK' AS r;
"

echo "SOLUTION=GENERATED_STORED_PLUS_UNIQUE"
echo "FUNCTIONAL_INDEX_MARIADB_10_11=INCOMPATIBLE"
