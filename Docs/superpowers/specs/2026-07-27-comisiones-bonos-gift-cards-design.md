# Diseño: Comisiones, bonos y gift cards para vendedores

**Fecha:** 2026-07-27  
**Estado:** Aprobado (pendiente plan de implementación)  
**Tipo:** Tres módulos independientes + reportes consolidados  

---

## 1. Problema

- No existe motor de comisiones por categoría (solo UI legacy incompleta en subcategorías / campos de producto no cableados).
- No hay motor de bonos por reglas; planilla usa montos manuales.
- El canje de gift cards se improvisa con ventas ficticias; solo existe detección por nombre de forma de pago en el corte.
- Se necesitan tres funcionalidades activables por separado, con dependencia suave gift → comisiones en redenciones.

## 2. Decisiones cerradas

| Tema | Decisión |
|------|----------|
| Arquitectura | Enfoque 1: módulos laterales + ledger; hooks mínimos post-facturación (patrón fidelización) |
| Devoluciones / anulaciones vs comisión | Opción B: si el período del movimiento original está `pagado`, el ajuste va al siguiente período `abierto`; si no está pagado, ajusta el mismo período |
| Nivel de % | Categoría = default; override opcional por subcategoría |
| Emisión gift card | Venta normal de SKU en categoría “Gift Cards” (0% comisión) + registro en ledger `gift_cards` |
| Redención | Venta real del producto/servicio + forma de pago Gift Card + fila en `gift_card_redenciones` |
| Materialización de comisión | Ledger al evento (`venta` / `redencion_gift_card` / `ajuste_devolucion`); reportes leen el ledger |
| Base del % | Configurable por empresa en `configuracion` del feature; default `subtotal_sin_iva` (post-descuento de línea) |
| Evaluación de bonos | Job programado + botón manual “Calcular”; idempotente por regla/vendedor/período |
| Vendedor | `users`; efectivo = `COALESCE(detalle.id_vendedor, venta.id_vendedor)` |
| Planilla | No es fuente de verdad; integración a `planilla_detalles` queda fuera de v1 |
| Metas de bono | Excluir ventas de emisión de gift cards; incluir ventas de productos canjeados (redención) |

## 3. Principios

1. Tres flags independientes; ninguna combinación forzada.
2. No mezclar motores dentro de la matemática de precios de `FacturacionService`.
3. Al desactivar un módulo: no hay nuevos cálculos; el histórico sigue consultable.
4. Bonos nunca aparecen como ventas ni generan comisión sobre sí mismos.
5. Emisión de gift card nunca genera comisión; solo la redención (y solo si comisiones está activo).
6. En pagos mixtos: comisión `redencion_gift_card` solo sobre la parte cubierta por gift card; el resto como `origen=venta`. No doble comisión sobre el mismo monto.

---

## 4. Feature flags

### Slugs

| Slug | Módulo |
|------|--------|
| `comisiones-vendedores` | Comisiones por categoría |
| `bonos-vendedores` | Motor de bonos |
| `gift-cards` | Emisión y redención |

### Mecanismo existente

- Tablas: `funcionalidades`, `empresa_funcionalidades` (`activo`, `configuracion` JSON).
- Middleware: `verificar.funcionalidad:{slug}`.
- Frontend: `FuncionalidadesService` + `FuncionalidadGuard`.
- Seeders dedicados (mismo patrón que fidelización).

### `configuracion` JSON (por empresa)

**Comisiones**

- `base_calculo`: `subtotal_sin_iva` (default) \| `total_con_iva` \| `bruto_sin_descuento`
- `excluir_categoria_gift_cards`: `true` (default)
- Parámetros de período / corte (según se definan en implementación)

**Bonos**

- Día/hora de evaluación automática, timezone si aplica

**Gift cards**

- `id_categoria_gift_cards`
- Prefijo / longitud de código (opcional)

### Helper

- `Empresa::tieneFuncionalidad(string $slug): bool` (o equivalente centralizado usado por middleware y servicios).
- Lectura histórica: endpoints de consulta no deben exigir flag activo (o middleware distinto “activo o tiene histórico”).

### Integración suave gift → comisiones

