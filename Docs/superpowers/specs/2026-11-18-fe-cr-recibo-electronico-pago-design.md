# Diseño: Recibo electrónico de pago CR (tipo 20)

**Fecha:** 2026-11-18  
**Estado:** Aprobado  
**Origen:** Tarea 6 de `Docs/superpowers/plans/2026-10-21-fe-cr-anexos-44-noviembre.md` (ajustada)  
**Tipo:** Nueva emisión FE Costa Rica

---

## 1. Problema

Hacienda exige el **Recibo electrónico de pago (REP)**:
- Tipo de **comprobante emitido** (Nota 3 / consecutivo): **10**
- Código de **referencia** (Nota 9): **17** (pago a comprobante electrónico)
- Tipo de **documento de referencia** (Nota 10) **20**: solo cuando el documento referenciado es otro REP

Solo puede referenciar tipos **01, 04, 07, 08, 10 y 20**.

SmartPyme aún no emite ese comprobante. El plan original disparaba la emisión al guardar el abono; el negocio pidió **emisión manual**, igual que en ventas.

## 2. Objetivo

Permitir emitir un REP (tipo comprobante **10**) desde un **abono de venta** ya guardado, con botón “Emitir DTE”, cuando la venta a crédito tenga FE CR aceptada y el tipo referenciable sea válido.

## 3. Fuera de alcance

- Emisión automática al crear/actualizar el abono
- Abonos de compras o gastos
- REP que referencie notas de crédito/débito (02, 03)
- Cambios a FE El Salvador u Honduras
- IVA devuelto / FEE (tareas 5 y 7 del plan de anexos)
- Editar `Frontend/src/environments/environment.ts`

## 4. Decisiones

| Tema | Decisión |
|------|----------|
| Disparo | Manual: guardar abono → botón Emitir DTE (mismo patrón que ventas) |
| Superficie UI | Solo `abonos-ventas` (detalle del abono) |
| Endpoint | `POST emitirFeCrReciboPago` con `{ id: abono.id }` |
| Monto del REP | Monto del abono, no el total de la factura |
| Medio de pago | El del abono (efectivo `01`, tarjeta `02`, transferencia `04`, etc. según Nota 6) |
| Referencia | Código `17`, `document_type` = `tipo_dte` de la venta, `document_number` = clave de la venta, fecha = fecha de la venta |
| Persistencia | En el abono: `tipo_dte` = `10`, `sello_mh` = clave del REP, JSON `dte` con respuesta/estado (mismo criterio práctico que venta CR) |
| Consecutivo | El consecutivo de 20 posiciones usa el tipo de comprobante **10** (no reutilizar el `01` de factura) |
| Fallo Hacienda | El abono no se revierte; se puede reintentar si aún no hay clave aceptada |
| Contado | Abono ligado a venta de contado: no muestra botón / endpoint rechaza |
| XSD | Si el generador no tiene `reciboElectronicoPago`, parche en `Backend/patches/` antes de conectar UI |

## 5. Condiciones para emitir (todas)

1. Empresa con facturación electrónica de Costa Rica.
2. Abono de ventas (`abonos_ventas`) existente.
3. La venta asociada está a **crédito**.
4. La venta tiene clave FE **aceptada** por Hacienda.
5. `tipo_dte` de la venta ∈ `ReferenciaComprobanteCr::tiposPermitidosEnReciboPago()` → `01, 04, 07, 08, 10, 20`.
6. El abono **aún no** tiene clave REP aceptada (`sello_mh` vacío o estado no aceptado).

Si falta cualquiera: el botón no se muestra; el endpoint responde 422 con mensaje claro.

## 6. Arquitectura

```
UI abonos-ventas (Emitir DTE)
  → CostaRicaFacturacionElectronicaService.emitirReciboPago(abono)
  → POST emitirFeCrReciboPago { id }
  → CostaRicaFeController::emitirReciboPago
  → CostaRicaFeEmitService::emitirReciboPago(Abono)
  → CostaRicaReciboPagoMapper::build(Abono, Venta)
  → firma / envío DGT
  → persistir en Abono (tipo_dte, sello_mh, dte)
```

Catálogo ya existente (no reinventar):

- `ReferenciaComprobanteCr::CODIGO_PAGO_REP` = `17`
- `ReferenciaComprobanteCr::TIPO_RECIBO_PAGO` = `20`
- `ReferenciaComprobanteCr::tiposPermitidosEnReciboPago()`

## 7. Backend

**Crear**

- `CostaRicaReciboPagoMapper` — arma el payload del tipo 20; lanza `InvalidArgumentException` si la venta no es referenciable o no tiene clave.
- Request `EmitirFeCrReciboPagoRequest` — valida `id` del abono.
- Acción en `CostaRicaFeController` + ruta API `emitirFeCrReciboPago`.
- Migración en `abonos_ventas`: columnas `tipo_dte`, `sello_mh`, `dte` (nullable), alineadas al uso en ventas/devoluciones CR.

**Modificar**

- `CostaRicaFeEmitService::emitirReciboPago(Abono $abono): array`
- `Abono` (modelo ventas): fillable / casts de las columnas FE.
- Parche XSD del generador solo si el tipo `reciboElectronicoPago` no existe o rechaza código 17 / tipo 20.

**No modificar**

- `AbonoVentaService::crearOActualizarAbono` — no emite FE al guardar.

## 8. Frontend

En `abonos-ventas` (detalle), cuando `esFeCostaRica()` y se cumplen las condiciones de la sección 5:

- Botón **Emitir DTE** (misma etiqueta/estilo que en ventas).
- Tras éxito: refrescar abono (clave / estado) y feedback igual al de emisión de venta.
- Si ya hay REP aceptado: ocultar emitir; mostrar estado/clave como en ventas.
- Sin cambios en abonos de compras/gastos.

Servicio: método en `costa-rica-facturacion-electronica.service.ts` que llama `emitirFeCrReciboPago`.

## 9. Errores

| Caso | Comportamiento |
|------|----------------|
| Venta sin clave / no aceptada | 422; no arma XML |
| `tipo_dte` no permitido (p. ej. 03) | 422; mensaje: el recibo no puede referenciar ese documento |
| Abono ya con REP aceptado | 422; no reemite encima |
| Rechazo Hacienda | Abono intacto; UI permite reintentar |
| Generador/XSD incompleto | No firmar XML inválido; fallar antes con mensaje de configuración |

## 10. Pruebas

- Unit: mapper — venta `01` + clave → referencia tipo `01`, código `17`, número = clave; monto = abono.
- Unit: mapper — venta `tipo_dte` `03` → excepción.
- Unit: mapper — sin clave → excepción.
- Manual STAG (cuando toque tarea 8 del plan anexos): abonar factura a crédito aceptada → emitir REP → Hacienda acepta; no intentar referenciar NC.

## 11. Relación con el plan de anexos

Sustituye el **Step 3** de la Tarea 6 del plan (auto al abonar) por emisión manual. El resto de la Tarea 6 (mapper, tipo 20, código 17, parche XSD) se mantiene. Actualizar ese plan en el mismo PR de implementación para que no quede la instrucción de auto-disparo.
