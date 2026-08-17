# Diseño: Tipos de cálculo configurables — comisiones y bonos

**Fecha:** 2026-08-14  
**Estado:** Aprobado  
**Tipo:** Extensión de módulos existentes (no reescritura)  
**Baseline:** `Docs/superpowers/specs/2026-07-27-comisiones-bonos-gift-cards-design.md` (v1, rama `dc.SP-1888`)  
**Enfoque:** Reglas + estrategias. Dimensiones independientes: `tipo_calculo` × `alcance` × (solo comisiones) `momento_devengo`.

---

## 1. Contexto y problema

v1 ya calcula comisiones **por categoría** (evento al pasar la venta a `Pagada`) y bonos `meta_fija` / `escalonado` con alcance `global` | `vendedores`. Eso queda corto: el cliente necesita más fórmulas, alcance individual/equipo, complemento a salario mínimo (SV), y que “por categoría” siga funcionando igual con los datos de prueba ya cargados.

No se reescribe el módulo. Gift cards, Excel, PDF, dashboard, feature flags y hooks de venta conservan su contrato.

## 2. Objetivos

1. Comisiones: tipos `por_categoria` | `por_volumen` | `por_margen`, configurables por empresa (multi-tenant).
2. Bonos: tipos `meta_fija` | `escalonado` | `porcentaje_excedente` | `grupal` | `cualitativo_manual`.
3. Alcance independiente del tipo: `global` | `individual` | `equipo`.
4. Ajuste a salario mínimo visible en la liquidación del vendedor, independiente del tipo.
5. Migración compatible: % por categoría existentes siguen produciendo los mismos montos.

## 3. Decisiones acordadas

| Tema | Decisión |
|------|----------|
| Patrón | Estrategia: interfaz + una clase por tipo. Mismo patrón en comisiones y bonos. |
| “Por cobro efectivo” | **No es tipo de cálculo.** Es `momento_devengo` de la regla de comisión. |
| “Mixta (base + %)” | **No es tipo de cálculo.** Es `salario_base` en `config` de la regla, sumado al cerrar el período. |
| Combinación global + individual | **Se suman.** Si la regla no-global tiene `reemplaza_global = true`, sustituye a las globales para ese vendedor. |
| Volumen | Tramo **último que cumple** sobre el total del período (misma semántica que bono escalonado). Se persiste **al cerrar** el período, no en cada venta. |
| Margen | Por línea: `% × max(0, base_calculo − costo_linea)`. Costo = `costo_promedio` si > 0, si no `costo`. |
| Salario mínimo | Post-procesador de liquidación. Fuente: `EmpresaConfiguracionPlanilla.configuracion.salario_minimo`. Si no hay config, no se ajusta. |
| Equipos v1 | Sin tabla `vendedor_equipos`. `alcance=equipo` usa `id_vendedores` JSON. Techo documentado; upgrade = tabla de equipos. |
| Alcance individual | `id_vendedores` con exactamente un id. |
| Cualitativo | No entra al job. Alta manual a `bono_generados` con el mismo modelo. |
| UI | Extender pantallas actuales (`comisiones/configuracion`, `bonos/reglas`). Sin pantalla nueva. |
| Hooks de venta | Conservar `ComisionService::registrarVentaPagada($venta)`. Internamente despacha reglas. |
| Gift / Excel / PDF / flags | Fuera de este cambio salvo columnas nuevas en liquidación y orígenes extra en el ledger. |
| UI legacy `tipo_comision` en subcategorías de inventario | No reactivar. Sigue sin ser fuente de verdad. |

## 4. Dimensiones de una regla

```text
tipo_calculo  ×  alcance  ×  momento_devengo (solo comisiones)
             ×  salario_base (solo comisiones, opcional)
             ×  reemplaza_global
```

### 4.1 `tipo_calculo` — comisiones

| Valor | Cuándo calcula | Fórmula |
|-------|----------------|---------|
| `por_categoria` | Evento de línea | `%` subcategoría → categoría → 0. Igual que v1. |
| `por_margen` | Evento de línea | `%` de `config.porcentaje` sobre `max(0, base − costo_linea)` |
| `por_volumen` | Cierre de período | Sobre ventas del período, último tramo `{umbral, porcentaje}` que cumple |

`por_categoria` es el default del backfill. Sigue usando `comision_categoria_config` / `comision_subcategoria_config`.

### 4.2 `tipo_calculo` — bonos

| Valor | Fórmula |
|-------|---------|
| `meta_fija` | Si ventas ≥ `config.meta` → `config.bono`, si no 0. **Sin cambio.** |
| `escalonado` | Último tramo `{meta, bono}` que cumple. **Sin cambio.** |
| `porcentaje_excedente` | Si ventas > `config.meta` → `(ventas − meta) × config.porcentaje / 100`, si no 0. |
| `grupal` | Meta sobre **suma del equipo**. Reparto `config.reparto`: `equitativo` o `proporcional` (por ventas del integrante / ventas del equipo). Requiere `alcance=equipo`. |
| `cualitativo_manual` | El job no genera monto. El supervisor crea el `bono_generados`. |

