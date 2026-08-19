# Recalcular precios de catálogo por tipo de cambio (HN)

**Fecha:** 2026-08-18  
**Ticket:** SP-2127  
**Estado:** Aprobado

## Meta

En Honduras, con **multimoneda**, la empresa puede fijar un TC (sugerido BCH, editable) y **recalcular** precios de venta del catálogo para que el valor en dólares se mantenga al cambiar el lempira.

No convierte líneas al facturar. Documentos ya emitidos no se tocan.

## Alcance

- Solo país **HN** + funcionalidad `multimoneda`.
- Botón en **Productos** y **Servicios**.
- Recalcula `producto.precio`, `precio_sin_iva`, `precio_con_iva` y filas de `producto_precios`.
- No toca costo / costo_promedio.
- Checkboxes: Productos (`Producto`, `Compuesto`) y/o Servicios (`Servicio`). Materias primas no.

## TC en la empresa

`pais_configuracion` módulo moneda es **por país** (BCH del día). El TC editable es **por empresa**, en `empresa_configuracion` módulo `configuraciones`:

- `tipo_cambio_venta`: sugerencia al vender (si está, pisa el BCH en preview).
- `tipo_cambio_catalogo`: TC con el que están los precios. No se pisa al solo guardar el de venta.

Primera vez: al guardar el TC de venta, si no hay catálogo, se copia también como `tipo_cambio_catalogo` **sin** cambiar precios.

Editar TC sin Recalcular: solo `tipo_cambio_venta`.

Recalcular: `precio × (venta / catálogo)`, 2 decimales, luego `tipo_cambio_catalogo = tipo_cambio_venta = TC del modal`.

## API

- `GET /productos/tipo-cambio-precios` — sugerido (BCH o venta empresa), catálogo, flags.
- `PUT /productos/tipo-cambio-precios` — guarda TC venta (y catálogo si es la primera vez).
- `POST /productos/tipo-cambio-precios/recalcular` — `{ exchange_rate, aplicar_productos, aplicar_servicios }`.

Errores 422: TC ≤ 0, sin checkboxes, sin TC catálogo (hay que guardar el inicial), no HN / sin multimoneda → 403. Transacción: nada a medias.

## UI

Modal: TC editable (sugerido BCH), texto de referencia, checkboxes Productos/Servicios, **Guardar tipo de cambio**, **Recalcular precio de servicios y productos**. Recalcular deshabilitado si aún no hay TC catálogo.

## Prueba

Precio 100, catálogo 25, nuevo 26 → 104.00; lista extra en `producto_precios` también.
