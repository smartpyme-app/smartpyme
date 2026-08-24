# Design: Facturación POS táctil (v2 + catálogo)

**Fecha:** 2026-08-19  
**Estado:** Aprobado  
**Alcance:** Nueva vista de facturación tipo POS para cajeros: catálogo por categorías, ticket simplificado, modal de opciones avanzadas, activable desde Mi cuenta.  
**Fuera de alcance:** Cotizaciones en POS, factura de exportación/comercial en happy path, Boxful wizard en pantalla principal, rediseño de facturación v2 clásica, offline multi-dispositivo.

## Problema

La facturación v2 resuelve IVA por producto y reglas fiscales completas, pero la UX sigue orientada a búsqueda/modal paginado. En mostrador, farmacia o tienda, el cajero necesita seleccionar productos con un clic (categorías → productos), como el POS de restaurante, sin perder reglas de inventario (lotes, consigna, presentaciones) ni opciones fiscales (crédito, retención, DTE).

## Objetivos

1. Catálogo táctil por categorías/subcategorías/productos (misma taxonomía de inventario).
2. Layout POS: catálogo (~60%) + ticket/checkout (~40%); móvil apilado.
3. Reutilizar el **motor fiscal de v2** (IVA incluido, `onFacturar`, DTE, crédito, cliente).
4. Mantener flujos de inventario: presentaciones, origen stock (bodega/consigna), lotes, stock insuficiente.
5. Opciones avanzadas en **modal** (sheet en móvil), no inline ni acordeón.
6. Activación por configuración de empresa: tercera opción en Mi cuenta.

## Decisiones

| Decisión | Elección |
|----------|----------|
| Config Mi cuenta | `version_facturacion`: `original` \| `v2` \| `pos` (mutuamente excluyentes) |
| Motor de venta | Mismo backend y lógica que `FacturacionV2Component` |
| Ruta | `/ventas-pos/crear`; guard redirige desde `/venta/crear` si `pos` |
| Layout | A: catálogo izquierda + ticket derecha; móvil apilado (&lt;768px) |
| Catálogo API | Nuevo `inventario/pos-menu/*` (no reutilizar `restaurante/pos-menu` ni permiso `restaurante.ver`) |
| Precio en tiles | Con IVA (v2); motor interno sigue sin IVA + impuestos por línea |
| Tap producto | +1 al carrito (agrupar si `agrupar_detalles_venta`); resolver presentación/lote/origen vía pipeline v2 |
| Opciones avanzadas | Modal centrado (desktop/tablet); bottom sheet &lt;768px |
| Cliente / crédito | Siempre visibles en panel checkout (buscador + crear cliente) |
| Cotización | Fuera del POS; cotizaciones siguen en v1 |

## Layout

### Tablet / desktop

```
┌─────────────────────────────────┬──────────────────┐
│ Header: Nueva venta · Regresar  │                  │
├─────────────────────────────────┤  Ticket          │
│ Cabecera compacta (fecha,       │  líneas + acciones│
│ bodega, documento, correlativo) │  totales         │
│ Catálogo táctil                 │  cliente         │
│ (breadcrumb + buscador/escáner) │  crédito         │
│ grilla categorías / productos   │  método de pago  │
│                                 │  [Opc. avanzadas]│
│                                 │  [Facturar]      │
└─────────────────────────────────┴──────────────────┘
```

### Móvil

Catálogo arriba; ticket abajo con acciones sticky (Facturar). Modal avanzadas → sheet inferior.

## Configuración Mi cuenta

En **Mi cuenta → preferencias de venta**, el select **Versión de facturación** incluye:

| Valor | Etiqueta (i18n) | Comportamiento |
|-------|-----------------|----------------|
| `original` | Precio sin IVA (actual) | `/venta/crear` → facturación v1 |
| `v2` | Precio con IVA (actual) | `/venta/crear` → redirect `/ventas-v2/crear` |
| `pos` | POS táctil (precio con IVA) | `/venta/crear` → redirect `/ventas-pos/crear` |

Solo una versión activa. POS **requiere** motor v2 (IVA por producto); no combinar `original` + layout POS.

## Catálogo táctil

### Navegación

1. Raíz: categorías activas (`enable = true`, `subcategoria = 0`).
2. Tap categoría: subcategorías si existen; si no, productos de la categoría.
3. Tap subcategoría: productos.
4. Breadcrumb: `Categorías › Bebidas › Gaseosas`.
5. Buscador secundario: nombre/código/barcode (mín. 2 chars); escaneo código exacto auto-agrega (misma regla que buscador v2).

### Tiles

- Categoría/subcategoría: imagen o placeholder, nombre.
- Producto: imagen, nombre, **precio con IVA**, badge “Presentación” si aplica.
- Presentaciones: si módulo activo, **una ficha por presentación** (como restaurante POS).

### Resolución al tap

1. Si el tile es presentación (`id_presentacion`), usar esa fila.
2. Si el producto tiene varias presentaciones y el tile es ambiguo → sheet “Elegir presentación”.
3. Cargar producto completo para venta (`productos/buscar-by-query` o endpoint dedicado `pos-menu/productos/{id}`) con bodega, impuestos, lotes, inventarios.
4. Invocar `productoSelect` de `VentaDetallesV2Component` (origen stock, validación consigna, stock, lotes).

## Ticket (panel checkout)

### Siempre visible

