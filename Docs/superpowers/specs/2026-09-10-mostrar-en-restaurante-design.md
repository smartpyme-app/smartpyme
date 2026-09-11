# Design: Mostrar en restaurante

**Fecha:** 2026-09-10  
**Estado:** Aprobado en conversación; implementación pedida en la misma sesión  
**Alcance:** Checkbox en categoría y producto para ocultar fichas del menú táctil de restaurante, sin tocar POS de facturación ni ventas.

## Problema

El POS de restaurante lista todas las categorías activas y todos los productos activos. Algunos clientes necesitan que ciertas categorías o productos existan en inventario/ventas pero no aparezcan en restaurante.

## Objetivos

1. Switch **Mostrar en restaurante** en categoría y producto, igual de estilo que **Genera comanda** / **Activo**.
2. Default **marcado** (`true`) para no cambiar el comportamiento actual.
3. Desmarcar = no sale en el catálogo de restaurante (listado, contenido, búsqueda).
4. Flags **independientes**: ocultar una categoría no oculta sus productos; un producto marcado puede salir en búsqueda aunque su categoría esté oculta.
5. El filtro aplica **solo** al módulo restaurante. POS facturación, inventario y ventas no filtran por este campo.

## Fuera de alcance

- POS de facturación / `PosMenuVentasController`.
- Excel de importación/exportación.
- Tabla legacy `categoria_subcategorias` (el modal de subcategoría de inventario no alimenta el menú restaurante).
- Rechazar por API que se agregue a una mesa un producto oculto.
- Alterar líneas ya cargadas en una cuenta.

## Contexto actual

- Menú restaurante: `PosMenuController` → `PosMenuCatalog` (categorías raíz, subcategorías como filas de `categorias` con `subcategoria = 1`, productos, búsqueda).
- El mismo `PosMenuCatalog` lo usa el POS de facturación **sin** este filtro.
- `genera_comanda` en producto controla impresión de cocina/barra, no visibilidad.
- Subcategorías del menú restaurante viven en `categorias` (`subcategoria = 1`, `id_cate_padre`). El form de categoría cubre raíz e hija.

## Decisiones

| Decisión | Elección |
|----------|----------|
| Texto del switch | `Mostrar en restaurante` |
| Campo | `mostrar_en_restaurante` boolean, default `true` |
| Tablas | `categorias` y `productos` |
| Independencia | Cada ficha se rige solo por su propio flag |
| Alcance del filtro | Solo queries del menú restaurante |
| Catálogo compartido | `PosMenuCatalog` recibe `bool $soloMostrarEnRestaurante = false`; restaurante pasa `true`; facturación no pasa nada |
| Conteo de subcategorías | En restaurante, `withCount` solo cuenta hijas con el flag en `true` (define modo subcategorías vs productos) |
| Pedidos abiertos | No se tocan |
| Subcategoría legacy | No se agrega el switch |

## Datos

- `categorias.mostrar_en_restaurante` boolean default `true`.
- `productos.mostrar_en_restaurante` boolean default `true`.
- Fillable + cast boolean en `Categoria` y `Producto`.
- Validación opcional boolean en `StoreCategoriaRequest`. Producto entra por `fill($request->all())` como `genera_comanda`.

Filas existentes reciben `true` por el default de la migración.

## Pantallas

- **Inventario → Categorías:** switch junto a Activo. Alta: `mostrar_en_restaurante = true` (como `enable`). Incluir el campo en el `FormData` de imagen.
- **Modal crear categoría** (desde producto): mismo switch; default `true` al abrir.
- **Producto información** y **crear producto:** switch en el bloque Restaurante / cocina, junto a Genera comanda. Alta: inicializar `true` para que el checkbox no quede vacío/falso.

El POS restaurante no cambia de UI: recibe menos ítems.

## API / filtro

`PosMenuController` delega a `PosMenuCatalog` pasando `$soloMostrarEnRestaurante = true` en:

- `queryCategoriasRaiz`
- `querySubcategorias`
- `queryProductos` (y por tanto productos de categoría, de subcategoría y búsqueda)

`PosMenuVentasController` sigue llamando el catálogo sin el flag.

Reglas:

- Categoría desmarcada: no sale en el listado raíz. Sus productos no se recorren por esa categoría.
- Producto desmarcado: no sale en contenido ni en búsqueda.
- Producto marcado + categoría desmarcada: puede salir en **búsqueda**.
- Si todas las subcategorías hijas están ocultas, restaurante trata la categoría como modo productos (conteo filtrado).

## Pruebas

Extender `PosMenuTest` (inspección de query builders, sin BD): las queries de restaurante incluyen `mostrar_en_restaurante = true`. Extender `PosMenuVentasTest`: las queries de facturación **no** incluyen esa columna.

## Archivos

- `Backend/database/migrations/2026_09_10_210000_add_mostrar_en_restaurante_to_categorias_and_productos.php`
- `Backend/app/Models/Inventario/Categorias/Categoria.php`
- `Backend/app/Models/Inventario/Producto.php`
- `Backend/app/Http/Requests/Inventario/Categorias/StoreCategoriaRequest.php`
- `Backend/app/Support/Inventario/PosMenuCatalog.php`
- `Backend/app/Http/Controllers/Api/Restaurante/PosMenuController.php`
- `Backend/tests/Feature/Restaurante/PosMenuTest.php`
- `Backend/tests/Feature/Inventario/PosMenuVentasTest.php`
- `Frontend/src/app/views/inventario/categorias/categorias.component.{html,ts}`
- `Frontend/src/app/shared/modals/crear-categoria/crear-categoria.component.{html,ts}`
- `Frontend/src/app/views/inventario/productos/producto/informacion/producto-informacion.component.html`
- `Frontend/src/app/views/inventario/productos/producto/producto.component.ts`
- `Frontend/src/app/shared/modals/crear-producto/crear-producto.component.{html,ts}`
