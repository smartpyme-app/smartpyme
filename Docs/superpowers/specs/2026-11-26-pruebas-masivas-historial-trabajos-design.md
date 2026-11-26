# Diseño: Historial de trabajos de pruebas masivas SV

**Fecha:** 2026-11-26  
**Estado:** Aprobado / implementado  
**Tipo:** Mejora UX en Admin → Empresa → Facturación electrónica

---

## 1. Problema

Al encolar pruebas masivas MH (SV), el trabajo queda en `trabajos_pendientes` y corre en segundo plano. En la UI solo se ven las cuotas por tipo de DTE (“Completado” / “Generar faltantes”). No hay forma de saber si un lote **pendiente, procesando, completó o falló**, ni **por qué** falló.

## 2. Objetivo

Debajo del bloque de pruebas masivas, mostrar una tabla con los trabajos `pruebas_masivas` de la empresa, con estado y mensaje corto; al hacer clic, el detalle completo del `resultado` (JSON).

## 3. Fuera de alcance

- Listar otros tipos de trabajos (Shopify, imágenes, etc.)
- Auto-refresh / polling
- Reintentar o cancelar trabajos desde la UI
- Cambiar la lógica de borrado local de documentos `prueba_masiva`
- Notificaciones push; el correo existente se mantiene

## 4. Decisiones

| Tema | Decisión |
|------|----------|
| Alcance de filas | Solo `tipo = pruebas_masivas` e `id_empresa` del usuario autenticado |
| Visibilidad UI | Solo si `empresa.fe_ambiente == '00'` **y** `esElSalvadorFe()` (misma condición del bloque actual de pruebas masivas) |
| Detalle de error | Mensaje corto en columna; clic abre modal con JSON formateado de `resultado` |
| Actualización | Al cargar la sección / estadísticas; botón “Actualizar”; también tras encolar un trabajo |
| Límite | Últimos 20, orden `id` DESC |
| Diseño visual | Reusar patrones de `empresa.component`: `section.bg-white.rounded.p-4`, `table table-hover align-middle`, `thead.table-light`, badges Bootstrap para estado, botones existentes (`btn-outline-primary`, etc.). Sin componente nuevo ni estilos custom |
| Enfoque API | Endpoint dedicado (no mezclar con `estadisticas`) |

## 5. Backend

### `GET /api/mh/pruebas-masivas/trabajos`

- Auth JWT (mismo group que el resto de pruebas-masivas).
- Query: `TrabajosPendientes` donde `tipo = 'pruebas_masivas'` y `id_empresa = Auth::user()->id_empresa`.
- `orderByDesc('id')->limit(20)`.
- Por cada fila, response:

```json
{
  "id": 123,
  "estado": "fallido",
  "tipo_dte": "14",
  "tipo_label": "Sujeto Excluido",
  "cantidad": 25,
  "fecha_creacion": "...",
  "fecha_inicio": null,
  "fecha_fin": "...",
  "mensaje": "texto corto desde resultado.message o resultado.error",
  "resultado": { }
}
```

- `parametros` y `resultado` se decodifican de JSON string si hace falta.
- `mensaje`: `resultado.message` → `resultado.error` → vacío si no hay.
- `tipo_label`: mismo mapa descriptivo que ya usa el controller al ejecutar (01 Facturas, 03 CCF, etc.).
- No requiere ambiente `00` en el endpoint (la UI ya lo oculta); si se llama fuera de pruebas, igual solo lista trabajos de esa empresa.

Ruta nueva en `routes/modulos/admin/pruebas-masivas-mh.php`. Método en `MHPruebasMasivasController` (lazy: sin service nuevo).

## 6. Frontend

Archivos tocados (mínimo):

- `MH.service.ts`: método `obtenerTrabajosPruebasMasivas()`.
- `empresa.component.ts` / `.html`: estado, carga, tabla, modal de detalle.

Colocación: **dentro** del `@if (empresa.fe_ambiente == '00' && esElSalvadorFe())`, debajo del `row` de cards de tipos, antes de cerrar el `</section>`.

Tabla:

| ID | Tipo | Cantidad | Estado | Creado | Fin | Mensaje |
|----|------|----------|--------|--------|-----|---------|
| … | badge/label | n | badge color por estado | fecha | fecha o — | truncado + link “Ver” |

Estados → badges:

- `pendiente` → secondary  
- `procesando` → primary / info  
- `completado` → success  
- `fallido` → danger  

Header de la subsección: título “Historial de trabajos” + botón Actualizar (icono refresh), alineado al estilo del `d-flex justify-content-between` del bloque.

Modal: reutilizar el patrón de modales/templates ya usado en `empresa.component` (`ng-template` + `AlertService`/`NgbModal` según el resto de la pantalla). Contenido: `<pre>` con `JSON.stringify(resultado, null, 2)`.

Vacío: mensaje breve tipo alert/info “No hay trabajos de pruebas masivas aún.”

## 7. Criterios de aceptación

1. En ambiente de pruebas SV se ve la tabla debajo de las cards.
2. En ambiente productivo (`fe_ambiente != '00'`) o no-SV no aparece.
3. Tras “Generar faltantes”, al refrescar (automático post-encolar o botón) aparece el trabajo en `pendiente` / luego su estado final.
4. Un trabajo `fallido` muestra mensaje corto; el modal muestra el JSON con el motivo.
5. Un trabajo `completado` se distingue visualmente del fallido.

## 8. Verificación mínima

- Un request manual al endpoint (o carga en UI) con empresa en `00` y al menos un trabajo fallido/completado en BD.
- Comprobar que no se listan trabajos de otra empresa ni de otro `tipo`.
