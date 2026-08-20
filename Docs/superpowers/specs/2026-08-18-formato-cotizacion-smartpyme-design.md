# Diseño: Formato de cotización SmartPyme (empresa ID 2)

**Fecha:** 2026-08-18  
**Estado:** Aprobado  
**Tipo:** Rediseño de PDF (misma ruta, mismos datos)  
**Enfoque:** Reescribir la plantilla Blade de cotización SmartPyme para acercarla al PDF de referencia. Sin endpoint nuevo, sin cambio de CRUD.

**Referencia visual:** `Formato_Cotizacion.pdf` (4 páginas).

---

## 1. Problema

La cotización de la empresa SmartPyme (ID 2) se imprime con `cotizacion-smartpyme.blade.php`: logo/datos de empresa, tabla de ítems y totales. No coincide con el formato comercial de referencia (carta, pie azul regional, tablas con encabezado marino, módulos, capacitación, términos y firma del ejecutivo).

## 2. Objetivos

1. Al generar/imprimir una cotización de la empresa ID 2, el PDF debe verse lo más parecido posible a `Formato_Cotizacion.pdf`.
2. Conservar los datos dinámicos de la cotización: cliente, fecha, correlativo, productos/servicios, precios, descuentos, impuesto y total.
3. Conservar los textos comerciales fijos del documento de referencia.
4. No cortar ni superponer textos. Soportar pocas y muchas líneas. Partir de página con encabezado de tabla repetido.
5. No cambiar el funcionamiento del módulo de cotizaciones ni el PDF de otras empresas.

## 3. Fuera de alcance

- Formatos de cotización de empresas distintas a ID 2 (420, 498, default).
- Cambiar el `if` de `generarDoc` (ya carga esta vista cuando `id_empresa == 2`).
- CRUD, estados, facturación, Excel, permisos de vendedores.
- Aplicar el diseño a empresa ID 13.
- Fotos de producto en la tabla.
- Firma/sello genéricos de empresa (`mostrar_sello_firma_cotizacion`).
- Editar textos comerciales desde la UI.
- Convertir la cotización en un selector de planes Professional/Corporativo fijos del PDF de referencia.

## 4. Decisiones

| Tema | Decisión |
|------|----------|
| Empresa | Solo `id_empresa == 2` |
| Archivo | Reescribir `Backend/resources/views/reportes/facturacion/formatos_empresas/cotizacion-smartpyme.blade.php` |
| Controller | Sin cambio de ruta ni de vista |
| Papel | US Letter, portrait (igual que hoy) |
| Motor | DomPDF, misma llamada `PDF::loadView(...)` |
| Encabezado logo | Solo página 1 |
| Pie azul | Todas las páginas |
| Tabla | Una tabla, N filas, 3 columnas al estilo del PDF |
| Cantidad y totales | Bloque aparte, debajo de la tabla |
| Textos comerciales | Fijos, copiados del PDF de referencia |
| Fotos de producto | No |
| Firma empresa/sello | No; se usa el bloque “Atentamente” del PDF |
| Observaciones de la cotización | Si hay texto, nota extra después del bloque de totales; los términos del PDF se quedan |

## 5. Fijo vs dinámico

### Fijo (copy y chrome del PDF)

- Logo SmartPyme y dirección: Edificio Colabora loca 1-2, Paseo General Escalón, San Salvador, San Salvador.
- Carta de presentación y “Presencia Regional y Adaptación Normativa”.
- “¿Por qué elegir SmartPyme?” y sus 5 viñetas.
- Título “Propuesta Económica” y la frase “Presentamos la Propuesta estructurada para la adquisición:”.
- Nota de sucursales/usuarios adicionales y “El cobro corresponde a la licencia…”.
- “Módulos e Integraciones Incluidas” y el listado (Ventas … Módulos extras).
- “Nuestros Planes de Implementación y Capacitación”, tabla de capacitación (contenido de referencia) y viñetas de material/soporte/plazos.
- “Términos y Condiciones del Servicio” (6 puntos).
- Cierre “Atentamente”, foto circular y datos de Jorge Alberto Casco.
- Pie: San Salvador, email, web, Guatemala, Honduras y teléfonos del PDF.

El placeholder “(tipo de plan)” del título de referencia **no** se rellena: el título queda “Propuesta Económica”.

### Dinámico (datos de la cotización)

| Campo en el PDF | Origen |
|-----------------|--------|
| Fecha (`d – m – Y`) | `$venta->fecha` |
| Cotización # | `$venta->correlativo` (junto a la fecha) |
| Empresa | `$venta->nombre_cliente` |
| Estimado(a): | Etiqueta fija; el nombre del cliente ya va en Empresa |
| Columna Servicio | Nombre del producto/servicio del detalle |
| Columna Alcance y Cobertura | Si `cotizacion_mostrar_descripcion` es true: descripción del detalle (o la del producto si el detalle no trae). Si es false: celda vacía, sin repetir el nombre. |
| Columna Precio | Precio unitario + símbolo de moneda de la empresa |
| Bloque cantidades | Una línea por ítem: nombre + cantidad |
| Subtotal / descuento / IVA o ISV / total | `$venta->sub_total`; si algún detalle tiene `descuento > 0`, una fila Descuentos con la suma; `$venta->iva`; `$venta->total` |
| Etiqueta impuesto | IVA salvo Honduras → ISV (igual que la plantilla actual) |
| Observaciones | `$venta->observaciones` si no está vacío |

