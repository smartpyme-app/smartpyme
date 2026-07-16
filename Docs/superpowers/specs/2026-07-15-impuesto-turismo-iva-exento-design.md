# Diseño: IVA independiente + impuesto turismo 5% + reporte

**Fecha:** 2026-07-15  
**Estado:** Aprobado en brainstorming  
**Ticket:** Error en aplicación del impuesto de turismo 5% para ventas exentas de IVA (Hostal Amapola / contexto empresa 770)  
**Tipo:** Bug fix + mejora

---

## 1. Contexto y problema

El catálogo multi-impuesto (`impuestos` → `producto_impuestos` → `venta_impuestos`) ya permite IVA 13% y turismo 5% en paralelo. El fallo es de acoplamiento:

1. El switch de cabecera `cobrar_impuestos` (“Con IVA”) pone **todos** los montos de `venta.impuestos` en 0.
2. `acumularMontosImpuestosVenta` solo acumula en líneas con `tipo_gravado === 'gravada'`, así que marcar exenta también anula el 5%.
3. En DTE, gates `venta.iva > 0` (p. ej. `MHFactura`, `MHCCF`) fuerzan exenta y `tributos = null` cuando no hay IVA, aunque existan tributos no-IVA en `venta_impuestos`.
4. Hoy `venta.iva` en facturación suele ser la **suma de todos** los impuestos del array, no solo el IVA 13%.

No existe configuración de cliente “exento de IVA sin afectar otros impuestos” ni reporte de turismo 5%.

**Restricción explícita:** no hardcodear `id_empresa = 770` ni lógica específica de Hostal Amapola. Solución genérica para cualquier empresa con impuestos especiales en catálogo.

---

## 2. Objetivos

1. Desactivar / exentar IVA 13% **sin** apagar impuestos especiales (turismo 5% y otros no-IVA).
2. Calcular turismo solo en líneas cuyo producto tenga ese impuesto asignado, sobre el monto de la línea (subtotal sin IVA).
3. Ajustar DTE para emitir tributos no-IVA cuando IVA = 0.
4. En ficha de cliente: tipo fiscal `Contribuyente` | `Consumidor Final` | `Exento` (solo afecta IVA).
5. Reporte de ventas afectas al 5% de turismo (base + monto impuesto + total a pagar).

---

## 3. Decisiones de diseño acordadas

| Tema | Decisión |
|------|----------|
| Enfoque | Desacoplar IVA vs otros tributos (enfoque A) |
| Base turismo con IVA exento | Monto de la línea; solo si el producto tiene el impuesto asignado |
| Identificación IVA | `codigo_mh === '20'` o porcentaje 13 sin código (igual que `BuildsTributosVenta::esImpuestoIva`) |
| Identificación turismo / reporte | Impuesto de catálogo con `porcentaje = 5` y no IVA; filtro opcional por `id_impuesto`; **sin** hardcode de empresa |
| Campo cliente | Nuevo `tipo_fiscal`: `Contribuyente` \| `Consumidor Final` \| `Exento` (no reusa `tipo` Persona/Empresa ni `tipo_contribuyente` Pequeño/Grande) |
| Cliente Exento | No calcula IVA 13%; sí otros impuestos del producto |
| DTE | Incluido en Fase 1: gates por monto IVA / presencia de tributos no-IVA |
| Empresa 770 | Solo contexto de negocio; cero lógica hardcodeada |
| Fases | 1 cálculo+DTE → 2 ficha cliente → 3 reporte |

---

## 4. Arquitectura

### 4.1 Flujo de cálculo (objetivo)

```
Producto.impuestos (IVA + Turismo…)
        │
        ▼
detalle.impuestos[] copia
        │
        ├── cobrar_iva / tipo_fiscal Exento / línea exenta
        │       → monto IVA = 0
        │       → línea puede ir a exenta (MH) pero con base para tributos especiales
        │
        └── impuestos no-IVA del detalle
                → monto = base_línea × %  (aunque IVA esté off)
                → acumular en venta.impuestos[]
        │
        ▼
venta.iva = solo monto IVA (código 20)
venta_impuestos = snapshot (IVA + especiales)
        │
        ▼
DTE: gravada/exenta según IVA;
     resumen.tributos / cuerpo.tributos desde venta_impuestos no-IVA
```

### 4.2 Semántica de campos de venta

| Campo | Nueva semántica |
|-------|-----------------|
| `venta.cobrar_impuestos` (UI) | Controla **solo IVA 13%** (renombrar label a “Con IVA (13%)”) |
| `venta.iva` | Solo monto de IVA (código 20 / 13%). No incluir turismo |
| `venta.impuestos[].monto` | Cada impuesto por separado |
| `detalle.tipo_gravado` | Clasificación MH de la línea (gravada/exenta/no_sujeta) respecto a IVA; no apaga tributos especiales en cálculo |
| `venta.gravada` / `exenta` / `no_sujeta` | Según tipo efectivo de línea **para IVA**; la base del turismo usa el monto de línea (exenta o gravada según corresponda) |

