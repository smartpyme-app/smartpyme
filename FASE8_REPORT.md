# FASE 8 REPORT — SMARTPYME RESTAURANTE 1.0

**Fecha:** 2026-08-10  
**Plan:** `PLAN_HARDENING_RESTAURANTE_1.0.md` §10 (Servicios legacy)  
**Predecesoras:** Fase 1–7 cerradas y validadas  
**Estado:** Fase 8 — **COMPLETADA DENTRO DEL ALCANCE**  
**Siguiente:** **DETENERSE** — no iniciar Fase 9+ hasta aprobación explícita  
**Commit:** pendiente (manual por el usuario)

---

## 1. Resumen

Auditoría de `PedidoRestauranteInventarioService`: **clasificación C — MUERTO**.

Evidencia: cero callers ejecutables (controllers, services, jobs, commands, events, providers, tests, frontend). El camino autoritativo de inventario canal es **`PedidoCanalInventarioService`**, cableado en `PedidoRestauranteController@confirmar` / `@anular` y BoxFul (Fase 1).

**Acción:** eliminar únicamente el archivo legacy. Sin cambios al flujo de inventario, locks, flags ni `PedidoCanalInventarioService`.

---

## 2. Estado inicial

| Ítem | Estado |
|------|--------|
| Suite Restaurante pre-cambio | **45/45** |
| Archivo legacy | `Backend/app/Services/Restaurante/PedidoRestauranteInventarioService.php` |
| Referencias código PHP | Solo el propio archivo |
| Referencias docs | PLAN, AUDITORIA, FASE7_REPORT (mentions) |

---

## 3. Inventario del servicio legacy (antes de borrar)

| Aspecto | Detalle |
|---------|---------|
| Namespace | `App\Services\Restaurante` |
| Clase | `PedidoRestauranteInventarioService` |
| Métodos públicos | `aplicarAlConfirmar(PedidoRestaurante, $user): ?string` |
| | `revertirPorAnulacion(PedidoRestaurante, $userForKardex = null): ?string` |
| Protegidos/privados | Ninguno |
| Dependencias | `Empresa`, `Inventario`, `Lote`, `Producto`, `PedidoRestaurante` |
| Tablas / efectos | stock `inventarios` / `lotes`, `kardex`, flags `inventario_descontado_at`, `id_bodega_inventario`, `lote_id` en detalles |
| Helpers propios | Ninguno (lógica inline de lotes FIFO/LIFO/FEFO) |

---

## 4. Búsqueda de referencias (evidencia)

Búsquedas realizadas (repo, excl. vendor/node_modules):

| Patrón | Resultado ejecutable |
|--------|----------------------|
| `PedidoRestauranteInventarioService` | Solo archivo + docs |
| `aplicarAlConfirmar` | Solo en el legacy |
| `revertirPorAnulacion` | Legacy + **otro** servicio (`ReversionPuntosService` fidelización) — no relacionado |
| `use …PedidoRestauranteInventario` | **0** |
| Inyección / `app(…)` / `new PedidoRestaurante…` | **0** |
| Providers `bind`/`singleton` | **0** para esta clase |
| Tests Feature/Unit Restaurante | **0** |
| Frontend | **0** |
| Jobs / Commands / Listeners | **0** |
| Graphify path → `PedidoCanalInventarioService` | Sin path dirigido (clases desconectadas) |

**Post-eliminación:** grep solo deja menciones históricas en PLAN / AUDITORIA / FASE7_REPORT.

---

## 5. Flujo autoritativo actual (Fase 1)

### Callers de `PedidoCanalInventarioService`

| Caller | Uso |
|--------|-----|
| `PedidoRestauranteController@confirmar` | `lockForUpdate` + TX; early-return si ya `pendiente_facturar` + `inventario_descontado_at`; `aplicarSalidasAlConfirmar`; estado → `pendiente_facturar`; Idempotency-Key vía `RestauranteIdempotencyService` |
| `PedidoRestauranteController@anular` | TX; `revertirSalidasPedido` si pendiente_facturar + bodega |
| `BoxFulShippingController` | `aplicarSalidasAlConfirmar` (mismo SoT de descuento) |
| `FacturacionService` / `VentasController` | `ventaCoincideConPedido` (validación payload; no descuenta vía legacy) |

### Garantías presentes (no modificadas en Fase 8)

- Locks: `lockForUpdate` en confirmar  
- Idempotencia inventario: `inventario_descontado_at` early-return  
- Persistencia: `id_bodega_inventario` al aplicar  
- Kardex / lotes / composiciones: vía `PedidoCanalInventarioService` + `LoteAsignacionService`  
- Rollback anulación: `revertirSalidasPedido`  
- Estado: `borrador` → `pendiente_facturar`  
- Transacciones: `DB::transaction` en confirmar/anular  

