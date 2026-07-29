# QA checklist — Bonos vendedores (Fase 3–4)

**Branch:** `feat/bonos-vendedores`  
**Entorno:** staging (empresa de prueba) — **pendiente ejecución manual**  
**Automated:** `php vendor/bin/phpunit tests/Unit/Services/Bonos/ tests/Unit/Services/Incentivos/` → 9/9 PASS

---

## Step 0 — Preparación / flags

- [ ] Super Admin → activar **Bonos de Vendedores** (`bonos-vendedores`); confirmar `empresa_funcionalidades.activo = 1`.
- [ ] Login empresa: menú **Bonos** visible; rutas `/bonos/reglas`, `/bonos/generados` cargan sin 403.
- [ ] Crear regla **meta_fija** activa (p. ej. meta `40000`, bono `100`, ventana mensual).
- [ ] Escenario flags independientes: empresa con **solo** `bonos-vendedores` ON (comisiones y gift OFF) — dashboard `/incentivos/vendedores` debe cargar y mostrar sección bonos/progreso sin error 500.

## Step 1 — meta_fija: bajo meta vs en meta

- [ ] Vendedor A: facturar ventas **Pagada** del mes que sumen **&lt; meta** (p. ej. $39 999).
- [ ] **Bonos → Generados** → **Calcular período** (`POST /api/bonos/evaluar`) o `php artisan bonos:evaluar --empresa={id}`.
- [ ] Confirmar **no** hay fila en `bono_generados` para vendedor A + regla (resumen `omitidos_monto` &gt; 0).
- [ ] Vendedor B: ventas del mes **≥ meta** ($40 000+).
- [ ] Recalcular → fila `bono_generados`: `monto = 100`, `monto_ventas_base` coherente, `estado = pendiente`.

## Step 2 — Idempotencia y protección de aprobados

- [ ] Ejecutar **Calcular período** dos veces seguidas con mismos datos → sigue **1 fila** por `(id_empresa, id_vendedor, id_regla, periodo_inicio, periodo_fin)`; segunda corrida actualiza monto si sigue `pendiente`, no duplica.
- [ ] Aprobar bono (`POST /api/bonos/generados/{id}/aprobar`) → `estado = aprobado`, `aprobado_por` / `aprobado_at` seteados.
- [ ] Cambiar ventas para que el monto calculado sería distinto; recalcular → `monto` y `estado` **sin cambio** (protegido; resumen `protegidos`).

## Step 3 — Flujo aprobar → pagar

- [ ] Desde `pendiente`: botón **Pagar** debe fallar (422 / mensaje “solo … aprobado”).
- [ ] Aprobar → **Pagar** (`POST …/pagar`) → `estado = pagado`, `pagado_at` seteado.
- [ ] Segundo pago o aprobar sobre `pagado` → rechazado.

## Step 4 — Bonos no contaminan ventas / facturación

- [ ] Export Excel ventas del período (`/api/ventas/export` o equivalente UI) → montos = solo ventas reales; **no** líneas ni columnas de `bono_generados`.
- [ ] Reportes facturación / cobros por vendedor → totales de ventas/cobros sin sumar bonos.
- [ ] Confirmar evaluación de bonos **no inserta** filas en `ventas` ni `comision_movimientos`.

## Step 5 — Dashboard consolidado + comprobante desglosado

- [ ] **Incentivos → Dashboard vendedores** (`GET /api/incentivos/vendedores?desde=&hasta=`): columnas separadas Comisiones / Bonos / Total; botón **Ver** abre detalle.
- [ ] Detalle vendedor: bloques `ventas_por_categoria`, `bonos`, `progreso_bono` (actual / meta / faltante); pie con **Comisiones**, **Bonos (aprob./pag.)** y **Total** — nunca un solo monto opaco.
- [ ] Bono `pendiente` aparece en detalle/progreso pero **no** en total a pagar hasta aprobar/pagar.
- [ ] Comprobante PDF comisión (`GET /api/comisiones/comprobante/{id_vendedor}?periodo_id=`): secciones comisión + bonos + totales desglosados.

## Step 6 — Flags y legacy

- [ ] Solo bonos ON: dashboard muestra bonos/progreso; secciones comisiones/gift omitidas o en cero sin romper UI.
- [ ] Con `comisiones-vendedores` ON: **Inventario → Subcategorías** muestra banner legacy (“usar Comisiones → Configuración”).

## Step 7 — Flag apagado

- [ ] Desactivar `bonos-vendedores` → menú Bonos oculto; `POST /api/bonos/evaluar` y rutas bonos → 403.
- [ ] `GET /api/incentivos/vendedores` sigue respondiendo (sin sección bonos si flag off).

---

## Sign-off

| Rol | Nombre | Fecha | Resultado |
|-----|--------|-------|-----------|
| QA / Product | | | ☐ Pass ☐ Fail |
| Dev | | | Automated unit tests OK; staging manual **pendiente** |

**Notas / incidencias:**