### 4.3 `alcance`

| Valor | Quién entra | Campos |
|-------|-------------|--------|
| `global` | Todos los vendedores con actividad (comportamiento v1) | `id_vendedores = null` |
| `individual` | Un vendedor | `id_vendedores = [id]` (length 1) |
| `equipo` | Lista de vendedores | `id_vendedores = [ids…]` (length ≥ 1) |

Bonos v1 usaban `alcance=vendedores`. Lectura: 1 id → `individual`; N ids → `equipo`. Escrituras nuevas no usan `vendedores`.

### 4.4 `momento_devengo` (solo comisiones)

| Valor | Cuándo se escribe el movimiento de línea |
|-------|------------------------------------------|
| `al_pagar` (default) | Venta `Pagada` (contado al facturar; crédito al saldar). **Igual que v1.** |
| `al_facturar` | Al crear la venta, aunque quede `Pendiente` (crédito). |
| `por_abono` | En cada abono: comisión de la línea × (`monto_abono` / `total_venta`). Idempotente por `(origen, id_detalle_venta, id_abono)`. |

Tipos de **período** (`por_volumen`, `salario_base`, ajuste mínimo) ignoran `momento_devengo` y corren al cerrar.

### 4.5 Combinación de reglas

Para un vendedor en un período:

1. Cargar reglas activas de la empresa cuyo alcance lo incluye.
2. Si alguna de las no-globales aplicables tiene `reemplaza_global = true`, **descartar las globales**.
3. Sumar los resultados de las reglas que quedan.

Varias individuales sin `reemplaza_global` también se suman.

`salario_base` sigue la misma regla (se suma por regla aplicable). No configurar base en una global y una individual a la vez salvo que se quiera duplicar a propósito.

## 5. Modelo de datos

Todas las tablas nuevas: `id_empresa` + scope de tenant. **No se dropean** las tablas v1.

### 5.1 `comision_reglas` (nueva)

| Columna | Tipo | Notas |
|---------|------|--------|
| `id_empresa` | unsignedBigInteger | |
| `nombre` | string | |
| `tipo_calculo` | string(32) | `por_categoria` \| `por_volumen` \| `por_margen` |
| `alcance` | string(32) | default `global` |
| `id_vendedores` | json nullable | |
| `momento_devengo` | string(32) | default `al_pagar` |
| `reemplaza_global` | boolean | default false |
| `config` | json | volumen: `{tramos:[{umbral, porcentaje}]}`; margen: `{porcentaje}`; mixto: `{salario_base?: number}` (el base se puede poner en cualquier tipo) |
| `activo` | boolean | default true |
| timestamps | | |

### 5.2 Tablas v1 de % por categoría

- Agregar `id_regla` nullable → NOT NULL tras backfill.
- Unique nuevo: `(id_regla, id_categoria)` y `(id_regla, id_subcategoria)`.
- Quitar unique `(id_empresa, id_categoria)` / `(id_empresa, id_subcategoria)`.

### 5.3 `comision_movimientos`

Sin rediseño. Orígenes nuevos:

- `ajuste_periodo` — resultado de `por_volumen` al cerrar
- `salario_base` — `config.salario_base` al cerrar
- `ajuste_salario_minimo` — complemento legal al cerrar
- `abono` — prorrateo `por_abono` (fase 2)

Idempotencia volumen/base/mínimo: unique razonable `(id_empresa, origen, id_periodo, id_vendedor, id_regla)` donde aplique. Agregar `id_regla` nullable al ledger (trazabilidad; las filas v1 quedan null).

### 5.4 `comision_liquidaciones`

Columnas nuevas (default 0 / null):

- `salario_base` decimal(14,4) default 0
- `ajuste_salario_minimo` decimal(14,4) default 0
- `salario_minimo_aplicado` decimal(14,4) nullable
- `total_a_pagar` decimal(14,4) — `total_comision + salario_base + ajuste_salario_minimo`

`total_comision` sigue siendo la suma del ledger **sin** salario base ni mínimo (esos van en sus columnas). Excel/PDF: desglose, nunca un solo monto opaco.

### 5.5 Bonos

- `bono_reglas.tipo`: ampliar el string; validación en servicio (no enum SQL).
- `bono_reglas.alcance`: aceptar `global` \| `individual` \| `equipo`; leer `vendedores` como alias.
- `bono_reglas.reemplaza_global` boolean default false.
- `bono_generados.origen` string default `evaluacion` (`evaluacion` \| `manual`).
- Unique v1 `(id_empresa, id_vendedor, id_regla, periodo_inicio, periodo_fin)` se mantiene.

