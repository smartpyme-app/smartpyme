# Design: Restaurante POS v2 — pantalla de mesa (cuenta)

**Fecha:** 2026-08-04  
**Estado:** Aprobado  
**Alcance:** Rediseño de la pantalla de mesa abierta (`restaurante/cuenta/:id`): catálogo táctil, layout POS, división/precuenta/facturación.  
**Fuera de alcance:** Mapa de mesas, cocina/barra UI, modificadores estructurados, flag “visible en restaurante”, offline multi-dispositivo.

## Problema

Hoy, al abrir una mesa, el mesero agrega ítems con `app-buscador-productos` (texto). En servicio real (tablet/teléfono/PC) es lento e inadecuado frente a un POS de restaurante por categorías.

## Objetivos

1. Toma de pedido táctil: categorías → subcategorías → productos/servicios.
2. Layout tipo POS (tablet-first, usable en PC y móvil).
3. Al agregar: cantidad + nota libre (sin modificadores).
4. División profesional: equitativa + asignación táctil por ítems (con partir línea) → precuenta → facturación.
5. Reutilizar APIs y reglas de negocio actuales (comanda, permisos, precuentas, facturar).

## Decisiones

| Decisión | Elección |
|----------|----------|
| Alcance UI | Enfoque B: rediseño de pantalla de mesa (no módulo entero) |
| Cobro | Precuenta y facturación desde la misma pantalla |
| Layout | A: catálogo (~60%) + orden fija a la derecha; móvil apilado |
| Arquitectura | Modular bajo la misma ruta (shell + piezas) |
| Catálogo | Único: productos + servicios activos juntos |
| Visibilidad | Todos activos; **no** filtrar por `genera_comanda` |
| `genera_comanda` | Solo afecta envío a cocina/barra |
| Navegación menú | Cat → subcat → ítems; si no hay subcats, ítems directo |
| Imágenes | Foto si existe; placeholder si no |
| Agregar ítem | Sheet: cantidad (default 1) + nota opcional |
| Modificadores | Fuera de v2 (fase posterior) |
| División | Completo + equitativa + por ítems táctil + partir cantidad de línea |
| Backend cobro | Reutilizar `solicitarCuenta` / dividir / facturar existentes |

## Layout

### Tablet / desktop

```
┌─────────────────────────────────┬──────────────────┐
│ Header: atrás · Mesa · estado   │                  │
├─────────────────────────────────┤  Orden           │
│ Breadcrumb + buscador secundario│  ítems           │
│ Grilla categorías / subcats /   │  totales         │
│ productos                       │  Enviar          │
│                                 │  Solicitar cuenta│
└─────────────────────────────────┴──────────────────┘
```

### Móvil (<768px)

Catálogo arriba; orden abajo o sheet “Ver orden”. Misma lógica, distinta densidad.

## Catálogo táctil

1. Nivel raíz: categorías (cuadros grandes).
2. Tap categoría:
   - Con subcategorías → grilla de subcategorías.
   - Sin subcategorías → grilla de productos/servicios.
3. Tap subcategoría → productos/servicios.
4. Breadcrumb / atrás: `Categorías › Bebidas › Gaseosas`.
5. Buscador secundario: atajo por nombre/código; no reemplaza la navegación.
6. Tile de producto: imagen o placeholder, nombre, precio.

### Sheet agregar

- Cantidad con +/- (mínimo según reglas actuales del API).
- Nota libre opcional.
- Confirmar → `agregarItem`; cancelar → no agrega.

## Panel de orden

- Cabecera: mesa, estado, comensales, tiempo, mesero.
- Lista: nombre, qty, precio, subtotal, nota, badges Cocina/Barra si enviado.
- Editar qty/nota y eliminar/anular: mismas reglas/permisos que hoy.
- Totales: subtotal, IVA, propina, total (configuración empresa).
- Acciones primarias: **Enviar** (ítems pendientes con `genera_comanda`), **Solicitar cuenta**.
- Acciones secundarias existentes (trasladar, reactivar, etc.) en UI más compacta.

