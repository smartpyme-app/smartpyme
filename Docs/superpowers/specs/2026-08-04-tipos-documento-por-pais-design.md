# Design: Tipos de documento fiscal por país (SV / HN / CR)

**Fecha:** 2026-08-04  
**Estado:** Aprobado en conversación; pendiente revisión final del archivo  
**Alcance:** Catálogo Admin Documentos, defaults al crear empresa/sucursal, y filtros en facturación de ventas, compras y gastos.

## Problema

Según la normativa SAR (Honduras), el documento fiscal principal es la Factura (con o sin RTN del cliente) y existen documentos complementarios específicos. El sistema hoy agrupa Honduras con El Salvador en un catálogo “default” que incluye tipos que **no aplican en HN** (p. ej. Crédito fiscal, Sujeto excluido, Factura de exportación MH).

Costa Rica ya tiene catálogo propio; El Salvador está correcto; faltaba separar Honduras.

## Objetivos

1. Mostrar solo tipos de documento permitidos/operativos según el país de la empresa.
2. Agregar el catálogo SAR de Honduras.
3. Mantener intactos los catálogos de El Salvador y Costa Rica.
4. Extender el patrón existente (listas FE + defaults backend), sin tabla nueva ni API.

## Fuera de alcance

- Migración/renombrado de series ya creadas (p. ej. “Crédito fiscal” en empresas HN históricas).
- Emisión electrónica HN (CAI ya existe en capa de reportes/PDF).
- Cambios a tipos de documento de identidad (DUI/NIT/RTN del cliente).
- Ajustes al catálogo de Costa Rica.
- Libros fiscales SAR (cubierto en `2026-07-25-libros-fiscales-honduras-design.md`).

## Contexto actual

- Los tipos son strings en `documentos.nombre` (ventas) y `tipo_documento` (compras/gastos).
- País: `empresas.pais` / `empresas.cod_pais`, resuelto con `resolveCodigoPaisFe()` (FE) / `FacturacionElectronicaCountryResolver`.
- Patrón existente CR vs resto:
  - `Frontend/src/app/views/ventas/documentos/documento-nombre-options.ts`
  - `Backend/app/Support/Admin/DocumentosDefaultPorPais.php`
- Filtros por whitelist en facturación ventas/compras/gastos.

## Decisiones

| Decisión | Elección |
|----------|----------|
| Enfoque | Extender listas por país (mismo patrón que CR) |
| Con/sin RTN | Una sola “Factura”; RTN vive en el cliente |
| Datos históricos | Sin migración; no se pueden crear nuevos tipos inválidos |
| País desconocido / GT / otros | Mantener lista SV (comportamiento actual del default) |

## Catálogos

### El Salvador (`SV`) — sin cambios

- Factura  
- Crédito fiscal  
- Ticket  
- Cotización  
- Recibo  
- Orden de compra  
- Nota de crédito  
- Nota de débito  
- Sujeto excluido  
- Factura de exportación  
- Abono de Venta  
- Factura comercial  

### Honduras (`HN`) — nuevo

**Fiscales / complementarios SAR:**

- Factura  
- Ticket  
- Boleta de compra  
- Nota de crédito  
- Nota de débito  
- Recibo por honorarios profesionales  
- Guía de remisión  
- Comprobante de retención  

**Operativos del sistema:**

- Cotización  
- Orden de compra  
- Recibo  
- Abono de Venta  

**Excluidos en HN (no se ofrecen al crear):** Crédito fiscal, Sujeto excluido, Factura de exportación, Factura comercial.

### Costa Rica (`CR`) — sin cambios

- Factura Electrónica  
- Tiquete Electrónico  
- Cotización  
- Recibo  
- Orden de compra  
- Factura Electrónica de Compra  
- Nota de Crédito Electrónica  
- Nota de Débito Electrónica  
- Abono de Venta  

## Comportamiento por superficie

### 1. Admin → Documentos (crear/editar nombre)

`documentoNombreOpciones(empresa)` devuelve:

- `CR` → lista CR  
- `HN` → lista HN  
- resto → lista SV  

También alinear el modal de historial si hoy hardcodea opciones SV.

### 2. Defaults al crear empresa/sucursal

`DocumentosDefaultPorPais::nombres()`:

