# Design: Restaurante POS — comensales, nombres al dividir, presentaciones

**Fecha:** 2026-08-17  
**Estado:** Aprobado  
**Alcance:** Tres mejoras en la pantalla de mesa (`restaurante/cuenta/:id`) y APIs de sesión / pre-cuenta / menú POS.  
**Fuera de alcance:** Mapa de mesas, nombres de comensales al abrir mesa, `genera_comanda` por presentación, tope por `mesa.capacidad`, modificadores, rediseño de inventario.

## Problema

1. Al dividir la cuenta, las personas se etiquetan `Persona 1`, `Persona 2`… No se puede poner el nombre de quien paga.
2. Si llega alguien más a una mesa ya abierta, no hay forma de cambiar `num_comensales` desde el POS (el API de update ya existe).
3. Con el módulo de presentaciones activo, el POS de restaurante no lista presentaciones. En facturación sí se aplanan. `genera_comanda` vive en el producto y debe heredarse.

## Objetivos

1. Al dividir (equitativa o por ítems), poder nombrar a cada pagador; vacío = `Persona N`; el nombre sale en pre-cuenta, lista POS y ticket.
2. En el encabezado del POS, cambiar comensales con `+` / `−` y persistir al instante (1–99).
3. Si el módulo de presentaciones está activo, el catálogo POS muestra cada presentación como ficha aparte (igual que facturación), con precio de la presentación. `genera_comanda` y destino siguen en el producto.

## Decisiones

| Decisión | Elección |
|----------|----------|
| Enfoque | Reutilizar APIs existentes; cambios mínimos de esquema |
| Comensales UI | Stepper `+` / `−` en el encabezado; sin modal |
| Rango comensales | 1–99; no limitar a `mesa.capacidad` |
| Dirección | Subir y bajar |
| Persistencia comensales | `PUT /sesiones-mesa/{id}` existente; solo `abierta` o `pre_cuenta` |
| Nombres | Opcionales; fallback `Persona {n}` (1-based) |
| Dónde viven los nombres | Columna `nombre_pagador` en `pre_cuentas_restaurante` |
| Número de pre-cuenta | Sin cambio (`PC-{mesa}-{n}`); el nombre es etiqueta extra |
| Presentaciones en catálogo | Aplanar como facturación: ficha base + una ficha por presentación |
| `genera_comanda` | Solo en el producto; las presentaciones heredan |
| Destino cocina/barra | Solo en el producto |
| Precio de línea | `producto.precio` si no hay presentación; `presentacion.precio_venta` si hay |
| Stock | Validar `cantidad × factor_conversion` en unidades base |
| Facturar | `prepararFactura` incluye `id_presentacion` y `descripcion` = nombre a mostrar |

## Datos / API

### Comensales

- Tabla: `restaurante_sesiones_mesa.num_comensales` (ya existe).
- `SesionMesaController::update`: aceptar `num_comensales` (integer 1–99) y `observaciones` como hoy.
- Restringir update a sesiones `abierta` o `pre_cuenta`. Si está `cerrada` (u otro estado), 422.
- Frontend: `RestauranteService.actualizarSesion(id, { num_comensales })` → ese PUT.
- El valor guardado sigue siendo el default de `numPagadores` al abrir “Solicitar cuenta” (mínimo 2 al dividir, como hoy).

### Nombres al dividir

- Migración: `pre_cuentas_restaurante.nombre_pagador` nullable string(80).
- Payload de `solicitarCuenta` / `dividir`:

```json
{
  "dividir": {
    "tipo": "equitativa",
    "num_pagadores": 3,
    "nombres": ["Ana", "", "Luis"]
  }
}
```

- `nombres` es opcional. Índice 0 = pagador 1.
- Normalización backend:
  - trim;
  - vacío o ausente → `Persona {i+1}`;
  - más corto que N → rellenar con fallback;
  - más largo que N → recortar;
  - máximo 80 caracteres;
  - nombres repetidos permitidos.
- Cada `PreCuenta` creada en la división guarda su `nombre_pagador`.
- División completa (una sola cuenta): no usa nombres.

### Presentaciones

- Migración: `orden_detalle_restaurante.id_presentacion` nullable FK → `producto_presentaciones.id` (`onDelete: set null`).
- Nombre a mostrar (catálogo, orden, comanda, factura): `{nombre_comercial} ({producto.nombre})` si hay presentación; si no, `producto.nombre`.
- `SesionMesaController::show` y cargas de orden: eager load `ordenDetalle.producto` y `ordenDetalle.presentacion`.
- `PosMenuController::mapProductos`:
  - Shape estable siempre: incluye `id_presentacion` (null en ficha base).
  - Si `Empresa::isModuloPresentaciones()`: eager load `presentaciones`; emitir ficha del producto (`id_presentacion: null`) y una ficha por presentación (`nombre` ya aplanado).
  - Si el módulo está off: una ficha por producto, `id_presentacion: null`.
- Shape de ficha POS:

| Campo | Producto base | Presentación |
|-------|---------------|--------------|
| `id` | `producto.id` | `producto.id` |
| `id_presentacion` | `null` | `presentacion.id` |
| `nombre` | `producto.nombre` | `nombre_comercial (producto.nombre)` |
| `precio` | `producto.precio` | `presentacion.precio_venta` |
| `img` | imagen del producto | misma imagen |
| `tipo` | producto | producto |
| `genera_comanda` | producto | mismo valor del producto |

