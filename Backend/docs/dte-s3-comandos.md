# DTE en S3 — Comandos y referencia

Este documento describe los comandos relacionados con la migración de JSON de DTE (`ventas` / `compras`) hacia Amazon S3 y el mantenimiento asociado.

---

## 1. `php artisan dte:migrate-to-s3`

**Qué hace:** Para cada fila elegible, lee el JSON en `dte` y/o `dte_invalidacion`, lo sube al disco configurado (por defecto `s3`), guarda la clave en `dte_s3_key` / `dte_invalidacion_s3_key`, marca `dte_migrated_at` / `dte_invalidacion_migrated_at` y deja en `NULL` la columna JSON correspondiente en la base de datos.

**Requisitos:** Variables AWS y `AWS_BUCKET` en `.env` (excepto en `--dry-run`, donde el bucket puede estar vacío). La migración de esquema (columnas nuevas) debe estar aplicada.

**Procesamiento interno:** Usa `chunkById(50)` para no cargar toda la tabla en memoria.

### Opciones

| Opción | Valores / formato | Explicación |
|--------|-------------------|-------------|
| `--dry-run` | flag | Solo imprime las rutas S3 que usaría. **No** sube archivos ni actualiza la base de datos. No exige bucket configurado. |
| `--table=` | `both` (defecto), `ventas`, `compras` | Tabla(s) a procesar en esa ejecución. |
| `--mes=` | `YYYY-MM` | Filtra por la columna **`fecha`** del registro (inicio y fin de ese mes calendario). No combinar con `--desde` / `--hasta`. |
| `--desde=` | `Y-m-d` | Inicio del rango de **`fecha`** (inclusive). Debe usarse junto con `--hasta`. |
| `--hasta=` | `Y-m-d` | Fin del rango de **`fecha`** (inclusive). Debe usarse junto con `--desde`. |
| `--limit=` | entero | Máximo de **registros** (filas) a **revisar** por tabla en esa corrida. Útil para pruebas o migraciones por tandas. Al terminar la tanda, las filas ya migradas no vuelven a salir en la consulta. |
| `--skip-invalidacion` | flag | Solo intenta migrar `dte`; no procesa `dte_invalidacion`. |

### Comportamiento importante

- Las filas que **ya** tienen `dte_s3_key` (o la clave de invalidación) **se omiten** para esa columna: el comando es idempotente en ese sentido.
- Cada registro puede generar hasta **dos** subidas por ejecución (documento + invalidación), salvo que uses `--skip-invalidacion`.
- El filtro por fechas se aplica sobre la columna **`fecha`** de la venta o compra, alineado con la estructura de carpetas en S3 (`.../AAAA/MM/...`).

### Ejemplos

```bash
# Simulación: compras de junio 2025
php artisan dte:migrate-to-s3 --dry-run --table=compras --mes=2025-06

# Migrar solo 100 compras de ese mes (prueba real)
php artisan dte:migrate-to-s3 --table=compras --mes=2025-06 --limit=100

# Todas las ventas de 2025 (rango explícito)
php artisan dte:migrate-to-s3 --dry-run --table=ventas --desde=2025-01-01 --hasta=2025-12-31
php artisan dte:migrate-to-s3 --table=ventas --desde=2025-01-01 --hasta=2025-12-31

# Ventas y compras, sin tocar invalidaciones
php artisan dte:migrate-to-s3 --table=both --mes=2025-03 --skip-invalidacion
```

### Convención de claves en S3 (referencia)

- Ventas: `ventas/{id_empresa}-{slug-nombre}/AAAA/MM/registro-{id}-documento.json` (e invalidación: `...-invalidacion.json`).
- Compras: `compras/...` (misma idea).

El slug del nombre se obtiene de la tabla `empresas` para el `id_empresa` de la fila.

---

## 2. Migración de base de datos (columnas S3)

Aplica o revierte las columnas `dte_s3_key`, `dte_migrated_at`, `dte_invalidacion_s3_key`, `dte_invalidacion_migrated_at` en `ventas` y `compras`.

```bash
# Aplicar migraciones pendientes (incluye la de DTE S3 si no está corrida)
php artisan migrate

# Solo el archivo de DTE S3 (ruta relativa al proyecto Backend)
php artisan migrate --path=database/migrations/2026_05_05_120000_add_dte_s3_columns_to_ventas_and_compras_tables.php

# Revertir solo esa migración (último batch que la incluya)
php artisan migrate:rollback --step=1
```

**Nota:** Hacer rollback **no** borra objetos ya subidos a S3; solo quita columnas en BD. Planificar con cuidado.

---

## 3. Programación automática (cron de Laravel)

Si en `.env` está `DTE_S3_SCHEDULE_ENABLED=true`, en `app/Console/Kernel.php` queda programado:

`php artisan dte:migrate-to-s3` a las **02:45** (sin solapamiento largo, log en `storage/logs/dte-migrate-s3.log`).

Para que el scheduler ejecute lo programado, el servidor debe tener el cron de Laravel:

```bash
* * * * * cd /ruta/al/Backend && php artisan schedule:run >> /dev/null 2>&1
```

**Explicación:** Cada minuto Laravel revisa qué tareas tocan; a la hora configurada dispara el comando de migración DTE.

---

## 4. Comandos útiles de soporte

```bash
# Listar el comando y ver todas las opciones
php artisan dte:migrate-to-s3 --help

# Refrescar configuración en caché tras cambiar .env
php artisan config:clear
php artisan config:cache
```

---

## 5. Variables de entorno relevantes (`.env`)

| Variable | Uso |
|----------|-----|
| `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` | Credenciales IAM para S3. |
| `AWS_DEFAULT_REGION` | Ej. `us-east-2` para el bucket en Ohio. |
| `AWS_BUCKET` | Nombre del bucket (ej. `sp-s3-dte-global`). |
| `DTE_S3_DISK` | Disco de Laravel a usar (defecto: `s3` en `config/dte.php`). |
| `DTE_S3_PRESIGNED_MINUTES` | TTL de URLs firmadas si se usan desde código (modelo). |
| `DTE_S3_SCHEDULE_ENABLED` | `true` / `false` para activar la corrida nocturna en el Kernel. |

---

## 6. Resumen rápido

| Objetivo | Comando |
|----------|---------|
| Probar sin tocar datos | `php artisan dte:migrate-to-s3 --dry-run ...` |
| Migrar solo compras de un mes | `--table=compras --mes=YYYY-MM` |
| Migrar ventas de un año | `--table=ventas --desde=YYYY-01-01 --hasta=YYYY-12-31` |
| Tanda pequeña | Añadir `--limit=N` |
| Ignorar JSON de anulación en S3 | `--skip-invalidacion` |
| Crear columnas en BD | `php artisan migrate` |
| Ver opciones | `php artisan dte:migrate-to-s3 --help` |

Si en el futuro se añaden más flags al comando, ejecutar siempre `php artisan dte:migrate-to-s3 --help` para la lista actualizada.
