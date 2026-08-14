# Diseño: Libros fiscales Honduras (formatos oficiales)

**Fecha:** 2026-07-25  
**Estado:** Aprobado en brainstorming  
**Tipo:** Feature / ajuste por país  
**Alcance:** Solo empresas Honduras (`LibroIvaPaisResolver::TIPO_HD`)

**Referencias de formato (PDF oficiales compartidos):**
- `formato-compras-honduras.pdf`
- `formato-ventas-consumidores-honduras.pdf`
- `formato-ventas-contribuyentes-honduras.pdf`

---

## 1. Contexto y problema

Hoy `libro-iva-hd` tiene un libro de ventas unificado (columnas SAR con RTN) más compras, retenciones y resumen. Los formatos oficiales requeridos son tres libros distintos:

1. Libro de compras
2. Libro de ventas a consumidor final
3. Libro de ventas a contribuyentes

Las columnas, encabezados y bloques de resumen de esos PDF deben reproducirse **exactamente** en vista web, Excel y PDF. SV/CR/general no se modifican.

---

## 2. Decisiones acordadas

| Tema | Decisión |
|------|----------|
| Formatos | Exactos a los PDF (incl. NIT/NRC/FOVIAL/COTRANS/CESC/CAI, etc.) |
| Columnas sin dato | Mostrar igual con vacío / 0 |
| Clasificación ventas | Como SV: `Factura` (+ exportación) → consumidor; `Crédito fiscal` → contribuyente |
| Navegación HN | Contribuyentes · Consumidor Final · Compras · Retenciones · Resumen |
| Retenciones / Resumen | Se mantienen sin cambios de formato en este trabajo |
| Enfoque técnico | Fork completo HN (exports, blades, vistas propias); no reusar exports SV |
| Consumidor final | Una fila por factura (no agregado diario como SV) |

---

## 3. Arquitectura

### Backend

- Reescribir / crear en `Backend/app/Exports/Contabilidad/Honduras/`:
  - `LibroComprasExport` (formato PDF compras)
  - `LibroConsumidoresExport` (nuevo)
  - `LibroContribuyentesExport` (nuevo; sustituye el uso del libro SAR unificado de ventas)
- Blades PDF en `Backend/resources/views/reportes/contabilidad/honduras/`:
  - `libro-compras.blade.php`
  - `libro-consumidores.blade.php` (nuevo)
  - `libro-contribuyentes.blade.php` (nuevo; reemplaza o deja de usarse el `libro-ventas.blade.php` SAR)
- `LibrosIvaHdController`: endpoints JSON + Excel/PDF para consumidores, contribuyentes y compras; `assertHonduras()` en todos.
- Rutas `libro-iva-hd`:
  - `/contribuyentes`, `/contribuyentes/descargar-libro`
  - `/consumidores`, `/consumidores/descargar-libro`
  - `/compras`, `/compras/descargar-libro`
  - retenciones (existente)
- Legacy `/ventas`: redirect o deprecación hacia `/contribuyentes`.

### Frontend

- Nav `libro-iva-hd`: 5 botones (Contribuyentes, Consumidor Final, Compras, Retenciones, Resumen).
- Vistas nuevas/ajustadas bajo `Frontend/src/app/views/contabilidad/libro-iva-hd/`:
  - `contribuyentes/`
  - `consumidor-final/` (o equivalente)
  - `compras/` con columnas del PDF
- Routing: rutas nuevas; `/libro-iva-hd/ventas` → redirect a contribuyentes.
- Filtros de periodo/sucursal reutilizan componentes shared existentes.
- Tablas con scroll horizontal; mismas columnas que Excel/PDF.

### Fuera de alcance

- Cambios a libros SV/CR/general.
- Rediseño de Retenciones o Resumen fiscal HN.
- Captura nueva de FOVIAL/COTRANS/CESC/máquina registradora si no existen en modelo (se muestran en 0/vacío).

---

## 4. Mapeo de columnas

### Encabezado / pie (3 libros)

- Nombre empresa, título del libro, Mes, Año, NIT, NRC (empresa).
- Pie: “Nombre y Firma de Contador”.

### Compras

