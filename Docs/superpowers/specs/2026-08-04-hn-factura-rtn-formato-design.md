# Design: Factura con/sin RTN + formato de correlativo Honduras

**Fecha:** 2026-08-04  
**Estado:** Aprobado en conversación; pendiente revisión del archivo  
**Relacionado:** `2026-08-04-tipos-documento-por-pais-design.md` (enmienda parcial)

## Problema

1. En Honduras el SAR distingue facturas según se consigne o no el RTN del cliente; el sistema solo tenía un tipo `Factura`.
2. El número fiscal típico es `001-001-01-00000439`, pero el sistema guarda y muestra correlativos planos (`439`).

## Objetivos

1. Catálogo HN: **Factura con RTN** y **Factura sin RTN** (series independientes).
2. Campo en crear/editar documentos (solo HN): número de emisión/sucursal SAR **01–20**.
3. Armar número de display: `001-001-{NN}-{correlativo 8 dígitos}` cuando la empresa es Honduras.
4. Persistir en `ventas.correlativo` el valor numérico (sin formato), como hoy.

## Decisiones

| Tema | Elección |
|------|----------|
| Split Factura | Dos tipos de documento |
| Formato | Fijo `001-001` + dropdown 01–20 + pad 8 |
| Almacenamiento emisión | Columna nueva en `documentos` (`numero_emision`) |
| Dónde aplica formato | Solo display (UI, PDF, libros HN) |
| Alcance docs | Todos los fiscales HN; no Cotización/OC |
| Migración series `Factura` | No automática |

## Catálogo HN (actualizado)

**Fiscales:**

- Factura con RTN  
- Factura sin RTN  
- Ticket  
- Boleta de compra  
- Nota de crédito  
- Nota de débito  
- Recibo por honorarios profesionales  
- Guía de remisión  
- Comprobante de retención  

**Operativos (sin `numero_emision` / sin formato):** Cotización, Orden de compra, Recibo, Abono de Venta.

**Defaults nueva empresa/sucursal HN:** Ticket, Factura sin RTN, Cotización, Orden de compra.

**Whitelist ventas HN:** Factura con RTN, Factura sin RTN, Ticket, Recibo, Guía de remisión, Abono de Venta.

## Campo `numero_emision`

- Tipo: `CHAR(2)` o `VARCHAR(2)`, nullable.
- Valores: `01` … `20`.
- UI: dropdown solo si `resolveCodigoPaisFe(empresa) === HN`.
- Requerido al crear/editar documento fiscal HN (no para Cotización / Orden de compra / Recibo operativo / Abono).
- Preview en modal: `001-001-{numero_emision}-{pad8(correlativo)}`.

## Helper de formato

```
formatoCorrelativoHn(numeroEmision, correlativo) →
  "001-001-" + pad2(numeroEmision) + "-" + pad8(correlativo)
```

- Frontend: mostrar en facturación cuando país es HN y el documento tiene `numero_emision`.
- Backend: PDFs HN genéricos y libros fiscales (`factura_no`) usan el helper leyendo `documento.numero_emision` + `venta.correlativo`.
- Fallback: si `numero_emision` es null, mostrar correlativo plano (comportamiento actual) o pad8 solo — documentar: **pad8 sin prefijo** para no inventar `001-001-00-…`.

## Validaciones

- Backend `StoreDocumentoRequest`: si empresa HN y nombre es fiscal → `numero_emision` required, `in:01..20`.
- Frontend: mismo requerido en el form HN.
- No validar rango CAI (`inicial`/`final`) en este cambio.

## Archivos principales

| Área | Archivos |
|------|----------|
| Migración | nueva migración `documentos.numero_emision` |
| Model / request | `Documento.php`, `StoreDocumentoRequest.php` |
| Catálogo FE | `documento-nombre-options.ts` (+ specs) |
| Defaults BE | `DocumentosDefaultPorPais.php` (+ test) |
| Form docs | `documentos.component.{ts,html}`, historial si aplica |
| Display venta | facturacion v1/v2 (label correlativo) |
| Print / libros | helper PHP + puntos de uso HN existentes |

## Fuera de alcance

- Prefijo `001-001` editable.
- Forzar tipo Factura con/sin RTN según RTN del cliente al facturar.
- Migrar series `Factura` → `Factura sin RTN`.
- Cambiar almacenamiento de `ventas.correlativo` a string formateado.

## Criterios de aceptación

1. Admin HN: dropdown nombre incluye Factura con RTN y Factura sin RTN; no un solo “Factura”.
2. Admin HN: campo 01–20 visible y requerido en docs fiscales; no en Cotización/OC.
3. Preview en form muestra `001-001-01-00000001` (ejemplo).
4. Al facturar HN: UI muestra número formateado; en BD se guarda correlativo numérico y se incrementa el contador del documento.
5. PDF/libro HN (rutas genéricas tocadas) muestran el formato cuando hay `numero_emision`.
6. SV/CR: sin campo nuevo ni cambios de formato.

## Testing mínimo

- Unit helper `formatoCorrelativoHn` (FE y/o BE).
- Actualizar tests catálogo HN / defaults (Factura sin RTN).
- Manual: crear documento HN con emisión 01, facturar, ver display vs valor en BD.