### 5.6 Backfill (obligatorio, no destructivo)

Para cada `id_empresa` que tenga filas en `comision_categoria_config` **o** `comision_subcategoria_config` **o** feature `comisiones-vendedores` activa:

1. Insertar `comision_reglas` (`nombre` = `Por categoría`, `tipo_calculo=por_categoria`, `alcance=global`, `momento_devengo=al_pagar`, `activo=true`, `config={}`).
2. Setear `id_regla` en las filas de config de esa empresa.
3. Si no había configs, la regla existe y el resolver sigue devolviendo 0% → mismos movimientos (ninguno).

Empresas sin feature y sin configs: no se crea regla.

## 6. Arquitectura de cálculo

### 6.1 Contratos

```text
ComisionCalculator
  calcularEnEvento(ComisionEventoContexto): ?ComisionCalculoResultado
  calcularEnCierre(ComisionCierreContexto): list<ComisionCalculoResultado>

BonoCalculator
  calcular(BonoCalculoContexto): float
```

`ComisionEventoContexto`: empresa, regla, venta, detalle, vendedor efectivo, base ya calculada, fracción gift, evento (`facturada` \| `pagada` \| `abono`), abono opcional.

`ComisionCierreContexto`: empresa, regla, período, id_vendedor, ventas_periodo, comisión_ledger_ya_persistida.

Dispatcher (`ComisionCalculatorFactory` / `BonoCalculatorFactory`): mapa `tipo → clase`. `default` del match = `never` vía excepción `tipo desconocido` (ya existe en el evaluator de bonos).

### 6.2 Comisiones — flujo de evento (línea)

1. Hook llama `registrarVentaPagada` / (fase 2) facturación pendiente / abono.
2. Si flag off → return (igual que v1).
3. Cargar reglas activas aplicables al vendedor efectivo de la línea.
4. Filtrar por `momento_devengo` vs el evento.
5. Aplicar combinación (suma / reemplaza_global).
6. Para cada regla `calcularEnEvento`; si no null y monto ≠ 0, persistir movimiento con `id_regla`.
7. Gift cards / categoría gift: misma exclusión v1, **antes** de calcular.
8. Devoluciones: siguen ajustando movimientos originales por ratio. No reimplementar.

Si no hay reglas (empresa sin backfill), fallback: una regla implícita `por_categoria` global `al_pagar` leyendo las tablas v1. Así un migrate a medias no cambia cifras.

### 6.3 Comisiones — flujo de cierre de período

`ComisionLiquidacionService::cerrarPeriodo` (ya existe) se extiende, no se reemplaza:

1. Para cada vendedor con movimientos **o** cubierto por una regla de período/base:
   - correr `calcularEnCierre` de reglas `por_volumen` y persistir `origen=ajuste_periodo`
   - si `config.salario_base` > 0, persistir `origen=salario_base`
2. Sumar `total_comision` del ledger **excluyendo** `salario_base` y `ajuste_salario_minimo`.
3. `ajuste = max(0, salario_minimo − (total_comision + salario_base))`. Persistirlo y copiar a la liquidación.
4. `total_a_pagar = total_comision + salario_base + ajuste`.

Período abierto: reportes de volumen se calculan **en vivo** (no hay filas aún). El dashboard no debe mostrar 0 engañoso: o preview o “pendiente de cierre”. Decisión UI fase 4: mostrar preview etiquetado “estimado”.

### 6.4 Bonos — flujo

`BonoEvaluationService` igual: período, reglas activas, candidatos por alcance, upsert `pendiente`, no pisa `aprobado`/`pagado`.

Cambios:

- Despachar `BonoCalculator` por `tipo`.
- `cualitativo_manual`: skip en el loop (no borrar pendientes manuales).
- `grupal`: una vez por equipo, luego repartir a cada `id_vendedores`.
- Combinación: si un vendedor tiene regla individual `reemplaza_global`, no evaluar globales para él.
- Endpoint nuevo: `POST bonos/generados/manual` `{id_regla, id_vendedor, periodo_inicio, periodo_fin, monto, motivo?}`. Valida tipo `cualitativo_manual`, alcance, y unique.

### 6.5 Dónde vive cada clase (no inflar `ComisionService`)