### Estados de sesión

- `abierta`: agregar, enviar, solicitar cuenta.
- `pre_cuenta`: comportamiento operativo actual; listar precuentas; facturar pendientes.

## División → precuenta → facturación

### Paso 1 — Modo

- Cobro completo (una precuenta), o
- Dividir: **equitativa** (N personas) o **por ítems**.

### Paso 2 — Por ítems (táctil)

- Elegir N (2–20, mismo rango práctico actual).
- Pestañas Persona 1…N; persona activa resaltada.
- Tap ítem → asigna a persona activa.
- Si cantidad > 1 o se parte línea: sheet “unidades para esta persona” (fracciones permitidas como hoy).
- Estados visuales: sin asignar / parcial / completo.
- Confirmar solo si la suma por línea = cantidad del ítem (validación actual del backend).

### Paso 3 — Precuentas

- Generar con payload actual de `solicitarCuenta` (+ `dividir` cuando aplique).
- Lista: número, total, estado (pendiente / facturada).
- Acciones: ver/imprimir precuenta; **Facturar**.

### Paso 4 — Facturación

- Desde precuenta pendiente → flujo de facturación existente.
- Cierre de mesa según reglas actuales (ocupada hasta facturar lo pendiente).

## Arquitectura de componentes

Ruta sin cambio: `restaurante/cuenta/:id`.

| Pieza | Responsabilidad |
|-------|-----------------|
| Shell `cuenta-mesa` | Carga sesión, layout A, orquestación |
| Catálogo táctil | Navegación menú + buscador secundario |
| Sheet agregar | Cantidad + nota → `agregarItem` |
| Panel orden | Lista, totales, editar/eliminar, Enviar, Solicitar cuenta |
| Flujo cuenta | Completo / equitativa / por ítems → precuentas → facturar |

Nombres de archivos concretos se fijan en el plan de implementación; no inventar capas de servicios más allá de `RestauranteService` / APIs de inventario existentes.

## Datos / API

- **Reutilizar:** sesión mesa, agregar/actualizar/eliminar ítem, enviar comanda, solicitar cuenta, dividir, facturar precuenta.
- **Catálogo:** categorías, subcategorías y productos/servicios activos (endpoints inventario actuales). Si la UX lo exige por performance, un endpoint ligero de “menú POS” (árbol o listados por nivel con id, nombre, precio, imagen, `genera_comanda`) — opcional, no bloqueante del diseño.
- **Sin** nuevo flag de visibilidad ni filtro por `genera_comanda` en el listado del menú.

## Errores

- Fallo de red/API al agregar, enviar o solicitar cuenta: alerta existente; no asumir éxito; preservar estado del sheet o de la asignación en curso cuando sea razonable.
- División incompleta: bloquear confirmación con mensaje claro.
- Validaciones de stock/negocio del backend: mostrar error; no marcar el ítem como agregado.

## Prueba mínima

Un check runnable (assert/demo o spec pequeño):

1. Categoría sin subcategorías expone productos directo.
2. Asignación por ítems: sumas por línea deben igualar la cantidad (caso completo y caso partir línea).

## Fuera de alcance (explícito)

- Rediseño del mapa de mesas o pantalla cocina.
- Modificadores / extras estructurados.
- Flag “visible en restaurante”.
- Modo offline / sync multi-dispositivo.
- Cambiar reglas fiscales o el motor de facturación (solo el acceso UX desde precuenta).

## Fases sugeridas de implementación

1. Shell layout A + panel orden (sin cambiar aún el buscador).
2. Catálogo táctil + sheet agregar (reemplaza buscador como camino principal).
3. Flujo cuenta táctil (equitativa + por ítems + partir línea) → precuenta → facturar.
4. Pulido móvil + prueba mínima.