```text
GiftCardRedencionService::redeem(...)
  → descontar saldo, crear gift_card_redenciones
  → if empresa.tieneFuncionalidad('comisiones-vendedores')
       → ComisionService::registrarDesdeRedencion(...)
       → gift_card_redenciones.id_comision_movimiento = ...
     else
       → id_comision_movimiento = null
```

Comisiones no depende de gift cards. Bonos no depende de ninguno.

---

## 5. Modelo de datos

Todas las tablas nuevas: `id_empresa` + scopes por tenant.

### 5.1 Comisiones

| Tabla | Propósito |
|-------|-----------|
| `comision_categoria_config` | `%` por `id_categoria`. Unique `(id_empresa, id_categoria)` |
| `comision_subcategoria_config` | Override por `id_subcategoria`. Unique `(id_empresa, id_subcategoria)` |
| `comision_periodos` | `fecha_inicio`, `fecha_fin`, estado: `abierto` \| `cerrado` \| `pagado` |
| `comision_movimientos` | Ledger inmutable de comisiones / ajustes |
| `comision_liquidaciones` | Totales por vendedor + período al cerrar/pagar |

**`comision_movimientos` (campos clave)**

- `id_vendedor`, `id_periodo` (nullable o asignado por fecha del evento)
- `origen`: `venta` \| `redencion_gift_card` \| `ajuste_devolucion`
- `id_venta`, `id_detalle_venta` (nullable en ajustes globales)
- `id_gift_card_redencion` (nullable)
- `id_categoria`, `id_subcategoria`
- `monto_base`, `porcentaje_aplicado`, `monto_comision` (negativo en ajustes)
- `id_movimiento_origen` (FK al movimiento ajustado)
- Idempotencia: unique razonable p. ej. `(id_empresa, origen, id_detalle_venta)` donde aplique; claves equivalentes para redenciones y ajustes

**Resolución de %:** override subcategoría → categoría → 0%. Si % = 0 en venta normal, no crear movimiento. Categoría Gift Cards además se excluye por flag/categoría dedicada (no solo por 0%).

**Liquidación / devoluciones:** período `pagado` no se reescribe; ajustes posteriores caen en el siguiente período `abierto`.

### 5.2 Bonos

| Tabla | Propósito |
|-------|-----------|
| `bono_reglas` | Nombre, tipo (`meta_fija` \| `escalonado`), ventana, activo, JSON umbrales/montos |
| `bono_generados` | `id_vendedor`, `id_regla`, período, `monto`, estado `pendiente` \| `aprobado` \| `pagado`, `aprobado_por` |
| `bono_evaluaciones` | Opcional v1: log de corridas job/manual |

Unique: `(id_empresa, id_vendedor, id_regla, periodo_inicio, periodo_fin)` para no duplicar al recalcular.

No escribe en `ventas` ni en `comision_movimientos`.

### 5.3 Gift cards

| Tabla | Propósito |
|-------|-----------|
| `gift_cards` | `codigo` unique por empresa, `monto_inicial`, `saldo`, `fecha_emision`, `fecha_vencimiento` nullable, `id_vendedor_emisor`, `id_venta_emision`, `id_producto`, estado `activa` \| `agotada` \| `anulada` |
| `gift_card_redenciones` | `id_gift_card`, `id_venta`, monto, `saldo_resultante`, `id_vendedor`, categoría del producto canjeado, `id_comision_movimiento` nullable |

Al activar `gift-cards` para una empresa: asegurar categoría “Gift Cards” + `comision_categoria_config.porcentaje = 0` si comisiones también aplica / o al menos categoría marcada en `configuracion`.

Mantener compatibilidad con `FORMAS_PAGO_GIFT_CARD` / corte de caja.

### 5.4 Relaciones con existentes

```text
users (vendedor)
ventas / detalles_venta / devoluciones_venta
categorias / categoria_subcategorias / productos
        ↓ hooks post-commit
comision_movimientos ←── gift_card_redenciones
gift_cards ← venta de emisión (SKU categoría Gift Cards)
bono_generados ← evaluador (lee ventas; no escribe ventas)
```

---

## 6. Flujos

### Comisión por venta

1. Venta pasa a pagada.
2. Si `comisiones-vendedores` activo: por cada línea (vendedor efectivo, no categoría gift card), calcular base según config, resolver %, insertar `comision_movimientos` `origen=venta`.
3. Parte de línea cubierta solo por pago gift card: no generar `origen=venta` sobre esa parte (ver redención).

