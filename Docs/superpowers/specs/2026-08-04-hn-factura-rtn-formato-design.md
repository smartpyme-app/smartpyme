# Design: Factura con/sin RTN + formato de correlativo Honduras

**Fecha:** 2026-08-04  
**Estado:** Aprobado en conversación; ampliado con impresión default HN
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
- Fallback: si `numero_emision` es null, mostrar el correlativo plano, que es el comportamiento actual del helper; nunca inventar `001-001-00-…`.

## Impresión default Honduras

### Problema

Los nuevos nombres fiscales HN no entran en las ramas de impresión existentes, que reconocen principalmente `Factura`, `Ticket`, `Recibo` y documentos de otros países. Por eso una venta emitida como `Factura con RTN` o `Factura sin RTN` termina con “No hay un formato para este tipo de documento de venta”.

### Resolución de plantilla

1. Conservar primero cualquier plantilla especial ya asignada por empresa.
2. Cuando no exista plantilla especial y la empresa sea Honduras, usar un único default HN para los documentos fiscales del catálogo.
3. El default HN no se nombrará según una factura concreta y mostrará como título el nombre del documento recibido.
4. Mantener sincronizadas las rutas de impresión principal y legacy mientras ambas sigan activas.
5. Empresas de El Salvador y Costa Rica conservan sus resoluciones actuales.

El default aplica inicialmente a:

- Factura con RTN
- Factura sin RTN
- Ticket
- Boleta de compra
- Nota de crédito
- Nota de débito
- Recibo por honorarios profesionales
- Guía de remisión
- Comprobante de retención

Los flujos especializados existentes, por ejemplo la impresión de devoluciones, siguen teniendo prioridad. El default cubre únicamente los casos que lleguen a la impresión de venta sin una vista más específica.

### Contenido del default

- Papel US Letter, orientación vertical.
- Logo y datos configurados de empresa/sucursal.
- Título dinámico según `documento.nombre`.
- RTN de la empresa y RTN del cliente cuando exista; los campos vacíos se ocultan.
- Correlativo generado exclusivamente con `FormatoCorrelativoHn::format($documento->numero_emision, $venta->correlativo)`.
- Fecha, condición y método de pago disponibles.
- Detalle con cantidad, descripción, precio unitario y total.
- Resumen fiscal: importe exonerado, exento, gravado 15%, gravado 18%, ISV 15%, ISV 18%, descuentos y total.
- Total en letras expresado en lempiras.
- Referencias SAR disponibles: orden de compra exenta, constancia de exoneración y registro SAG.

### Footer configurable

El pie toma la configuración del tipo de documento y omite valores vacíos:

- `documento.nota`: texto libre configurable.
- `documento.resolucion`: CAI.
- `documento.rangos`: rango autorizado.
- `documento.fecha`: fecha límite de emisión.
- Leyenda fiscal “La Factura es Beneficio de Todos, EXÍJALA”.
- Leyenda de copias para cliente/emisor.

No se incorporan al default datos fiscales hardcodeados de una empresa de referencia.

### Estructura técnica

- Una vista Blade compartida para el default HN.
- Un resolver pequeño y testeable determina si el documento debe usar ese default por país y nombre.
- Los controladores conservan las excepciones de empresa y consultan el resolver antes de caer al template genérico internacional o al mensaje sin formato.
- Un componente PHP reutilizable prepara las bases, impuestos y datos del footer para la vista; los controladores no duplican esos cálculos.

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
| Form docs | `documentos.component.{ts,html}` e historial de documentos |
| Display venta | facturacion v1/v2 (label correlativo) |
| Print / libros | helper PHP + puntos de uso HN existentes |

## Fuera de alcance

- Prefijo `001-001` editable.
- Forzar tipo Factura con/sin RTN según RTN del cliente al facturar.
- Migrar series `Factura` → `Factura sin RTN`.
- Cambiar almacenamiento de `ventas.correlativo` a string formateado.
- Diseñar una apariencia diferente para cada tipo fiscal HN.
- Migrar templates personalizados existentes al nuevo default.
- Modificar automáticamente documentos ya creados sin `numero_emision`.

## Criterios de aceptación

1. Admin HN: dropdown nombre incluye Factura con RTN y Factura sin RTN; no un solo “Factura”.
2. Admin HN: campo 01–20 visible y requerido en docs fiscales; no en Cotización/OC.
3. Preview en form muestra `001-001-01-00000001` (ejemplo).
4. Al facturar HN: UI muestra número formateado; en BD se guarda correlativo numérico y se incrementa el contador del documento.
5. PDF/libro HN (rutas genéricas tocadas) muestran el formato cuando hay `numero_emision`.
6. SV/CR: sin campo nuevo ni cambios de formato.
7. Toda venta HN con un documento fiscal reconocido imprime el default HN si no tiene una plantilla especial.
8. Una plantilla especial existente conserva prioridad sobre el default del país.
9. El PDF usa el correlativo `001-001-{NN}-{8 dígitos}` y muestra el footer configurado en el tipo de documento.
10. Campos opcionales vacíos del footer no generan errores ni rótulos sin valor.

## Testing mínimo

- Unit helper `formatoCorrelativoHn` en frontend y backend.
- Actualizar tests catálogo HN / defaults (Factura sin RTN).
- Manual: crear documento HN con emisión 01, facturar, ver display vs valor en BD.
- Unit: resolver de template reconoce los nueve nombres fiscales solo para Honduras.
- Unit: resolver conserva la prioridad de plantillas especiales.
- Render/feature mínimo: el default HN puede renderizar una venta con footer completo y otra sin campos opcionales.
