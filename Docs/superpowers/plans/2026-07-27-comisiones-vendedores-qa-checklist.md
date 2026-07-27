# QA checklist — Comisiones vendedores (Fase 1)

**Branch:** `feat/comisiones-vendedores`  
**Entorno:** staging (empresa de prueba)  
**Automated:** `php vendor/bin/phpunit tests/Unit/Services/Comisiones/` → 23/23 PASS

---

## Step 1 — Activar feature flag

- [ ] Super Admin → empresa staging → activar **Comisiones de Vendedores** (`comisiones-vendedores`).
- [ ] Confirmar `empresa_funcionalidades.activo = 1` para ese slug.
- [ ] Login en la empresa: menú **Comisiones** visible; rutas `/comisiones/configuracion`, `/comisiones/periodos`, `/comisiones/reportes` cargan sin 403.

## Step 2 — Config % + venta → ledger

- [ ] En **Comisiones → Configuración**, asignar **2%** a una categoría con productos de prueba.
- [ ] Facturar venta **Pagada** con producto de esa categoría y vendedor asignado.
- [ ] Verificar fila en `comision_movimientos`:
  - `origen = venta`
  - `porcentaje_aplicado = 2`
  - `monto_comision = monto_base × 0.02` (base default `subtotal_sin_iva`)
  - `id_vendedor` = vendedor efectivo del detalle/venta
  - `id_periodo` = período abierto del mes del evento

## Step 3 — Devoluciones (regla B)

**Escenario A — período abierto**

- [ ] Con el período del movimiento original en estado `abierto`, registrar devolución parcial o total.
- [ ] Verificar ajuste `origen = ajuste_devolucion`, `monto_comision` negativo, `id_movimiento_origen` apunta al movimiento de venta.
- [ ] Confirmar `id_periodo` del ajuste = **mismo** período que el movimiento original.

**Escenario B — período pagado**

- [ ] Cerrar período y marcar liquidación **pagada** (`comision_periodos.estado = pagado`).
- [ ] Registrar otra devolución sobre la misma venta/línea.
- [ ] Confirmar ajuste cae en el **siguiente período abierto** (no reescribe el pagado).

## Step 4 — Excel y PDF

- [ ] **Comisiones → Reportes** → descargar Excel (`GET /api/comisiones/export?desde=&hasta=`): archivo `.xlsx` sin error; hoja por vendedor con columnas fecha, categoría, origen, base, %, comisión.
- [ ] Generar comprobante PDF por vendedor/período (`GET /api/comisiones/comprobante/{id_vendedor}?periodo_id=`): stream PDF válido, totales coherentes con movimientos.

## Step 5 — Flag apagado

- [ ] Desactivar `comisiones-vendedores` en Super Admin.
- [ ] Facturar nueva venta Pagada con % configurado previamente → **no** debe crearse fila nueva en `comision_movimientos`.
- [ ] Devolución sobre venta histórica → **no** debe crear ajuste nuevo.
- [ ] **Histórico:** rutas actuales exigen middleware `verificar.funcionalidad`; con flag off, GET movimientos/export/comprobante devuelven 403. Si se requiere lectura histórica con flag off, abrir follow-up (spec §4).

---

## Sign-off

| Rol | Nombre | Fecha | Resultado |
|-----|--------|-------|-----------|
| QA / Product | | | ☐ Pass ☐ Fail |
| Dev | | | Automated unit tests OK |

**Notas / incidencias:**