### Redención gift card

1. Venta del producto/servicio con pago (parcial o total) Gift Card.
2. Validar código/saldo; descontar; si saldo 0 → `agotada`.
3. Crear `gift_card_redenciones`.
4. Si comisiones activo: movimiento `origen=redencion_gift_card` con % de la categoría del producto canjeado.

### Devolución / anulación

1. Localizar movimientos originales.
2. Crear ajuste negativo; asignar período según regla B.
3. Si había redención gift: restaurar saldo y estado de la card según política de la fase gift.

### Bonos

1. Job y/o “Calcular período”.
2. Evaluar reglas contra ventas del período (excl. emisión gift cards).
3. Upsert `bono_generados` en `pendiente` (no duplicar).
4. Supervisor: aprobar → pagar.

---

## 7. Reportes y UI

### Dashboard / listado vendedores (solo lectura)

- Progreso a meta de bono (si módulo activo).
- Comisiones acumuladas del período abierto.
- Próxima fecha de pago / estado de bonos.
- Consume ledgers y `bono_generados`; sin recálculo ad hoc.

### Excel comisiones

- Rango de fechas libre.
- Hoja por vendedor: correlativo, fecha, categoría, monto base, %, comisión; total al final.
- Líneas de redención gift identificadas explícitamente.

### Comprobante PDF

- Por vendedor individual.
- Datos vendedor, período, desglose comisión / bono, espacio de firma física.
- DomPDF + Blade (patrón existente).

### Consolidado

- Ventas por categoría, comisiones (venta vs redención), bonos por regla, redenciones, total a pagar **siempre desglosado**.

---

## 8. Plan por fases

| Fase | Entregable |
|------|------------|
| **0** | Seed 3 slugs, helper `tieneFuncionalidad`, wiring menús/rutas, bootstrap categoría Gift Cards al activar |
| **1** | Comisiones: migraciones, admin %, ledger post-venta, ajustes B, períodos/liquidación, Excel + PDF |
| **2** | Gift cards: emisión, redención parcial, check runtime comisiones, reverso básico, extensión reportes |
| **3** | Bonos: reglas, evaluador job+manual, flujo aprobación, dashboard progreso, inclusión en consolidado/PDF |
| **4** | UX consolidada; deprecar UI legacy de comisión en subcategorías; documentar fin del workaround de venta ficticia |

**Dependencias:** 0 → 1; 2 y 3 pueden paralelizarse tras 1 (3 solo requiere 0 para flag; idealmente tras 1 para dashboard unificado). 4 al final.

---

## 9. Riesgos técnicos

1. `FacturacionService` crítico — hooks post-commit; fallos de comisión/gift no deben tumbar la venta (log + reintento).
2. Vendedor efectivo debe coincidir con `VentaMontosPorVendedorService`.
3. UI/campos legacy de comisión en producto/subcategoría: no usar como fuente de verdad.
4. Formas de pago gift por nombre: sincronizar con ledger o el corte diverge.
5. Pagos mixtos: prorrateo obligatorio para evitar doble comisión.
6. Jobs CLI: fijar `id_empresa` (global scopes).
7. Idempotencia ante reintentos de facturación.
8. Shopify/canales externos fuera de v1 salvo que reutilicen el mismo pipeline de venta.
9. Endpoints de histórico vs flag apagado.
10. No mezclar con `planilla_detalles.comisiones`.

---

## 10. Fuera de alcance (v1)

- Fecha de vencimiento operativa de gift cards (campo nullable sí; lógica de expiración no).
- Firma digital en comprobantes.
- Push automático de comisiones/bonos a planilla.
- Motor de comisiones por producto individual (solo categoría + override subcategoría).
- Integración Shopify dedicada para gift/comisiones.
- Recálculo masivo histórico de ventas anteriores al go-live del módulo (salvo herramienta admin explícita posterior).

---

## 11. Criterios de éxito

- Empresa puede activar cualquier subconjunto de los 3 flags sin romper POS.
- Emisión gift no comisiona; redención comisiona solo si comisiones está on.
- Devolución tras período pagado no altera liquidaciones pagadas; ajusta período abierto siguiente.
- Bonos no contaminan reportes de facturación ni el ledger de comisiones.
- Excel/PDF/dashboard muestran desglose, nunca un único monto opaco comisión+bono.