**No se reemplazó ni tocó este flujo.**

---

## 6. Clasificación

**C. MUERTO**

Criterio cumplido: sin callers ejecutables reales; responsabilidad ya cubierta por `PedidoCanalInventarioService` (más completo: composiciones, `PedidoDetalleLote`, `LoteAsignacionService`, excepciones `RuntimeException`).

No A/B: no hay ruta dinámica/config/dispatch hacia el legacy.  
No D: evidencia suficiente (búsqueda exhaustiva + callers del camino vivo documentados).

---

## 7. Divergencias legacy vs canal (documental)

Si alguien reintrodujera el legacy en paralelo, riesgos históricos:

| Tema | Legacy | Canal (autoritativo) |
|------|--------|----------------------|
| API | `?string` error | `RuntimeException` |
| Bodega | Solo `$user->id_bodega` | Request / pedido / user (validada empresa) |
| Lotes | Switch FIFO/LIFO/FEFO inline (1 lote) | `LoteAsignacionService` + asignación manual multi-lote |
| Composiciones / servicio | No procesa composiciones de servicio | `procesarComposiciones` + `meta_inventario` |
| Idempotencia flag | Sí (`inventario_descontado_at`) | Sí (mismo flag) |

Riesgo de **doble descuento** si ambos caminos se invocaran: real en teoría; **imposible en código actual** tras eliminación del legacy. Un solo camino autoritativo.

---

## 8. Cambios implementados

| Acción | Archivo |
|--------|---------|
| **ELIMINADO** | `Backend/app/Services/Restaurante/PedidoRestauranteInventarioService.php` |
| Modificados | Ninguno |
| Imports huérfanos | Ninguno (no había) |
| `PedidoCanalInventarioService` | Sin cambios |

Código auxiliar huérfano: **ninguno** (modelos/helpers compartidos siguen usados por el canal).

---

## 9. Tests

| Momento | Comando | Resultado |
|---------|---------|-----------|
| Antes | `php artisan test tests/Feature/Restaurante` | **45 passed** (176 assertions) |
| Después | mismo | **45 passed** (176 assertions) |

Incluye `ConcurrencyIntegrityTest` de inventario/`inventario_descontado_at` (Fase 1) — verde.

Deuda Karma/Ventas: fuera de alcance (igual que Fases 4–7).

---

## 10. Migraciones / infraestructura

Ninguna. Sin cleanup Fase 7. Sin observabilidad Fase 10. Sin reportes Fase 9. Sin load tests.

---

## 11. Problemas encontrados

Ninguno bloqueante. Solo deuda documental: PLAN/AUDITORIA aún mencionan el servicio (histórico; no reescritos para no ampliar alcance).

---

## 12. Riesgos

| Riesgo | Evaluación |
|--------|------------|
| Romper inventario canal | **Bajo** — callers no usaban el legacy |
| Código externo/fork privado | **Bajo** — no hay binding; si existiera fork out-of-repo, fallaría al autoload (aceptable) |
| Confundir con `ReversionPuntosService::revertirPorAnulacion` | Nombre similar; dominio distinto |

---

## 13. Hallazgos para fases posteriores

| Ítem | Fase |
|------|------|
| Arquitectura reportes (sin path mesero) | **9** |
| Métricas / observabilidad | **10** |
| Cleanup outbox / soft-deletes / cocina `servido` | Post-7 (ops), no 8 |
| Load/Peak | **12/13** |

---

## 14. Desviaciones del plan

Ninguna material. Plan: *“Eliminar solo si cero dependencias y tests pasan”* → cumplido.

---

## 15. Criterios de completitud

- [x] Auditoría completa de referencias
- [x] Comparación vs flujo real (`PedidoCanalInventarioService` + confirmar)
- [x] Clasificación con evidencia (**MUERTO**)
- [x] Eliminación mínima del legacy
- [x] Sin tocar protecciones Fases 1–7
- [x] Suite Restaurante 45/45 antes y después
- [x] Sin Fase 9+
- [x] `FASE8_REPORT.md` creado
- [x] Sin commit automático

---

## 16. Siguiente paso

**DETENERSE.**

Esperar aprobación explícita para **Fase 9 — Reportes** (solo arquitectura documentada; sin agregaciones pesadas en path mesero).

---

**FASE 8 COMPLETADA — DETENERSE — ESPERANDO APROBACIÓN PARA FASE 9**
