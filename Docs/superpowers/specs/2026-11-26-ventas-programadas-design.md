# Diseño: Ventas / Facturas Programadas

**Fecha:** 2026-11-26  
**Estado:** Aprobado  
**Tipo:** Módulo nuevo en Ventas (independiente de recurrentes)

---

## 1. Problema

El cliente necesita programar facturas/ventas a clientes con frecuencia, hora, estado, correo y trazabilidad de ejecuciones. Las **ventas recurrentes** actuales (plantilla sobre `ventas` + cron fijo) no ofrecen esa UX ni un historial de ejecuciones dedicado. No se debe modificar el comportamiento de recurrentes.

## 2. Objetivo

Módulo **Facturas / Ventas Programadas** bajo Ventas:

- Plantillas propias (cabecera + detalles), sin correlativo al guardar.
- Al guardar: solo queda programada; no genera venta.
- Ejecución automática según frecuencia (mensual/anual), día y hora.
- Por programada: activo, emitir automático vs borrador, enviar email (si emite), máximo de ejecuciones opcional, conteo de ejecuciones.
- Listado como mockup del cliente; crear/editar/ver + historial de ejecuciones.
- Cada venta generada guarda referencia a la programada.

## 3. Fuera de alcance (MVP)

- No tocar ventas recurrentes ni su cron/preferencias.
- Frecuencias distintas de mensual/anual (semanal, quincenal, una vez).
- Vigencia futura de precios / historial de tarifas (editar plantilla aplica en la **próxima** ejecución).
- Reutilizar pantallas de facturación v1/v2 para crear la programada.
- Reintento automático agresivo en cada tick tras error (ver §6).
- Correo interno a la empresa / resumen diario.
- Multi-sucursal avanzada más allá de lo que ya exija el motor de ventas al emitir.
- Módulo admin SaaS “Suscripciones” SmartPyme.

## 4. Decisiones

| Tema | Decisión |
|------|----------|
| Relación con recurrentes | Módulo nuevo; recurrentes intactas |
| Persistencia | Tablas propias: `ventas_programadas`, `detalles_venta_programada`, `ventas_programadas_ejecuciones` |
| Formulario | Propio, simple, sin correlativo |
| Frecuencia MVP | `mensual` \| `anual` (no “una vez”) |
| Al guardar | No ejecuta; calcula `proxima_ejecucion` |
| Parada | Desactivar **o** alcanzar `max_ejecuciones` (opcional) |
| Emisión | Toggle `emitir_automatico`: emitida completa vs borrador |
| Email | Al cliente **solo** si se emite y `enviar_email=true` |
| Precio | Sin vigencia programada en MVP; editar plantilla → próximo ciclo |
| Runtime | Scheduler cada **15 minutos** (honra hora exacta del mockup) |
| Día 31 en meses cortos | Usar **último día del mes** |
| Tras error | Registrar ejecución `error`; avanzar `proxima_ejecucion` al siguiente ciclo (evitar loop cada 15 min) |
| Idempotencia | Clave de período por programada (p. ej. `Y-m` mensual / `Y` anual) única en ejecuciones ok |
| Referencia en venta | Columna nullable `id_venta_programada` en `ventas` |
| Permisos | Mismos que admin de ventas / listado recurrentes |
| TZ | Zona horaria de la app/empresa ya usada en scheduler (consistente con otros crons) |
| Nombre en UI | **Facturas Programadas** (copy del mockup); rutas/código pueden usar `ventas-programadas` |

---

## 5. Modelo de datos

### 5.1 `ventas_programadas`

Campos mínimos:

- Identidad: `id`, `id_empresa`, `nombre`
- Comercial: `id_cliente`, `id_documento`, totales/cabecera espejo de venta necesarios para clonar (sin correlativo / sin estado de emisión)
- Programación: `frecuencia` (`mensual`\|`anual`), `dia_ejecucion` (1–31), `hora_ejecucion` (time), `proxima_ejecucion` (datetime)
- Flags: `activo`, `emitir_automatico`, `enviar_email`
- Límites: `max_ejecuciones` (nullable), `ejecuciones_count` (default 0)
- Auditoría: `created_at`, `updated_at`, usuario creador si el patrón del módulo lo usa

### 5.2 `detalles_venta_programada`

Espejo de líneas de detalle de venta (producto/servicio, cantidad, precios, impuestos, descuentos, etc. según lo mínimo para que el servicio de facturación pueda crear la venta). FK a `ventas_programadas`.

### 5.3 `ventas_programadas_ejecuciones`

- `id_venta_programada`, `id_venta` (nullable si falló antes de persistir)
- `periodo` (string, único con programada para ejecuciones exitosas)
- `programada_para`, `ejecutada_en`
- `estado` (`ok`\|`error`\|`omitida`)
- `modo` (`emitida`\|`borrador`)
- `mensaje_error` (nullable)
- timestamps

### 5.4 `ventas`

- Agregar `id_venta_programada` nullable + índice/FK.

---

## 6. Runtime / flujo de ejecución