- Lista de líneas: nombre, cantidad (+/-), total línea, acciones (⋮ o iconos).
- Totales: subtotal, impuestos desglosados, retenciones si aplican, total (+ propina si activa).
- Cliente: `app-buscador-clientes` + `app-crear-cliente`.
- Switch venta al crédito + fecha de pago (mismas reglas v2).
- Método de pago (+ banco / gift card si aplica).
- Observaciones (1 línea, opcional).
- Botón **Opciones avanzadas** (badge si hay opciones activas).
- Botón **Facturar** (primario).

### Acciones por línea

Reutilizar lógica de `VentaDetallesV2Component`:

| Acción | Cuándo |
|--------|--------|
| Cantidad +/- | Siempre |
| Eliminar línea | Siempre (permisos/supervisor como v2) |
| Elegir lote | `inventario_por_lotes` + lotes activos + metodología Manual |
| Editar descuento / tipo gravado | Si empresa lo permite |
| Cambiar precio / lista precios | Si aplica (cliente A/B/C) |

Sheet “Editar línea” en móvil cuando la fila no cabe.

## Modal Opciones avanzadas

Contenedor: `BsModalService` (desktop) / sheet CSS (móvil). Recibe `venta` y callbacks `sumTotal`, `setCredito`, etc.

### Contenido del modal

- Retención IVA (1%), renta (10%) — El Salvador
- Consigna (venta) — depende de crédito
- Recurrente
- Cobrar impuestos (si no FE CR)
- Cuenta a terceros
- Propina
- Canal, proyecto, num. identificación
- Exportación / factura comercial (solo si documento seleccionado lo requiere)
- Fidelización / puntos (si no va en panel principal)
- Multimoneda / tipo de cambio

### Footer del modal

- Total actualizado en vivo al cambiar switches.
- Botones: **Aplicar** (cierra y recalcula) / **Cancelar**.

Badge en botón trigger: cuenta opciones activas (retención, propina, consigna, canal, etc.).

## Integración con motor v2

| Capacidad | Implementación |
|-----------|----------------|
| Inicialización venta | Misma que `FacturacionV2Component.cargarDatosIniciales` / `loadData` |
| Agregar producto | `VentaDetallesV2Component.productoSelect` → `addDetalle` |
| Totales | `FacturacionV2Component.sumTotal` |
| Cliente | `setCliente` |
| Crédito | `setCredito` |
| Facturar / DTE | `onFacturar` / `emitirDTE` |
| Pre-cuenta restaurante | Query params / state igual que guard v2 actual |

No duplicar POST de venta ni cálculo de impuestos.

## Backend: catálogo POS ventas

Nuevo controlador `App\Http\Controllers\Api\Inventario\PosMenuVentasController` (nombre final en plan).

Rutas bajo `routes/modulos/inventario/productos.php` (permiso ventas/inventario existente, **no** `restaurante.ver`):

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/inventario/pos-menu/categorias` | Categorías raíz + count subcats |
| GET | `/inventario/pos-menu/categorias/{id}/contenido` | Subcats o productos |
| GET | `/inventario/pos-menu/subcategorias/{id}/productos` | Productos subcategoría |
| GET | `/inventario/pos-menu/buscar?q=` | Búsqueda rápida (límite 30) |
| GET | `/inventario/pos-menu/productos/{id}` | Ficha + datos venta (bodega, impuestos, lotes) |

Query params comunes: `id_bodega`, `id_sucursal` (stock y filtros).

Reutilizar queries estáticas de `PosMenuController` donde aplique (DRY en trait o clase base compartida), con diferencias:

- Precio en JSON: **con IVA** para tiles (v2).
- Filtro stock opcional en listado (no bloquear tile; validar al agregar).
- Permisos de ventas, no restaurante.

## Componentes frontend

| Pieza | Responsabilidad |
|-------|-----------------|
| `FacturacionPosComponent` | Shell POS, estado venta, checkout, facturar |
| `PosCatalogoVentasComponent` | Grilla táctil (adaptar de `PosCatalogoComponent`) |
| `PosTicketVentasComponent` | Lista líneas + totales compactos |
| `PosOpcionesAvanzadasComponent` | Modal/sheet opciones fiscales |
| `PosLineaVentaSheetComponent` | Editar línea (lote, descuento, qty) |
| `VentaDetallesV2Component` | Pipeline producto (oculto o `@Input() modoSoloPipeline`) |
| `pos-menu-ventas.util.ts` | Mapper tile → payload `productoSelect` |
| `FacturacionVersionGuard` | Redirect `pos` → `/ventas-pos/crear` |

## Fuera de alcance v1 POS

- Cotizaciones
- Boxful wizard inline (post-factura igual que v2 si aplica)
- Factura exportación / comercial en UI principal (solo modal avanzadas si documento lo exige)
- Paquetes / citas como tiles (mantener acceso secundario en modal o botón “Más productos” v2 si módulo activo — fase 2 opcional)

## Testing

- PHPUnit: queries catálogo ventas (categorías, presentaciones, precio con IVA).
- Jasmine: mapper producto POS → detalle v2; badge opciones avanzadas; guard redirect.
- Manual QA: tap producto con lote manual, origen consigna, presentación, crédito con cliente, facturar contado.

## Referencias

- Restaurante POS: `Docs/superpowers/specs/2026-08-04-restaurante-pos-v2-cuenta-mesa-design.md`
- Facturación v2: `Frontend/src/app/views/ventas/facturacion/facturacion-tienda-v2/`
- Config versión: `empresa.component.ts` → `version_facturacion`
