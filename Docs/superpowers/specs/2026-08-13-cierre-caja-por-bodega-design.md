# Diseño: Cierre de caja por bodega

**Fecha:** 2026-08-13  
**Estado:** Aprobado (pendiente implementación)  
**Tipo:** Feature de reporte  
**Enfoque:** Extender el reporte de Cierre de caja existente (opción A): mismo endpoint y `Indicador`, filtro exclusivo por sucursal **o** bodega

---

## 1. Contexto y problema

El reporte **Cierre de caja** (`/cierre-de-caja`, `CorteComponent`) calcula totales del día filtrando por fecha, usuario, sucursal y canal. No permite cerrar ni consultar por bodega.

Las ventas y devoluciones ya tienen `id_bodega`. Los gastos (`egresos`) y los abonos como registro propio no: los abonos se ligan a una venta; los gastos solo a sucursal.

La operación necesita ambos criterios, sin cambiar el cierre por sucursal que ya usan.

---

## 2. Objetivos

1. Agregar un selector de **criterio de cierre**: Por sucursal / Por bodega.
2. Por bodega: elegir una bodega y calcular solo operaciones de esa bodega.
3. Por sucursal: comportamiento actual (incl. “Todas las sucursales” para admin).
4. Totales coherentes con el criterio elegido (pantalla y PDF).
5. No alterar cierres ni consultas existentes por sucursal.

---

## 3. Decisiones acordadas

| Tema | Decisión |
|------|----------|
| Enfoque técnico | Extender `Indicador` + `GET /api/corte` (opción A). No endpoint nuevo. |
| UI del criterio | Selector exclusivo. Por sucursal muestra el select de sucursal; por bodega lo **reemplaza** por select de bodega. |
| Default | **Por sucursal**. |
| Todas las bodegas | **No.** Hay que elegir una bodega concreta. |
| Gastos en cierre por bodega | **No incluir.** Totales y cantidades de gastos = 0. El card permanece visible. |
| Abonos por bodega | Incluir, filtrando por `venta.id_bodega`. |
| Roles Ventas / Ventas Limitado | Por sucursal: su `id_sucursal`. Por bodega: su `id_bodega`. Sin “todas”. |
| Admin / Supervisor | Lista completa de sucursales o de bodegas activas, según criterio. |
| PDF | Respeta el mismo criterio. `id_bodega` por query param. Canal no se agrega al PDF. |
| Dashboard (`/api/dash`) | Fuera de alcance. No recibe `id_bodega`. |
| Tabla `caja_cortes` / arqueos persistidos | Fuera de alcance. No se migran ni se reescriben. |
| Refactor grande de `Indicador` | No. Solo agregar `id_bodega` junto al `when(id_sucursal)` ya existente en las queries de corte. |

---

## 4. Arquitectura

### 4.1 Pantalla (`Frontend/src/app/views/reportes/corte/`)

Barra de filtros, de izquierda a derecha en el grupo de selects:

1. Fecha (sin cambio)
2. Usuario (sin cambio)
3. **Criterio** (nuevo): `sucursal` | `bodega`
4. Sucursal **o** bodega (según criterio)
5. Canal (sin cambio)

`filtros.criterio` es solo de UI. Al API se envía **uno** de los dos ids:

| Criterio | Select visible | Payload |
|----------|----------------|---------|
| `sucursal` | Sucursales (admin: opción vacía “Todas las sucursales”) | `id_sucursal` como hoy; `id_bodega` vacío / omitido |
| `bodega` | Bodegas activas, etiqueta `nombre (nombre_sucursal)` | `id_bodega` obligatorio; `id_sucursal` vacío |

Al cambiar de criterio:

- A sucursal: `id_bodega` se limpia. Admin: `id_sucursal = ''`. Ventas: `id_sucursal` del usuario.
- A bodega: `id_sucursal` se limpia. Ventas: `id_bodega` del usuario. Admin: si el usuario tiene `id_bodega`, se preselecciona; si no, queda vacío y **no se llama al API** hasta que elija una (placeholder “Seleccione una bodega”).

Lista de bodegas: `GET bodegas/list` (ya existe; solo activas). Ventas / Ventas Limitado filtran a su `id_bodega`.

Si el usuario es Ventas / Ventas Limitado y no tiene `id_bodega`, al elegir “Por bodega” se muestra aviso y no se consulta.

### 4.2 API de consulta (`DashController::corte`)

Ruta sin cambio: `GET /api/corte`.

Construcción del `Indicador`:

```php
new Indicador([
    'inicio' => $request->fecha,
    'fin' => $request->fecha,
    'id_empresa' => $usuario->id_empresa,
    'id_sucursal' => $request->id_sucursal,
    'id_bodega' => $request->id_bodega,
    'id_usuario' => $request->id_usuario,
    'id_canal' => $request->id_canal,
]);
```

El frontend nunca manda ambos ids a la vez. El backend aplica cada `when` por separado: si uno viene vacío, no filtra por ese campo (igual que sucursal hoy).