```text
Backend/app/Services/Comisiones/
  ComisionService.php              orquestación evento (ya existe)
  ComisionLiquidacionService.php   cierre (ya existe; se extiende)
  Calculators/
    ComisionCalculator.php         interfaz
    PorCategoriaCalculator.php     envuelve ComisionPorcentajeResolver
    PorMargenCalculator.php
    PorVolumenCalculator.php
    ComisionCalculatorFactory.php
  ComisionReglaScope.php           filtra reglas por vendedor + combinación

Backend/app/Services/Bonos/
  BonoReglaEvaluator.php           facade: factory + calcular (firma actual se mantiene con $tipo)
  Calculators/
    BonoCalculator.php
    MetaFijaCalculator.php
    EscalonadoCalculator.php
    PorcentajeExcedenteCalculator.php
    GrupalCalculator.php
    CualitativoManualCalculator.php  calcular() = 0; el job no lo llama
    BonoCalculatorFactory.php
```

`BonoReglaEvaluator::calcular(string $tipo, array $config, float $ventas)` **se conserva** para no romper tests v1. Internamente delega al factory. Grupal usa un método extra `calcularGrupal(...)` porque necesita ventas por integrante.

## 7. API y UI

### 7.1 Comisiones

Extender, no reemplazar:

- `GET/POST/PUT comisiones/config/reglas` — CRUD de `comision_reglas`.
- `GET/PUT comisiones/config/categorias…` — igual que v1, pero operan sobre la regla `por_categoria` activa (query `id_regla` opcional; default = la global backfilleada).
- Liquidación: el JSON ya anidado en períodos incluye las columnas nuevas.

Pantalla `config-categorias`:

1. Selector de regla (o “Por categoría” si solo hay la default).
2. Alta de regla: tipo, alcance, momento, salario_base, reemplaza_global, config del tipo.
3. Si tipo = `por_categoria`, la grilla actual de % no se mueve.

### 7.2 Bonos

- Validación `tipo` y `alcance` ampliados en `BonoReglaController`.
- Form de `reglas`: opciones nuevas + campos de config; alcance `individual` (select único) / `equipo` (checkboxes, reusa el control v1) / `global`.
- `generados`: botón “Asignar bono manual” si hay reglas cualitativas activas.

## 8. Plan por fases

| Fase | Entregable | Merge |
|------|------------|-------|
| **0** Andamiaje | `comision_reglas` + backfill + strategy con **solo** `PorCategoria`. `ComisionService` despacha; cifras idénticas. | Puede ir **en `dc.SP-1888` antes del merge a principal**. |
| **1** Bonos tipos | excedente, cualitativo, alcance individual/equipo, grupal, `reemplaza_global` | PR posterior |
| **2** Comisiones línea | `por_margen`; `momento_devengo` `al_facturar` / `por_abono` | PR posterior |
| **3** Comisiones período | `por_volumen`; `salario_base`; ajuste salario mínimo al cerrar | PR posterior |
| **4** UI admin | selectores en pantallas actuales; preview volumen en período abierto | PR posterior |

Fase 0 no cambia Excel/PDF/dashboard. Fases 3–4 sí muestran desglose de ajuste mínimo.

## 9. Fuera de alcance

- Tabla `vendedor_equipos` (v1 usa lista JSON).
- Recálculo masivo de ventas anteriores al go-live de un tipo nuevo.
- Empujar comisiones/bonos a `planilla_detalles`.
- Firma digital.
- Motor por producto individual (sigue categoría + override subcategoría).
- Tramo progresivo de volumen (cada umbral a su % sobre el excedente). Si se pide después, es otro calculator.
- Reactivar `tipo_comision` legacy de inventario.

## 10. Riesgos y mitigaciones

| Riesgo | Mitigación |
|--------|------------|
| Calcular volumen en cada venta | Prohibido. Solo cierre + preview. |
| Cambiar firma de `registrarVentaPagada` | Conservarla. Nuevos eventos = métodos extra o parámetro interno. |
| Unique viejo de categoría vs varias reglas | Cambiar unique a `(id_regla, id_categoria)` en la misma migración del backfill. |
| Liquidaciones pagadas | No reescribir períodos `pagado`. Igual que v1 regla B de devoluciones. |
| Job CLI sin `id_empresa` | Ya resuelto en evaluador de bonos; no relajar scopes. |
| Costo en 0 → margen = base | Documentar; `max(0, base − costo)` con costo 0 equivale a % sobre base. No inventar costo. |
| Tests v1 de evaluator / orchestration | Deben seguir verdes tras fase 0 y 1. |

## 11. Criterios de éxito

- Empresa con % por categoría cargados: mismos `monto_comision` por línea que antes de la migración, mismo período.
- Activar `por_margen` / `por_volumen` no altera reglas `por_categoria` de otras empresas.
- Bono `meta_fija` / `escalonado` existentes: mismos montos.
- Liquidación muestra `total_comision`, `salario_base`, `ajuste_salario_minimo`, `total_a_pagar` por separado.
- Flag off: no hay cálculos nuevos; histórico consultable (igual v1).
- Gift cards, Excel, PDF, dashboard no se rompen en fase 0.
