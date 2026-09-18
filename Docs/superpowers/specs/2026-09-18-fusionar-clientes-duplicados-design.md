# Design: Fusionar clientes duplicados (SPT-500)

**Fecha:** 2026-09-18  
**Estado:** Aprobado  
**Ticket:** [SPT-500](https://smartpyme.atlassian.net/browse/SPT-500) — Alta Legal, empresa 799

## Problema

Alta Legal cargó ventas y se crearon clientes duplicados. Los duplicados están inhabilitados (`enable = 0`). Quieren borrarlos, pero si tienen ventas o DTE hay que pasarlos al cliente habilitado con el mismo NIT o DUI. Hacer el mapeo a mano no escala.

## Objetivos

1. Comando Artisan de un solo uso operativo, acotado por `--empresa`.
2. Descubrir pares solo dentro de esa empresa: mismo NIT (si hay) o mismo DUI.
3. Reasignar todo lo operativo que apunte al inhabilitado, luego hard-delete.
4. Dry-run por defecto. `--ejecutar` escribe.
5. Grupos ambiguos: no tocar, reportar.

## Fuera de alcance

- UI de merge.
- Emparejar por nombre.
- Tocar `empresas.id_cliente`.
- Soft delete (está apagado en `Cliente`).
- Dashboard (SPT-499) u otros pedidos de SPT-500 ya cubiertos en producto.

## Decisiones

| Tema | Elección |
|------|----------|
| Descubrimiento | Automático por NIT/DUI normalizado, solo `id_empresa` del flag |
| Llave | NIT si no está vacío; si no, DUI. Sin documento → saltar |
| Normalización | Quitar espacios y guiones; mayúsculas |
| Destino | Exactamente 1 habilitado en el grupo |
| Origen | Cada inhabilitado del grupo |
| 0 o 2+ habilitados | Saltar el grupo |
| Persistencia | Dry-run sin `--ejecutar` |
| Delete | Hard delete del origen si no quedan FKs de las tablas manejadas |
| Fallo de un par | Rollback de ese par; seguir con el resto |

## Emparejado

Dentro de `clientes.id_empresa = {empresa}` (sin global scope):

1. `clave = nit:{NIT}` o `dui:{DUI}` o `null`.
2. `null` → lista “sin documento”, no fusiona.
3. Grupo fusionable: 1 habilitado + ≥1 inhabilitado.
4. Destino = habilitado. Orígenes = inhabilitados.

## Reasignación (por par, en transacción)

Actualizar `id_cliente` origen → destino en las tablas que existan:

`ventas`, `devoluciones_venta`, `creditos`, `credito_contratos`, `contactos_cliente`, `cotizaciones`, `cotizacion_ventas`, `eventos`, `paquetes`, `ordenes_produccion`, `proyectos`, `transacciones_puntos`, `consumo_puntos`, `cliente_notas`, `cliente_visitas`.

DTE viajan con `ventas`.

`puntos_cliente`: unique `(id_cliente, id_empresa)`. Si ambos tienen fila, sumar puntos al destino y borrar la del origen.

Snapshots 360 del origen: borrar (`cliente_ventas_mensuales`, `cliente_fidelizacion_snapshot`, `cliente_metricas_rfm`, `cliente_productos_top`, `cliente_categorias_preferidas`, `cliente_actividad_reciente`).

Si alguna de las tablas de reasignación aún tiene filas del origen, abortar el par. Si no, borrar la fila en `clientes`.

Usar `DB::table` (no Eloquent) para no disparar observers de venta/puntos.

## Interfaz

```
php artisan clientes:fusionar-duplicados --empresa=799
php artisan clientes:fusionar-duplicados --empresa=799 --ejecutar
```

`--empresa` obligatorio. Sin él, exit 1.

Salida: tabla por grupo (clave, destino, orígenes, ventas a mover, acción). Al final: fusionados / saltados / errores.

## Pruebas

Unitarias, sin DB:

- Normalizar NIT/DUI con guiones y espacios.
- Clave usa NIT si hay, si no DUI, si no null.
- 1 habilitado + 1 inhabilitado → fusionar.
- 2 habilitados mismo NIT → saltar.
- Inhabilitado sin documento → no entra a grupo fusionable.

## Cómo correrlo en prod (799)

1. Dry-run y revisar saltados.
2. `--ejecutar` en horario bajo.
3. Contar ventas por los IDs destino vs antes.