**Restricción de rol (solo bodega, para no cambiar el API de sucursal):** si el usuario es `Ventas` o `Ventas Limitado` y el request trae `id_bodega`, el backend fuerza `id_bodega` al del usuario autenticado e ignora otro valor. No se añade un enforcement nuevo sobre `id_sucursal`.

### 4.3 `Indicador`

Agregar `id_bodega` a `$fillable`.

En el constructor, las queries **de ventas** (y las que ya hacen `whereHas('venta')`) reciben:

```php
->when($this->id_bodega, function ($q) {
    $q->where('id_bodega', $this->id_bodega);
})
```

Aplica a:

- `detalles_metodos_de_pago` (dentro del `whereHas('venta')`)
- `ventas`
- `ventas_pagadas`
- `ventas_anuladas`
- `devoluciones_ventas` (`whereHas('venta')`)
- `cxc`
- `abonos` (`whereHas('venta')`)

No aplica a `compras` ni a `getTotalesSalidas` (dashboard).

**Gastos:** `egresos` no tiene `id_bodega`. Si `$this->id_bodega` está seteado, no cargar gastos (`$this->gastos = collect()`). Así `getTotalGastosPagados`, `getCantidadGastosPagados`, `getTotalGastos` y `getCantidadGastos` quedan en 0. No filtrar `egresos` por una columna inexistente.

`id_usuario` e `id_canal` siguen aplicándose igual en ambos criterios.

### 4.4 PDF (`DashController::cortePdf`)

Ruta actual (se mantiene):

`GET /api/corte/documento/{id_usuario?}/{id_sucursal?}/{fecha?}`

Query param nuevo: `id_bodega` (opcional). El token ya va por query.

- Por sucursal: misma URL de hoy; sin `id_bodega`.
- Por bodega: `id_sucursal` en path = `null`; `?id_bodega={id}`.

`cortePdf` lee `id_bodega` del request y lo pasa al `Indicador`. Misma vista `reportes.corte`. Canal sigue sin enviarse al PDF.

### 4.5 Datos y flujo

```
UI criterio sucursal → GET /corte?id_sucursal=&id_usuario=&id_canal=&fecha=
UI criterio bodega   → GET /corte?id_bodega=&id_usuario=&id_canal=&fecha=

Indicador carga colecciones filtradas
DashController::corte arma los mismos campos JSON de hoy
UI pinta cards y tablas sin cambio de layout
```

Ventas y devoluciones se asocian a bodega por `ventas.id_bodega` (las devoluciones, vía la venta, igual que hoy con sucursal). Abonos, igual vía venta.

---

## 5. Manejo de errores / bordes

| Caso | Comportamiento |
|------|----------------|
| Admin en “Por bodega” sin bodega elegida | No se llama a `/api/corte`. Placeholder en el select. |
| Ventas sin `id_bodega` | Aviso; no se consulta por bodega. |
| Request con `id_bodega` de otra bodega (usuario Ventas) | Backend usa la bodega del usuario autenticado. |
| Request con ambos ids (no debería ocurrir) | Se aplicarían los dos `when`. El frontend no lo envía. |
| Cierres históricos por sucursal | Intactos. Este cambio es solo filtro del reporte del día. |

---

## 6. Pruebas

No hay tests actuales de `Indicador` ni de corte. Agregar tests unitarios/feature en `Backend/tests/` que construyan `Indicador` (o el endpoint de corte) con datos de dos bodegas / dos sucursales y verifiquen:

1. Con `id_sucursal` y sin `id_bodega`: mismos totales que el criterio actual (ventas + gastos de esa sucursal).
2. Con `id_bodega` y sin `id_sucursal`: solo ventas, devoluciones y abonos de esa bodega; gastos en 0.
3. Ventas de otra bodega no entran en (2).
4. Una venta de sucursal A / bodega B no aparece en el cierre de sucursal C ni en el de bodega D.

Frontend: el spec de `corte.component` es un stub roto; no es obligatorio arreglarlo. Verificar a mano el selector, el swap sucursal/bodega, roles y que el PDF abra con `id_bodega`.

---

## 7. Fuera de alcance

- Cierre persistido por bodega (`caja_cortes`).
- Filtro por bodega en el dashboard.
- Incluir canal en el PDF.
- Incluir gastos en el cierre por bodega (no hay `id_bodega` en `egresos`).
- Opción “Todas las bodegas”.
- Extraer un helper de ubicación en `Indicador` (opción B).
- Endpoint `/api/corte-bodega` (opción C).

---

## 8. Archivos a tocar

| Archivo | Cambio |
|---------|--------|
| `Frontend/src/app/views/reportes/corte/corte.component.ts` | Criterio, carga de bodegas, payload exclusivo, PDF con query param |
| `Frontend/src/app/views/reportes/corte/corte.component.html` | Select de criterio; sucursal vs bodega |
| `Backend/app/Http/Controllers/Api/DashController.php` | `corte` y `cortePdf` pasan `id_bodega`; restricción de rol en bodega |
| `Backend/app/Models/Indicador.php` | `id_bodega` en fillable; `when` en queries de ventas; gastos vacíos si hay bodega |
| `Backend/tests/Unit/` o `Feature/` | Tests de totales por sucursal vs bodega |
