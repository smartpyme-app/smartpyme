# Diseño: Notas de Crédito y Débito para Honduras

**Fecha:** 2026-08-07  
**Estado:** Aprobado / Implementado  
**Tipo:** Feature fiscal (HN) + plantillas empresa 420  
**Fuente fiscal:** SAR-927 / Solicitud de autorización de autoimpresor (Inversiones André)  
**Enfoque:** Extender flujo de devoluciones de venta existente (no crear emisión NC/ND como ventas nuevas)

---

## 1. Contexto y problema

SmartPyme ya emite Notas de Crédito y Notas de Débito como **devoluciones de venta** ligadas a una factura (`devoluciones_venta` + `id_venta` + documento fiscal). El soporte completo (FE MH, menú desde “Crédito fiscal”, plantillas por empresa) está orientado a **El Salvador**.

Para **Honduras**:
- Existe impresión de factura y libro de ventas estilo SAR.
- El menú de ventas solo ofrece NC/ND cuando `nombre_documento == 'Crédito fiscal'`; en HN suele usarse **Factura**.
- El libro de ventas incluye devoluciones, pero **todas** se etiquetan como “Nota de crédito” con montos negativos (no distingue débito).
- **Inversiones André (ID 420)** solo tiene plantilla de factura; no hay NC/ND con CAI, rango y fechas de autorización.

Se requiere soporte completo de NC/ND en Honduras (emisión sobre facturas, impacto en libro de ventas) y actualizar el formato de impresión de la empresa 420.

---

## 2. Objetivos

1. Permitir emisión de NC y ND vinculadas a una factura previa para **todas las empresas de Honduras**.
2. Reflejar correctamente NC/ND en **Libros fiscales → Ventas** (descripción y signo fiscal).
3. Crear plantillas de impresión NC/ND para **Inversiones André (420)** con CAI, rango autorizado y fechas del PDF SAR-927.
4. Otras empresas HN usan plantilla genérica (sin pie SAR de André).
5. No emitir DTE/FE de El Salvador al crear NC/ND en Honduras.

---

## 3. Decisiones acordadas

| Tema | Decisión |
|------|----------|
| Fechas en plantillas 420 | Autorización **02/03/2026**, límite **02/03/2027** (PDF / factura actual; no las del texto de la tarea “5 marzo”) |
| Alcance emisión | Todas las empresas **Honduras** |
| Plantillas SAR (CAI/rango) | Solo empresa **420**; resto HN → genérica |
| Enfoque técnico | Extender **devoluciones** existentes (opción 1) |
| Libro ventas | NC = montos **negativos** + descripción “Nota de crédito”; ND = montos **positivos** + “Nota de débito” (práctica SAR) |
| CAI / rango / fechas en Documentos UI | **Fuera de alcance** (otro ticket). Hardcode en Blades 420, igual que la factura actual |
| Seed/migración de documentos | No. El equipo crea documentos NC/ND en UI si el correlativo interno lo requiere; datos SAR visibles salen del template |
| FE Honduras / MH DTE | No aplicar |
| Libro compras / retenciones | Fuera de alcance |
| Rediseño grande del libro SAR | Fuera de alcance |

### Datos fiscales hardcodeados (empresa 420)

| Campo | Valor |
|-------|--------|
| CAI | `4C127A-574649-D93CE0-63BE03-0909A0-30` |
| Fecha autorización | `02/03/2026` |
| Fecha límite emisión | `02/03/2027` |
| Rango NC (06) | `000-002-06-00000201 / 000-002-06-00000220` |
| Prefijo número NC | `000-002-06-` + correlativo 8 dígitos |
| Rango ND (07) | `000-002-07-00000201 / 000-002-07-00000220` |
| Prefijo número ND | `000-002-07-` + correlativo 8 dígitos |

Factura 01 ya está alineada en `Factura-Inversiones-Andre.blade.php` (CAI, rango 01, mismas fechas); no requiere cambio salvo verificación.

---

## 4. Arquitectura

### 4.1 Emisión (UI + backend)

**Frontend — menú ventas** (`ventas.component.html`):

Hoy:

```html
*ngIf="... && venta.nombre_documento == 'Crédito fiscal'"  <!-- NC y ND -->
```

Objetivo: para Honduras, permitir también cuando el documento sea **Factura** (y mantener CCF para no romper SV/otras).

Condición sugerida (concepto):

- Mostrar NC/ND si el usuario puede editar **y**:
  - `nombre_documento == 'Crédito fiscal'` (comportamiento actual), **o**
  - empresa es Honduras **y** `nombre_documento == 'Factura'`.

Reutilizar:

- Ruta `/devolucion-venta/nueva` con `queryParams: { id_venta, tipo_documento: 'nota_credito' | 'nota_debito' }`.
- Componentes existentes de devolución.

**Backend:**

- Seguir `DevolucionVentasController` / `POST .../devolucion/venta/facturacion`.
- Mantener validación de montos (neto NC−ND vs total factura).
- Garantizar que el flujo MH (`emitirDTENotaCredito` / `MHNotaCredito` / `MHNotaDebito`) **no** se dispare para `pais === 'Honduras'` (mismo criterio que ya se use para no-FE).