- Búsqueda POS (`pos-menu/buscar`): misma expansión.
- `POST .../items`: `id_presentacion` opcional. Debe pertenecer a `producto_id`. Precio de lista = presentación o producto. Fusión de líneas: mismo `producto_id` + mismo `id_presentacion` (ambos null cuenta como iguales) + mismo precio + no enviado + mismas notas.
- Stock: `cantidadSolicitada` en unidades base = `cantidad × (factor_conversion o 1)`.
- Comanda: sigue mirando `producto.genera_comanda` y `producto.destino_comanda`.

## UI

### Encabezado POS — comensales

Donde hoy es texto `N comensales`: número + botones `−` / `+`. Rango 1–99. Un tope guarda de inmediato. Mientras el request está in-flight, deshabilitar stepper. Si el API falla: revertir al valor anterior y `alertService.error`. Sin modal.

### Flujo solicitar cuenta — nombres

Debajo de “Número de personas”, N inputs (placeholder `Persona 1`…). Al cambiar N, se agregan o quitan filas; los nombres ya escritos se conservan en el índice que sigue existiendo.

En división por ítems, las pestañas muestran el nombre resuelto (input o fallback). El mini-prompt de partir unidades: `Unidades para {nombre}`.

Al confirmar, el payload incluye `dividir.nombres`.

### Catálogo y orden — presentaciones

Misma grilla. Ficha de presentación: foto del producto, `nombre` aplanado, precio de presentación, badge (mismo criterio visual que facturación: badge si `id_presentacion`).

`@for` track: `id + ':' + (id_presentacion || 0)` — el `id` de producto se repite.

Sheet agregar: título = `nombre` de la ficha (ya aplanado). Emitir `{ producto_id, id_presentacion, cantidad, notas }`.

Lista de orden: mismo nombre a mostrar (relación `presentacion` + `producto`).

Si el módulo está apagado, el catálogo no cambia de comportamiento.

### Lista de pre-cuentas

`{numero_pre_cuenta} · {nombre_pagador} — {total}` cuando hay `nombre_pagador`. Si no hay (cuenta completa), se omite el nombre.

## Tickets y facturación

- Ticket pre-cuenta: línea `Para: {nombre_pagador}` cuando el campo tiene valor.
- Ticket comanda: descripción de línea = nombre a mostrar (presentación o producto).
- Agrupar líneas para ticket/factura: clave `producto_id|id_presentacion|precio|notas` (no mezclar presentaciones del mismo producto).
- `prepararFactura.detalles[]`: agregar `id_presentacion` (nullable) y `descripcion` = nombre a mostrar.

## Errores

| Caso | Comportamiento |
|------|----------------|
| Update comensales en sesión cerrada | 422 |
| Fallo de red al cambiar comensales | Revertir UI; alerta |
| `id_presentacion` de otro producto / otra empresa | 422 |
| Stock insuficiente (en unidades base) | 422, mensaje actual con cantidades convertidas |
| División por ítems incompleta | Igual que hoy: no confirmar |
| `nombres` mal dimensionado | Rellenar / recortar; no 422 |

## Prueba mínima

Un check runnable por regla (PHPUnit feature existente o extensión de `PosMenuTest` / tests de pre-cuenta; un helper de mapeo si el test no toca BD):

1. `mapProductos` con módulo off: una ficha por producto.
2. `mapProductos` con presentaciones: ficha base + una por presentación; `genera_comanda` igual al producto.
3. Store ítem con `id_presentacion` válido: guarda FK y `precio_unitario` = `precio_venta`.
4. Dividir con `nombres: ["Ana", ""]`: pre-cuentas `Ana` y `Persona 2`.
5. PUT `num_comensales` en sesión abierta: actualiza; en cerrada: 422.
6. Producto sin `genera_comanda`: su presentación no genera línea de comanda.

## Arquitectura de archivos (orientativa)

| Pieza | Cambio |
|-------|--------|
| `SesionMesaController` | Bloquear update si sesión no operable |
| `PreCuentaController` | Validar/normalizar `nombres`; persistir `nombre_pagador` |
| `PosMenuController` | Aplanar presentaciones si módulo activo |
| `OrdenDetalle` | Relación `presentacion`; fillable `id_presentacion` |
| `OrdenDetalleController` | `id_presentacion`, precio, stock con factor, fusión |
| `RestauranteTicketHtmlService` | Nombre en ticket; agrupación con presentación |
| `pre-cuenta-ticket.blade.php` | `Para: …` |
| `comanda-ticket.blade.php` | Nombre a mostrar |
| `RestauranteService` | `actualizarSesion`; `agregarItem` acepta `id_presentacion` |
| `cuenta-mesa` | Stepper comensales; lista pre-cuentas; payload agregar |
| `pos-flujo-cuenta` | Inputs de nombres |
| `pos-catalogo` | Track por presentación; badge; mostrar `nombre` aplanado |
| `pos-sheet-agregar` | Emitir `id_presentacion` |
| `producto-presentaciones` | Sin switch de comanda (se hereda) |

No hay servicio Angular nuevo. No hay flag nuevo de módulo: se usa `isModuloPresentaciones()`.

## Fuera de alcance (explícito)

- Pedir nombres al abrir la mesa o en el mapa.
- Switch `genera_comanda` en el formulario de presentación.
- Limitar comensales a `mesa.capacidad`.
- Tratar presentaciones como productos distintos (sin FK).
- Cambiar el motor de facturación más allá de pasar `id_presentacion` y descripción.
- Offline / sync multi-dispositivo.

## Fases sugeridas

1. Comensales: PUT + stepper POS.
2. Nombres al dividir: columna, payload, UI, ticket y lista.
3. Presentaciones: migración línea, menú POS, store ítem, comanda/factura/ticket.
4. Pruebas mínimas de las tres reglas.
