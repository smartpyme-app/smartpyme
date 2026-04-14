# Plan de trabajo — Módulo Pedidos (Smartpyme)

**Versión:** 1.1  
**Contexto:** Módulo ya enlazado en el menú (`/pedidos`) bajo la funcionalidad **Restaurantes y pedidos** (`modulo-restaurante`). Integración **manual** con plataformas externas (sin API de Pedidos Ya u otras por ahora). Flujo de negocio inspirado en el documento del cliente (Spoties): pedidos de canal con precios propios, comprobantes operativos y liquidación por periodos.

---

## 1. Principios acordados

| Tema | Decisión |
|------|----------|
| Modelo operativo | **Un pedido = un documento** (borrador), no una “cuenta semanal” fusionada a mano. Evita confusión y facilita trazabilidad y DTE. |
| Pantalla de ítems | Misma experiencia que **venta / cotización** (líneas, productos, precios, notas). |
| Conversión a venta | Botón **Procesar** (por rango de fechas y/o selección) que convierte pedidos elegibles en **ventas**; la **facturación electrónica** sigue el flujo actual (venta → factura). |
| Paralelo con cotización | **Sí**, a nivel de flujo: documento previo → venta → facturación. El nombre en UI puede ser **Pedido** o **Pedido de canal** para no chocar con cotizaciones a clientes. |
| Integración externa | **No** en esta primera ola (sin API). IDs y precios se cargan **manualmente** (observaciones / campo dedicado). |
| Facturación y DTE | **Sin flujo nuevo:** al **Procesar**, se crea una **venta normal** en base de datos, igual que el resto de ventas; aparece en el **listado de ventas** y desde ahí se sigue el **proceso habitual de facturación** del sistema hasta generar el **DTE**. No se duplica lógica fiscal en el módulo Pedidos. |
| Estado **Facturado** en pedidos | El pedido **no** se marca como facturado solo por existir la venta. Se marca **facturado** cuando la **venta vinculada** ha completado el flujo normal y tiene **factura/DTE emitido**. Estados acordados: **borrador** → **pendiente de facturar** (tras Procesar y creación de venta) → **facturado**; **anulado** si aplica. La sincronización pedido ↔ venta se implementará leyendo el estado de la venta o al confirmar la factura. |

**Decisión pendiente de cerrar con negocio/contador (antes o durante Fase 4):**  
¿Al procesar, se genera **una venta por pedido** (recomendado para auditoría 1:1) o se permite **una venta consolidada por periodo**? El plan contempla primero **una venta por pedido**; consolidar sería una variante.

---

## 2. Fases de implementación

### Fase 0 — Alcance y preparación

**Objetivo:** Dejar fijadas reglas y nombres para no rediseñar a mitad de camino.

**Entregables:**
- Estados del pedido **cerrados:** **borrador** → **pendiente de facturar** → **facturado**; **anulado**. **Facturado** solo cuando la venta asociada tenga DTE por el flujo estándar.
- Confirmación del criterio de facturación al procesar (una venta por pedido vs consolidado).
- Lista de **canales** iniciales (ej. Pedidos Ya, otro) como catálogo simple o texto libre.

**Pruebas:** Revisión interna del documento; no requiere código.

---

### Fase 1 — Base de datos y API (backend)

**Objetivo:** Persistencia y API REST protegida con el mismo middleware que restaurante (`modulo-restaurante`).

**Incluye migraciones nuevas:** hoy no existen tablas de pedidos en el proyecto; hay que crearlas (cabecera + detalle, FKs, índices). No basta con reutilizar tablas de ventas sin crear el encabezado de pedido.

**Entregables (orientativos):**
- Tablas: cabecera de **pedido** (empresa, sucursal, fechas, canal, cliente si aplica, estado, totales, referencia externa opcional, observaciones).
- Tabla de **detalle** (producto, cantidad, precio, descuentos, notas por línea — ej. ID del pedido en plataforma).
- Endpoints: CRUD pedidos, listados con filtros (fechas, estado, canal).
- Validaciones básicas (empresa, productos existentes, transiciones de estado).

**Pruebas al cerrar fase:**
- Crear/editar/listar pedidos vía API (Postman o similar).
- Sin UI aún; comprobar 403 si la funcionalidad no está activa.

---

### Fase 2 — Interfaz: listado y edición de pedidos

**Objetivo:** Sustituir el placeholder de `/pedidos` por pantallas reales.

**Entregables:**
- Listado de pedidos con filtros (fechas, estado, canal).
- Formulario de **nuevo pedido / edición**: cabecera + **detalle de líneas** alineado con patrones de **venta/cotización** (reutilizar componentes/servicios donde existan).
- Guardado en borrador; cálculo de totales coherente con el resto del sistema.

**Pruebas al cerrar fase:**
- Flujo completo en UI: crear pedido, agregar líneas, guardar, reabrir y editar.
- Verificar que solo usuarios con funcionalidad activa acceden (ruta + menú ya condicionados).

---

### Fase 3 — Comprobantes operativos (impresión)

**Objetivo:** Apoyar el proceso del cliente: ticket para el pedido y ticket de respaldo para firma del repartidor.

**Entregables:**
- Acción **Imprimir** (una o dos impresiones según diseño): formato térmico o PDF según estándar del proyecto.
- Textos mínimos: datos del pedido, líneas, total, ID externo si existe.

**Pruebas al cerrar fase:**
- Impresión desde un pedido de prueba; revisión en dispositivo real o simulación.

---

### Fase 4 — Procesar pedidos → ventas