### 4.2 Impresión (empresa 420)

**Archivos nuevos** (basados en `Factura-Inversiones-Andre.blade.php`):

- `Backend/resources/views/reportes/facturacion/formatos_empresas/NC-Inversiones-Andre.blade.php`
- `Backend/resources/views/reportes/facturacion/formatos_empresas/ND-Inversiones-Andre.blade.php`

Cambios respecto a factura:

- Título: **NOTA DE CRÉDITO** / **NOTA DE DÉBITO** (no “FACTURA”).
- Número con prefijo `000-002-06-` o `000-002-07-`.
- Pie: rango 06 o 07; mismo CAI y fechas.
- Incluir referencia a factura origen (`id_venta` / correlativo de la venta) de forma consistente con el layout.

**Cableado** en `DevolucionVentasController::generarDoc` (patrón ya usado por empresas 187, 250, 128):

- Si `id_empresa == 420` y documento es Nota de crédito → `NC-Inversiones-Andre`.
- Si `id_empresa == 420` y documento es Nota de débito → `ND-Inversiones-Andre`.
- Otras empresas → `nota-credito` genérico (comportamiento actual).

Revisar si hay otro endpoint de impresión de devolución (`/api/reporte/devolucion/...`) y aplicar el mismo switch si aplica.

### 4.3 Libro de Ventas Honduras

Archivos principales:

- `Backend/app/Exports/Contabilidad/Honduras/LibroVentasExport.php`
- `Backend/resources/views/reportes/contabilidad/honduras/libro-ventas.blade.php`
- UI `libro-iva-general` si consume la misma construcción de filas

Hoy en `filasDevoluciones`:

- `descripcion` siempre `'Nota de crédito'`
- montos siempre negativos (`-1 * ...`)

Objetivo:

| Tipo documento devolución | `descripcion` | Signo montos (exenta/gravada/ISV) |
|---------------------------|---------------|----------------------------------|
| Nota de crédito | Nota de crédito | Negativo |
| Nota de débito | Nota de débito | Positivo |

Clasificar por `nombre_documento` de la devolución (o relación `documento`), no asumir que toda devolución es NC.

Campos ya calculados (`nota_credito_numero`, `numero_factura_relacionada`, `fecha_factura_relacionada`): usarlos si el layout Excel/PDF/UI ya los contempla o pueden mostrarse sin rediseñar columnas; si no caben limpios, priorizar descripción + `no_factura` del NC/ND y referencia a factura origen donde sea natural.

Totales del período = suma de filas (facturas + NC negativas + ND positivas).

### 4.4 Fuera de alcance (ticket futuro)

- Campos CAI / rango / fecha límite en pantalla **Documentos**.
- Seed automático de rangos para 420.
- Guía de remisión (08) del mismo PDF.
- FE electrónica Honduras.

---

## 5. Manejo de errores y validaciones

- Sin documento NC/ND configurado / correlativo: mantener mensajes actuales del flujo de devolución.
- Monto que excede saldo disponible de la factura: rechazar como hoy.
- Empresa no Honduras: no cambiar reglas de menú salvo CCF existente.
- Impresión 420 sin documento tipado: no caer en plantilla factura; usar genérica o error claro.
- No invocar APIs MH en empresas HN.

---

## 6. Plan de pruebas (aceptación)

1. Empresa HN (no 420): desde una **Factura**, emitir NC y ND; listar devoluciones; imprimir (genérica).
2. Empresa 420: emitir NC → PDF con título NC, prefijo `000-002-06-`, rango 06, CAI y fechas 02/03/2026–02/03/2027; referencia a factura.
3. Empresa 420: emitir ND → análogo con `000-002-07-` y rango 07.
4. Libro ventas del período: NC negativa etiquetada; ND positiva etiquetada; totales coherentes.
5. Empresa El Salvador: menú NC/ND en CCF y FE sin regresiones.
6. Factura impresa 420 sin cambios indeseados en CAI/rango/fechas.

---

## 7. Archivos clave a tocar

| Área | Archivo |
|------|---------|
| Menú NC/ND HN | `Frontend/.../ventas/ventas.component.html` (+ helper país si hace falta en `.ts`) |
| Devolución (si filtros docs) | `Frontend/.../devoluciones/devolucion-nueva/*` |
| Impresión | `DevolucionVentasController.php` → `generarDoc` |
| Templates | `NC-Inversiones-Andre.blade.php`, `ND-Inversiones-Andre.blade.php` |
| Libro Excel | `LibroVentasExport.php` |
| Libro PDF | `libro-ventas.blade.php` (HN) |
| Referencia layout | `Factura-Inversiones-Andre.blade.php` |

---

## 8. Criterios de éxito

- [ ] Empresas Honduras pueden crear NC/ND sobre Factura.
- [ ] Libro Ventas distingue NC vs ND con signos correctos.
- [ ] PDF 420 NC/ND cumple SAR-927 (CAI, rango, fechas).
- [ ] Resto HN imprime genérico; SV no se rompe.
- [ ] Sin hardcode de CAI en Documentos UI (aplazado).