**Base para impuestos no-IVA:**  
`baseEspecial = detalle.gravada > 0 ? detalle.gravada : (detalle.exenta > 0 ? detalle.exenta : 0)`  
No aplicar en `no_sujeta`.

### 4.3 DTE (ajuste mínimo)

Reutilizar `BuildsTributosVenta` (`montoIvaDocumento`, `montoTributosNoIvaDocumento`, `buildTributosResumenFacturaConsumidor`, etc.).

Cambiar gates del estilo `if ($this->venta->iva > 0)` en `MHFactura` y `MHCCF` (y equivalentes que copien el patrón) a:

- Hay IVA → `montoIvaDocumento() > 0` (o gravada de líneas).
- Hay tributos no-IVA → no poner `tributos = null` solo porque IVA = 0; usar builders de códigos/montos no-IVA.
- Clasificar venta/línea como exenta de IVA cuando no hay IVA, **manteniendo** `ventaExenta` / montos y tributos especiales coherentes con montos guardados.

Notas de crédito/débito: revisar el mismo patrón si usan el gate `venta.iva > 0` para tributos de línea.

### 4.4 Ficha cliente

- Migración: `clientes.tipo_fiscal` nullable string (o enum string): `Contribuyente`, `Consumidor Final`, `Exento`. Default `null` / `Consumidor Final` según default actual de facturación.
- UI en `cliente-informacion` (select).
- Al `setCliente` en facturación: si `Exento` → `cobrar_impuestos = false` (solo IVA) y sync tipo gravado; **no** vaciar montos de impuestos no-IVA.
- `tipo_contribuyente` (Pequeño/Mediano/Grande) intacto (retención GC).

### 4.5 Reporte turismo 5%

Patrón: `LibrosIVAController` + export Maatwebsite + pestaña/sección en Contabilidad (como retenciones en `libro-iva-general`).

- Query: ventas no anuladas, no cotización, rango fechas/sucursal, join `venta_impuestos` + `impuestos` donde `porcentaje = 5` y no es IVA (`codigo_mh != '20'`).
- Filtro opcional `id_impuesto` si la empresa tiene varios 5%.
- Columnas: fecha, documento/correlativo, cliente, base (sub_total o base del impuesto), monto turismo, total período.
- Excel: `LibroImpuestoTurismoExport` (mismo estilo que `LibroRetencion1Export`).

---

## 5. Archivos ancla

| Área | Rutas |
|------|--------|
| Cálculo | `Frontend/src/app/utils/impuestos-venta.util.ts` |
| Facturación | `facturacion.component.ts` / `facturacion-v2.component.ts` / consigna |
| DTE | `Backend/app/Models/MH/MHFactura.php`, `MHCCF.php`, `BuildsTributosVenta.php` |
| Cliente | `Cliente.php`, migración, `cliente-informacion.*` |
| Reporte | `libros-iva.php`, `LibrosIVAController`, export ElSalvador, `libro-iva-general` |

---

## 6. Fuera de alcance

- Hardcode `id_empresa` / Hostal Amapola.
- Nuevo tipo_gravado MH (`exenta_iva_con_tributos`).
- Cambiar catálogo admin de impuestos (se usa el existente).
- Reemplazar `tipo` Persona/Empresa/Extranjero.
- Refactors ajenos al desacoplamiento IVA / DTE / reporte / ficha.

---

## 7. Criterios de aceptación (trazabilidad)

| Criterio | Cómo se cumple |
|----------|----------------|
| Apagar IVA 13% no apaga turismo | Fase 1 util + facturación |
| Venta exenta de IVA puede aplicar turismo | Fase 1 acumulación sobre base exenta + producto |
| Config cliente Contribuyente / CF / Exento | Fase 2 |
| Exención cliente no afecta especiales | Fase 2 + regla cálculo Fase 1 |
| Reporte 5% turismo | Fase 3 |
| Total 5% a pagar correcto | Fase 1 + validación reporte Fase 3 |
| DTE correcto con IVA 0 + turismo | Fase 1 DTE |

---

## 8. Riesgos

| Riesgo | Mitigación |
|--------|------------|
| `venta.iva` dejará de sumar turismo → pantallas/edición que usan `iva > 0` como “hay impuestos” | Auditar lecturas de `venta.iva` / `cobrar_impuestos` en edición de venta |
| Empresas con varios impuestos al 5% | Filtro `id_impuesto` en reporte |
| Líneas no_sujeta | No aplicar turismo (solo gravada/exenta) |