**Objetivo:** Botón **Procesar** por **rango de fechas** (y/o selección múltiple) que genere **ventas** a partir de pedidos en estado elegible.

**Entregables:**
- Regla: solo pedidos **no procesados** (y validaciones de negocio).
- Creación de **venta(s)** con líneas equivalentes; vínculo pedido ↔ `id_venta` (o equivalente) para auditoría.
- Tras crear la venta: el pedido pasa a estado **pendiente de facturar** (no “facturado” aún).

**Pruebas al cerrar fase:**
- Varios pedidos en rango → procesar → ver **ventas nuevas** en el listado de ventas, con enlace al pedido origen si aplica.

---

### Fase 5 — Enlace con facturación electrónica (flujo existente)

**Objetivo:** La facturación y el DTE usan **exactamente** el flujo que ya tiene el sistema sobre **ventas**. El módulo Pedidos solo facilita **ir a la venta** (enlace) y mantener coherencia de estados.

**Entregables:**
- Desde pedido procesado: acción **Ir a venta** / **Facturar** que abre la venta en la **misma pantalla de facturación** que ya conocen (sin pantalla fiscal nueva en Pedidos).
- Al **emitirse la factura/DTE** por el flujo normal, el pedido asociado pasa a **facturado** y el listado de pedidos lo muestra así.

**Pruebas al cerrar fase:**
- Procesar pedido → localizar venta en listado → facturar como siempre → verificar pedido en **facturado**.

---

### Fase 6 — Cierre de periodo y ajustes (descuento global)

**Objetivo:** Soportar el caso “pago los viernes por rango de fechas” con **descuento % sobre el total del periodo** y conciliación con el estado de cuenta de la plataforma (referencia manual).

**Entregables (mínimo viable):**
- Pantalla o flujo de **cierre de periodo**: seleccionar rango, ver total, aplicar **% de descuento** sobre ventas/pedidos del periodo (definir si el descuento vive en nota de crédito, línea de ajuste o documento único — acordar con contador).
- Campos de **notas de conciliación** (opcional).

**Pruebas al cerrar fase:**
- Simular periodo con varias ventas ya generadas; aplicar descuento y verificar reflejo en documentos o reporte.

*Nota:* Si la contabilidad prefiere solo **notas manuales** fuera del módulo en la primera versión, esta fase puede acotarse a **reporte exportable** + descuento documentado en observaciones.

---

### Fase 7 — Pulido y operación

**Objetivo:** Dejar el módulo usable en producción.

**Entregables:**
- Permisos por rol si el proyecto los usa para ventas.
- Mensajes de error claros; estados bloqueados (no editar pedido en **pendiente de facturar** o **facturado**, salvo reglas explícitas).
- Ajustes de rendimiento en listados si hay muchos pedidos.

**Pruebas al cerrar fase:**
- Checklist con el cliente (Spoties): flujo pedido → tickets → procesar → facturar → cierre de periodo (o subset acordado).

---

## 3. Dependencias

- Funcionalidad **Restaurantes y pedidos** activa y preferencia de menú en **Pedidos** (o **Ambos**) en configuración de empresa.
- Módulos existentes: **productos**, **clientes**, **ventas**, **facturación**.

---

## 4. Fuera de alcance (por ahora)

- Integración API con Pedidos Ya, Rappi u otras.
- Sincronización automática de precios desde plataformas.

---

## 5. Estado del plan

| Fase | Estado |
|------|--------|
| Fase 0 | Cerrada (estados: borrador, pendiente de facturar, facturado, anulado) |
| Fase 1 | Implementada |
| Fase 2 | **Implementada — pendiente de tu revisión y pruebas** |
| Fases 3–7 | Pendientes |

## 6. Por dónde empezar ahora

- **Siguiente paso tras aprobar Fase 2:** **Fase 3** (impresión tickets).
- Pendiente de negocio (opcional antes de Fase 4): una venta por pedido vs consolidado; canales iniciales (campo `canal` es texto libre).

### Fase 2 — UI implementada (referencia)

- Rutas: `/pedidos` (listado + filtros), `/pedidos/nuevo`, `/pedidos/editar/:id`.
- Servicio: `RestauranteService` (`getPedidos`, `getPedido`, `crearPedido`, `actualizarPedido`, `eliminarPedido`).
- Listado: filtros estado, fechas, canal; editar/eliminar solo en **borrador**.
- Formulario: cabecera + líneas con `app-buscador-productos`, precio/cantidad/descuento/notas por línea; clientes desde `clientes/list`.

### Fase 1 — API implementada (referencia)

- **Prefijo:** `GET/POST/PUT/DELETE` bajo `api/restaurante/pedidos` (middleware `modulo-restaurante` + JWT).
- **Tablas:** `restaurante_pedidos`, `restaurante_pedido_detalles`.
- **Listado:** query opcionales `estado`, `fecha_desde`, `fecha_hasta`, `canal`, `id_sucursal`.
- **Crear:** `POST` JSON con `fecha`, `detalles[]` (`producto_id`, `cantidad`, `precio`, opcional `descuento`, `notas`), opcional `canal`, `referencia_externa`, `cliente_id`, `observaciones`.
- **Editar / eliminar:** solo si `estado == borrador`.
- **Migraciones:** `2026_03_24_100000_create_restaurante_pedidos_table.php`, `2026_03_24_100001_create_restaurante_pedido_detalles_table.php` (ejecutar en cada entorno: `php artisan migrate --path=...` si el `migrate` global falla por migraciones viejas).