| País | Series iniciales |
|------|------------------|
| CR | Tiquete Electrónico, Factura Electrónica, Cotización, Orden de compra |
| HN | Ticket, Factura, Cotización, Orden de compra |
| SV / resto | Ticket, Factura, Crédito fiscal, Cotización, Orden de compra |

### 3. Filtros en facturación de ventas

Whitelist de ventas (extender la existente) para HN:

- Factura  
- Ticket  
- Recibo  
- Guía de remisión  
- Abono de Venta  

Mantener equivalentes actuales SV/CR. Cotización y Orden de compra siguen fuera del flujo de venta normal (como hoy).

### 4. Filtros en compras / gastos

Whitelist de compras/gastos para HN:

- Factura  
- Boleta de compra  
- Recibo por honorarios profesionales  
- Comprobante de retención  
- Recibo  

Mantener reglas actuales: excluir notas de crédito/débito y cotización/OC donde ya se excluyan. Incluir tipos SV/CR existentes sin romper.

## Arquitectura (mínima)

```
empresa.pais / cod_pais
        │
        ▼
resolveCodigoPaisFe() / CountryResolver
        │
        ├── documentoNombreOpciones()     → dropdown Admin
        ├── DocumentosDefaultPorPais      → seed series
        └── whitelists ventas/compras     → filtrar documentos cargados
```

No se cambia el modelo de datos: los nombres nuevos HN son strings válidos en `documentos.nombre` / `tipo_documento`.

Constantes sugeridas (Frontend):

```ts
export const NOMBRE_DOCUMENTO_HN = {
  factura: 'Factura',
  ticket: 'Ticket',
  boletaCompra: 'Boleta de compra',
  notaCredito: 'Nota de crédito',
  notaDebito: 'Nota de débito',
  reciboHonorarios: 'Recibo por honorarios profesionales',
  guiaRemision: 'Guía de remisión',
  comprobanteRetencion: 'Comprobante de retención',
  // operativos reutilizan nombres globales: Cotización, Orden de compra, Recibo, Abono de Venta
} as const;
```

Helpers de nota crédito/débito existentes (`esNombreNotaCredito`, etc.) siguen válidos para HN porque usan los mismos nombres base que SV.

## Archivos principales a tocar

| Archivo | Cambio |
|---------|--------|
| `Frontend/.../documento-nombre-options.ts` | Rama HN + `documentoNombreOpciones` SV/HN/CR |
| `Frontend/.../fe-pais.util.ts` | Usar `FE_PAIS_HN` si ya existe; si no, reutilizar constante existente |
| `Frontend` facturación ventas / compras / gastos | Extender whitelists con nombres HN |
| `Frontend/.../documento-historial.component.html` | Usar opciones por país (si está hardcodeado) |
| `Backend/.../DocumentosDefaultPorPais.php` | Defaults HN |

## Criterios de aceptación

1. Empresa SV: dropdown Admin igual que hoy.  
2. Empresa CR: dropdown Admin igual que hoy.  
3. Empresa HN: dropdown Admin muestra solo el catálogo HN; no aparecen Crédito fiscal / Sujeto excluido / Factura de exportación / Factura comercial.  
4. Nueva empresa HN: se crean Ticket, Factura, Cotización, Orden de compra (sin Crédito fiscal).  
5. En facturación ventas HN solo aparecen series cuyos nombres están en la whitelist de ventas HN.  
6. En compras/gastos HN aparecen Boleta de compra, Recibo por honorarios, Comprobante de retención, etc.  
7. Empresas HN con series históricas inválidas: siguen visibles si ya existen en BD, pero no se pueden crear nuevas con esos nombres.  

## Riesgos y mitigaciones

| Riesgo | Mitigación |
|--------|------------|
| Whitelist ventas/compras demasiado estricta rompe flujos | Extender listas existentes; no eliminar nombres SV/CR |
| Nombres HN nuevos no reconocidos en informes | Libros SAR / PDF ya usan Factura/Ticket; tipos nuevos no son obligatorios en venta hasta que se creen series |
| Historial hardcodeado SV | Incluir fix menor en el mismo PR |

## Testing (mínimo)

- Unit/helper: `documentoNombreOpciones` con empresa SV / HN / CR / null.  
- Manual: crear documento en empresa HN y verificar opciones; misma prueba en SV y CR.  
- Manual: abrir facturación venta y compra en empresa HN y confirmar filtros.