| Columna PDF | Fuente |
|---|---|
| No. / Fecha emisión / N° documento | índice, `fecha`, `referencia` |
| NRC / NIT o DUI sujeto excluido / Nombre proveedor | `proveedor.ncr`, `proveedor.nit` (o DUI), `nombre_proveedor` |
| Exentas internas / internaciones / importaciones | `LibroIvaMontosHelper` + tipo doc; sin dato → 0 |
| Gravadas internas / internaciones / importaciones / Crédito fiscal | idem |
| FOVIAL / COTRANS / CESC | 0 si no hay campo |
| Anticipo IVA percibido / Total / Retención a terceros / Compras sujetos excluidos | `percepcion`, total, retención si existe, tipo sujeto excluido |

Consultas base: compras + gastos + devoluciones (signo negativo), excluyendo anuladas / tipos sin IVA fiscal según constantes actuales.

### Ventas a consumidor final (fila = factura)

| Columna PDF | Fuente |
|---|---|
| N° / Fecha / Factura N° | índice, `fecha`, `correlativo` |
| CAI N° | `documento.resolucion` o `custom_empresa.configuraciones.factura_cai` |
| N° máquina registradora | vacío / 0 si no hay |
| Exentas / Exoneradas | helper; exonerada → 0 si no existe |
| Gravadas 15% / 18% | según tasa de la venta; resto 0 |
| Total ventas / Cuenta de terceros | total propio / `cuenta_a_terceros` |
| Bloque Resumen | totales periodo: exentas, exoneradas, netas 15/18, débito, crédito |

Filtro documento: `Factura`, `Factura de exportación` (misma lógica SV consumidores). Incluir devoluciones con signo negativo cuando aplique.

### Ventas a contribuyentes (fila = documento)

| Columna PDF | Fuente |
|---|---|
| No. / Fecha / N° correlativo / NRC / Nombre | índice, fecha, correlativo, `cliente.ncr`, nombre |
| Exentas / No sujetas / Gravadas locales / Débito fiscal | `LibroIvaMontosHelper` |
| Cta. terceros / Débito cta. terceros / IVA percibido / retenido / Total | campos venta |
| Resumen operaciones | totales + filas Consumidor Final / Contribuyentes / Cta. terceros |

Filtro documento: `Crédito fiscal`. Incluir devoluciones con signo negativo.

---

## 5. API y contratos

- JSON de listado: array de filas con claves estables alineadas a columnas del libro + objeto `totales` / `resumen` cuando el PDF lo exige.
- Descarga Excel: Maatwebsite Excel con encabezados del PDF y filas de totales.
- Descarga PDF: DomPDF (o el stack actual del módulo) sobre blades HN.
- Empresa no HN → HTTP 403 con mensaje existente de país.
- Parámetros: `inicio`, `fin`, `id_sucursal` opcional (mismo `BaseLibroIVARequest` / validación actual).

---

## 6. Criterios de aceptación

1. Empresa HN ve 5 pestañas; la antigua “Ventas” unificada ya no es el libro principal.
2. Vista, Excel y PDF de compras / consumidor / contribuyente coinciden en columnas y orden con los PDF de referencia.
3. Facturas van a consumidor; créditos fiscales a contribuyente.
4. Columnas sin fuente aparecen en 0 o vacías, nunca se omiten.
5. Devoluciones/notas de crédito impactan con signo negativo.
6. Retenciones y Resumen siguen funcionando.
7. Empresa no HN no puede usar endpoints `libro-iva-hd`.
8. Libros SV/CR/general sin regresiones.

### Verificación

- Tests unitarios de mapeo, clasificación y totales de los 3 exports HN.
- Test/assert de 403 para país distinto.
- Lint/build frontend de rutas y componentes nuevos.
- Comparación visual Excel/PDF vs PDFs oficiales.
- Smoke: SV libros intactos.

---

## 7. Notas de implementación (ponytail)

- Duplicar estructura de SV solo donde haga falta; no introducir capa abstracta multi-país.
- Reutilizar `LibroIvaMontosHelper` y filtros de periodo/sucursal existentes.
- Eliminar o dejar de referenciar el export SAR unificado de ventas HN una vez existan consumidor + contribuyente.
- Un check runnable mínimo por libro (test unitario pequeño de mapeo/totales), sin frameworks de fixtures pesados.