1. Command Artisan (p. ej. `ventas:ejecutar-programadas`) registrado en `Kernel` **cada 15 minutos**.
2. Selecciona programadas con `activo=1`, `proxima_ejecucion <= now`, y (`max_ejecuciones` null o `ejecuciones_count < max_ejecuciones`).
3. Por cada una, con lock/transacción para evitar doble corrida:
   1. Si ya existe ejecución `ok` para el `periodo` actual → omitir / avanzar próxima.
   2. Clonar cabecera + detalles → crear `venta` con `id_venta_programada`.
   3. Si `emitir_automatico`: emitir (correlativo, stock, FE si aplica vía el camino existente más seguro/reutilizable). Si `enviar_email`, correo al cliente.
   4. Si no: dejar borrador; **sin** correo.
   5. Insertar historial (`ok` o `error`).
   6. Si `ok`: `ejecuciones_count++`; recalcular `proxima_ejecucion` (+1 mes o +1 año, misma hora; día inválido → último día del mes). Si alcanza máximo → `activo=0`.
   7. Si `error`: guardar mensaje; **igual** avanzar `proxima_ejecucion` al siguiente ciclo.
4. Recurrentes: sin cambios.

**Nota profesional vs “2 veces al día”:** con hora libre en UI (08:00, etc.), 2×/día retrasa la emisión hasta el siguiente slot. 15 minutos alinea expectativa del mockup con carga baja (solo filas vencidas).

---

## 7. API (borrador)

Prefijo bajo API de ventas (auth empresa):

| Método | Ruta | Uso |
|--------|------|-----|
| GET | `/ventas-programadas` | Listado + búsqueda |
| POST | `/ventas-programadas` | Crear plantilla |
| GET | `/ventas-programadas/{id}` | Detalle + líneas |
| PUT/PATCH | `/ventas-programadas/{id}` | Editar |
| POST | `/ventas-programadas/{id}/activar` / `desactivar` | Toggle activo (o PATCH flag) |
| GET | `/ventas-programadas/{id}/ejecuciones` | Historial |

Validaciones: cliente y documento de la empresa; al menos una línea; día 1–31; hora válida; `max_ejecuciones` null o ≥ 1.

---

## 8. Frontend

- Ruta: `/ventas/programadas` (pestaña/enlace **después** de recurrentes en navegación de Ventas).
- Listado: columnas Nombre, Cliente, Tipo documento, Frecuencia, Próxima ejecución, Activo, Enviar email, Ejecuciones, acciones (editar / ver; activar-desactivar en menú).
- Crear/Editar: formulario propio (nombre, cliente, documento, líneas, programación, flags).
- Ver: plantilla + tabla de historial de ejecuciones (link a venta generada si existe).
- Estilos: patrones existentes del módulo Ventas (tablas, botones, modales); acento naranja del producto si ya se usa en listados.

---

## 9. Errores y consistencia

- Fallos de FE/stock/validación: historial `error`, sin incrementar `ejecuciones_count` de éxito.
- Ventas ya generadas no se recalculan al editar la plantilla (trazabilidad histórica).
- Editar plantilla solo afecta ejecuciones futuras.
- No compartir estado con `frecuencia_recurrencia` / `id_venta_plantilla` de recurrentes.

---

## 10. Pruebas (mínimo)

- Cálculo de `proxima_ejecucion` (mensual, anual, día 31 → feb).
- Guardar programada **no** crea venta.
- Ejecución con `emitir_automatico=false` → borrador, sin email.
- Ejecución con emit + email → venta emitida + intento de correo (mockeable).
- `max_ejecuciones` desactiva al alcanzar.
- Idempotencia: segunda corrida del mismo período no duplica venta ok.
- `id_venta_programada` poblado en la venta hija.

---

## 11. Criterios de aceptación (mapeo cliente)

| Criterio | Cómo se cubre |
|----------|----------------|
| Config por cliente | `id_cliente` en la programada |
| Tipo de factura | `id_documento` |
| Precio | Líneas/totales de la plantilla |
| Cambiar precio | Editar plantilla → próxima ejecución |
| Vigencia de precio | **Fuera de MVP** (explícito) |
| Fecha/hora emisión | `dia_ejecucion` + `hora_ejecucion` + `proxima_ejecucion` |
| Envío correo | Flag + solo si emite |
| No afectar otras | Filas independientes por programada |
| Histórico intacto | Ventas hijas inmutables respecto a ediciones posteriores |
| Listado tipo mockup | UI §8 |
| Historial ejecuciones | Tabla `ventas_programadas_ejecuciones` |

---

## 12. Riesgos / open points

1. **Profundidad de emisión automática:** reutilizar el camino de clon/emisión de `GenerarVentasRecurrentesService` + `FacturacionService` (FE SV donde aplique); sin FE, venta emitida interna sin DTE.
2. **Campos exactos del espejo detalle:** al implementar, copiar el subset mínimo que usa el clon de recurrentes; no inventar campos fiscales nuevos.

---

## 13. Self-review

- Sin TBD vagos: open points listados y acotados.
- Recurrentes explícitamente fuera; no contradice “dejar recurrentes como está”.
- Alcance MVP acotado a un plan de implementación.
- Precio con vigencia: excluido de forma explícita (no ambiguo).
