# Despliegue a producción

## Flujo

1. PR a `develop` → CI → merge → **QA** se despliega solo (apitest / test.smartpyme.site).
2. PR de `develop` a `main` → CI → merge → GitHub compila el paquete de producción y **espera aprobación**
   (ambiente `produccion`; solo el responsable aprueba). Sin aprobación no llega nada al servidor.
3. Al aprobar: se sube el paquete, se etiqueta la versión (`v27.11.26`, `v27.11.26b`, …) y el servidor
   lo instala con `/root/prod/instalar-prod.sh` (cron de root, cada minuto). También se actualiza el
   frontend de la DEMO, que usa el código de producción.

Las pruebas corren solo en GitHub. En el servidor no se corre ninguna prueba y las dependencias van con `--no-dev`.

## Número de versión

Lo pone el pipeline al compilar (`environment.prod.ts` → `appVersion` y `sentry.release`, y `manifest.webmanifest`):
fecha de El Salvador `DD.MM.AA`, y una letra si ya hubo otro despliegue ese día (`b`, `c`, …).
**No hay que editarlo a mano.** La carpeta de cada versión es `<versión>-<commit>` (p. ej. `27.11.26b-22988d6`).

## En el servidor

```
/home/smartpyme/prod/releases/<versión>-<commit>/Backend   código + vendor de cada versión (quedan 3)
/home/smartpyme/prod/actual -> releases/<…>                la versión que atiende a los clientes
/home/smartpyme/prod/compartido/.env, storage              compartidos por todas las versiones (hoy enlazan
                                                           a repositories/unificado/Backend, que no se borra)
/root/prod/respaldos/<versión>.sql.gz                      respaldo antes de migrar (quedan 3)
/root/prod/despliegues.log                                 registro de cada despliegue
```

## Qué hace el instalador (y qué ven los clientes)

| Paso | Si falla |
|---|---|
| Verifica el paquete, prepara la versión en su carpeta, `composer install --no-dev`, cachés | Se detiene. Los clientes no notan nada. Correo. |
| Revisa las migraciones pendientes (`scripts/revisar-migraciones.php`) | Se detiene si hay una destructiva o pesada sin marca. |
| Si hay migraciones: respaldo completo de la base (~2 min) y `migrate --force` | Se detiene; la versión anterior sigue atendiendo. |
| **Cambio**: `prod/actual` apunta a la versión nueva (instantáneo) y recarga PHP | — |
| Revisa `https://apiunificado.smartpyme.site/api/salud` (debe responder la versión nueva) | **Vuelve sola a la versión anterior** y avisa. |
| Frontend: primero los archivos nuevos, al final `index.html` | Los archivos de las 3 últimas versiones se conservan para quien tenga la app abierta. |

Volver atrás a mano: `ln -sfn /home/smartpyme/prod/releases/<versión> /home/smartpyme/prod/actual && systemctl reload ea-php84-php-fpm`.

## Migraciones

- `migrate --force` aplica solo las pendientes; al terminar la base queda al día.
- **Nunca se deshacen solas**: el `down()` puede borrar datos. Si hay que volver atrás, vuelve solo el código.
  Por eso cada migración debe funcionar también con la versión anterior del código:
  agregar columnas (nullable o con default) o tablas nuevas, sí; borrar o renombrar en el mismo despliegue, no.
  Si hay que quitar una columna: primero se despliega el código que ya no la usa y, en otro despliegue, la migración.
- La sesión que migra usa `lock_wait_timeout = 15` s: si una tabla está ocupada, la migración falla en vez de
  dejar a los clientes esperando.

### Migraciones bloqueadas y cómo marcarlas

El CI (job *migraciones seguras*) y el servidor bloquean una migración nueva si su `up()`:

- **borra o puede perder datos**: `drop…` de tablas o columnas, `truncate`, `delete`, renombrar, `->change()`,
  o SQL con `DROP`, `TRUNCATE`, `DELETE FROM`, `RENAME`, `MODIFY`/`CHANGE`;
- **es pesada en una tabla grande** (`ventas`, `detalles_venta`, `kardexs`, `ajustes`, `inventario`, `productos`, …):
  índices, llaves foráneas, cambios de tipo, `ALTER TABLE` o `UPDATE` masivo.

Si la operación es intencional y está revisada, se marca en el archivo de la migración con un comentario:

```php
// @migracion-destructiva: se elimina la columna fax, sin uso desde 2025 (ver SP-1234)
// @migracion-pesada: índice en ventas(id_empresa, fecha) para el reporte mensual; ~1 min, en línea
```

La marca hace que el CI pase y queda a la vista en el PR y en el registro del despliegue, para quien aprueba.
Agregar columnas o tablas no necesita marca: en MariaDB 10.11 es instantáneo aunque la tabla tenga millones de filas.