La lógica de nombre vs descripción reutiliza el criterio de `cotizacion_mostrar_descripcion` que ya pasa `cotizacionPdfViewData`. No se usan imágenes aunque `cotizacion_mostrar_imagenes_productos` esté activo.

## 6. Layout

### Página 1

1. Logo izquierda, dirección derecha, línea gris.
2. Fecha y correlativo a la derecha.
3. **Empresa:** nombre del cliente. **Estimado(a):**
4. Carta fija + presencia regional.
5. Línea gris + “¿Por qué elegir SmartPyme?”.

Si el contenido de página 1 no cabe (márgenes DomPDF), el resto de la carta baja a la página 2; no se comprime tipografía.

### Propuesta económica (después de la carta)

Tabla única:

| Servicio | Alcance y Cobertura | Precio |
| encabezado fondo azul marino, texto blanco | | precios alineados a la derecha |

Una fila por `$venta->detalles`. Bordes gris claro, padding amplio.

Debajo:

1. **Cantidades:** lista nombre + cantidad.
2. **Totales** (bloque a la derecha, como un resumen): Subtotal, Descuentos si > 0, IVA/ISV, Total en negrita.
3. Observaciones de la cotización, si existen.
4. Notas fijas de sucursales/licencia.
5. Módulos, capacitación, términos, firma.

### Pie (todas las páginas)

Barra azul marino a ancho completo, 4 columnas blancas con iconos/texto del PDF. En DomPDF: `position: fixed; bottom: 0` más `margin-bottom` de `@page` para que el cuerpo no lo tape.

### Saltos de página

- `thead { display: table-header-group; }` para repetir el encabezado azul.
- Evitar cortar una fila a la mitad (`page-break-inside: avoid` en `tr` en la medida que DomPDF lo respete).
- El bloque de totales no se parte: si no cabe, pasa entero a la página siguiente.
- Cotización de 1 ítem: una o dos páginas según la carta fija.
- Cotización larga: la tabla ocupa las páginas que haga falta; módulos/términos van después de la tabla.

## 7. Assets

Copiar desde el documento de referencia a `Backend/public/img/` (nombres estables, sin espacios):

- Logo SmartPyme.
- Foto de Jorge (circular en CSS, no hace falta recorte previo).
- Iconos del pie (ubicación, mail, web, teléfono) o equivalentes SVG/PNG simples.

No usar el logo de `empresa.logo` en esta plantilla: el formato es de marca SmartPyme, no el logo configurable de la cuenta.

## 8. Datos y flujo

Sin cambio de API. `generarDoc($id, 'cotizacion')` sigue:

1. Carga `CotizacionVenta` con detalles, cliente, empresa.currency.
2. Arma `$pdfData` con `cotizacionPdfViewData`.
3. Si `Auth::user()->id_empresa == 2`, `loadView('...cotizacion-smartpyme', $pdfData)`.

La vista lee `$venta` igual que hoy. No hay campos nuevos en BD.

## 9. Pruebas

1. Conservar `CotizacionPdfViewDataTypeTest` (el controller no cambia el contrato).
2. Un test de vista o de contenido HTML de la plantilla: con un `$venta` mínimo (cliente, fecha, correlativo, 1 detalle, totales) el HTML incluye nombre del cliente, fecha, nombre del ítem y el texto fijo “¿Por qué elegir SmartPyme?”. Falla si se pierde el binding dinámico o el copy fijo.
3. Verificación manual contra `Formato_Cotizacion.pdf`: 1 ítem, varios ítems (salto de página), ítem con descuento, observaciones vacías vs con texto.

No se mockea un PDF pixel-perfect en CI.

## 10. Archivos

| Archivo | Cambio |
|---------|--------|
| `Backend/resources/views/reportes/facturacion/formatos_empresas/cotizacion-smartpyme.blade.php` | Reescritura visual |
| `Backend/public/img/` | Assets de logo, foto, iconos de pie |
| `Backend/tests/Unit/Ventas/Cotizaciones/` | Test de binding de la plantilla |
| `CotizacionesController.php` | Sin cambio |

---

## Spec self-review

- Sin TBD. Correlativo incluido junto a la fecha (aprobado en diseño).
- Empresa 13 explícitamente fuera de alcance.
- Una tabla de N filas, no una mini-tabla por ítem.
- Cantidad no va en la tabla de 3 columnas; va en el bloque inferior.
- Controller y datos de cotización no se rediseñan.
