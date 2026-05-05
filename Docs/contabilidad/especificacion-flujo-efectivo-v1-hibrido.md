# Especificación funcional — Estado de flujo de efectivo híbrido (v1)

**Proyecto:** SmartPyME — módulo contabilidad  
**Versión del documento:** 1.1 (Fase 0 — comparativa periodo anterior incorporada)  
**Referencia técnica:** `BalanceGeneralNiifSvPresenter`, `EstadoResultadosNiifSvPresenter`  
**Moneda y marco:** USD, enfoque NIIF para PYMES orientado a El Salvador (CVPCPA), coherente con balance y estado de resultados ya exportados.

---

## 1. Objetivo

Entregar a cada empresa un **Estado de flujo de efectivo** exportable (PDF y Excel, en fase posterior) que:

- Sea **inteligible** para la PYME (énfasis en liquidez y conciliación con caja).
- Sea **coherente** con los mismos datos contables que el balance general y el estado de resultados del mismo rango de fechas.
- Use un enfoque **híbrido v1**: **método indirecto** en el cuerpo del estado, más **notas / anexos** derivados del movimiento en cuentas de efectivo cuando los datos lo permitan.

---

## 2. Alcance v1 y exclusiones

**Incluido en v1**

- Un solo periodo configurable: `fecha_inicio` … `fecha_fin` (mismo criterio que otros reportes).
- Partidas con estado `Aplicada` o `Cerrada` y `fecha` en el rango.
- Tres bloques NIIF habituales: **actividades operativas**, **de inversión**, **de financiación**.
- Leyenda de metodología y limitaciones (catálogo, cuentas en “Otros”, estimaciones del ER).
- **Comparativa con periodo anterior** (opcional), misma regla que el estado de resultados: parámetro de consulta `comparar=1` y periodo inmediatamente anterior con **el mismo número de días** que el periodo actual (ver sección 3.3).

**Excluido en v1** (salvo nueva aprobación)

- Estado de flujo **directo** NIIF completo por cobros/pagos.
- Consolidación multi-empresa.
- Flujo en moneda extranjera distinta de la presentación USD del resto de reportes NIIF SV.

---

## 3. Definiciones de fechas y saldos

### 3.1 Rango del reporte

- **Inicio del periodo:** `fecha_inicio` 00:00:00.
- **Fin del periodo:** `fecha_fin` 23:59:59 (o fin de día, según patrón actual de `Carbon` en reportes).

### 3.2 Saldo “al inicio” y “al fin” de una línea de balance NIIF

Para cada **clave interna** del balance (`efectivo_equivalentes`, `cuentas_cobrar_clientes`, … — ver anexo A), se define:

- **Saldo al fin del periodo (cierre):** mismo criterio que `BalanceGeneralNiifSvPresenter::build` al **último día** del rango: saldo por cuenta hoja (inicial + movimientos en el rango, con agregación a padres), clasificado en la línea NIIF correspondiente.

- **Saldo al inicio del periodo:** mismo algoritmo de clasificación y saldo, pero considerando el **subperiodo** desde una fecha “origen” hasta el día **anterior** a `fecha_inicio`:
  - Si existe información de **periodo anterior** vía `SaldoMensual` (misma lógica que `obtenerSaldosIniciales` en balance): usar saldos iniciales coherentes con el balance ya implementado.
  - Si **no** hay periodo anterior en `SaldoMensual`: el saldo inicial por cuenta puede venir de `catalogo_cuentas.saldo_inicial` y movimientos acumulados hasta víspera de inicio; si aun así no hay base fiable, la línea de “efectivo al inicio” se muestra con **nota explícita** “no determinado / revise saldos iniciales y mayorizaciones”.

**Variación en el periodo** para una línea L:

`ΔL = saldo_fin(L) − saldo_inicio(L)`  
(con signo según convención del EFE indirecto; ver sección 6).

### 3.3 Comparativa con periodo anterior

Cuando el usuario active la comparativa (`comparar=1` en la URL del reporte, análogo a `/api/reportes/estado/resultados/...&comparar=1`):

**Definición del periodo anterior** — misma función conceptual que `EstadoResultadosNiifSvPresenter::periodoAnterior`:

- `fin_anterior` = día **anterior** a `fecha_inicio` (fin de día).
- `inicio_anterior` = retroceder desde `fin_anterior` **(N − 1) días** hacia atrás, donde **N** = número de días del periodo actual inclusive (`fecha_inicio` … `fecha_fin`).

Así el periodo comparativo tiene **la misma longitud en días** que el actual, evitando sesgos por meses de distinta duración.

**Qué se calcula dos veces**

1. **Periodo actual:** todo el estado (secciones 4–9) con `[fecha_inicio, fecha_fin]`.
2. **Periodo anterior:** el mismo pipeline de cálculo con `[inicio_anterior, fin_anterior]`.

Incluye: utilidad neta (ER) de cada periodo, ajustes por depreciación del periodo, Δ de capital de trabajo de cada periodo, subtotales operación / inversión / financiación, incremento neto de efectivo, efectivo inicio/fin **de cada periodo**, y anexo de conciliación **para cada periodo** (o al menos conciliación del periodo actual y variación de efectivo del anterior en columnas, según diseño de plantilla).

**Presentación sugerida**

- Tablas con columnas: **Periodo actual** | **Periodo anterior** | **Diferencia (actual − anterior)** en importe; opcional una cuarta columna **%** sobre el valor absoluto del anterior cuando `|anterior| > ε` (mismo criterio de “crecimiento” que tenga sentido para flujos, que pueden ser negativos — en UI puede mostrarse “N/A” si el denominador es ~0).
- Encabezado o subtítulo con las etiquetas de periodo legibles (p. ej. “Del … al …” para ambos), reutilizando el estilo de `periodo_titulo` del ER si aplica.

**Leyenda obligatoria con comparativa**

> “El periodo comparativo tiene la misma duración en días que el seleccionado y termina el día anterior al inicio de dicho periodo.”

**Sin comparativa** (`comparar` ausente o distinto de `1`): una sola columna de importes como en la especificación original.

---

## 4. Punto de partida del método indirecto: resultado del periodo

### 4.1 Fuente única acordada (v1)

**Línea de partida:** la misma cifra que el estado de resultados NIIF exportado en la cascada bajo la etiqueta constante:

`EstadoResultadosNiifSvPresenter::LBL_UTIL_NETA`  
(*“UTILIDAD NETA DEL EJERCICIO (estimada)”*).

Es decir: se invoca (o se reutiliza internamente) el resultado de `EstadoResultadosNiifSvPresenter::build` para el mismo `empresaId`, `fecha_inicio`, `fecha_fin` y se toma el valor numérico de esa línea.

### 4.2 Justificación y leyenda obligatoria en el reporte

El ER del sistema incorpora **estimaciones** (reserva legal porcentual, ISR según tramos, pago a cuenta 1,75 % sobre ingresos brutos, etc.). El flujo de efectivo v1 **no sustituye** esas reglas fiscales del ER; las **hereda** como punto de partida para mantener **cuadre conceptual** con el ER que el usuario ya ve.

Texto tipo (redactable en legal final):

> “El flujo de efectivo inicia con la utilidad neta **(estimada)** del estado de resultados del mismo periodo. Las partidas de efectivo y equivalentes reflejan el registro contable; las diferencias con extractos bancarios pueden deberse a temporizaciones o ajustes no registrados.”

### 4.3 Relación con `utilidad_ejercicio` del balance

El balance NIIF presenta en patrimonio la línea **“Utilidad (pérdida) del ejercicio”** (`utilidad_ejercicio`) y **“Utilidades retenidas…”** (`utilidades_retenidas`), calculadas con saldos de cuentas patrimoniales distintas del waterfall del ER.

**Regla anti-doble conteo (v1):**

- El **punto de partida** del EFE es **solo** la utilidad neta del **ER** (sección 4.1).
- Las **variaciones** de patrimonio por líneas `capital_social`, `reserva_legal`, `utilidades_retenidas`, `utilidad_ejercicio`, `superavit_revaluacion` se tratan **solo** en el bloque de **financiación** (y en operación solo si una norma explícita lo exige en v2), **sin** volver a sumar la utilidad del ejercicio como si fuera otra utilidad operativa.
- En la práctica de implementación: si un cierre contable capitaliza resultados entre `utilidad_ejercicio` y `utilidades_retenidas`, la **variación neta** entre inicio y fin de esas cuentas refleja el **flujo de financiación / distribución** respecto al resultado ya tomado del ER; el detalle fino se documentará en notas o en v1.1 según feedback contable.

*(Si en implementación se detecta inconsistencia material entre ER neto y movimientos de cuentas de resultado en partidas, se documenta como hallazgo y se propone v1.1 con opción “utilidad desde balance”.)*

---

## 5. Ajustes por partidas sin efecto de caja (v1 mínimo)

### 5.1 Depreciación y amortización

**Sumar de vuelta** al flujo operativo (porque reducen resultado pero no son salida de caja en el periodo):

- Suma del **movimiento del periodo** (debe en cuentas de gasto clasificadas como depreciación/amortización en el ER: claves internas `gasto_venta_deprec`, `gasto_admin_deprec`, y si en futuro se desglosa más, las que el ER ya agrupa como depreciación operativa).

**Alternativa equivalente:** variación de `depreciacion_acumulada` en balance con signo contable invertido para el efecto caja; **v1 recomendado:** preferir **movimiento desde buckets del ER** para alinear con la misma “historia” que la utilidad de partida.

### 5.2 Otros ajustes no efectivo (v2 o backlog)

Provisiones no desembolsadas, deterioro de inventarios sin movimiento de caja, ganancias/pérdidas por tenencia de activos, etc.: **no obligatorios en v1**; se listan en sección “Limitaciones conocidas” del PDF.

---

## 6. Actividades operativas (indirecto + capital de trabajo)

Después de:  
`Utilidad neta (ER)` + `Ajustes no efectivo (v1: depreciación/amortización operativa)`,

se presentan líneas de **variación del capital de trabajo** y partidas operativas corrientes, como **cambio en el periodo** (Δ) entre saldos inicio y fin, con **signo para efecto sobre caja**:

| Concepto (etiqueta sugerida) | Clave balance origen | Efecto típico sobre caja si el activo **aumenta** |
|-----------------------------|----------------------|---------------------------------------------------|
| (Aumento) disminución cuentas por cobrar – clientes | `cuentas_cobrar_clientes` | Aumento de activo → **sale** caja (mostrar negativo) |
| Documentos por cobrar | `documentos_cobrar` | Igual lógica activo |
| Provisión incobrables (neto) | `provision_incobrables` | Según naturaleza y presentación en balance |
| Inventarios | `inventarios` | Aumento inventario → **sale** caja |
| IVA crédito fiscal | `iva_credito_fiscal` | Aumento → típicamente **sale** caja (pagos/recuperación según caso; nota) |
| Pago a cuenta acumulado | `pago_cuenta_acumulado` | Activo; aumento → **sale** caja |
| Gastos pagados por anticipado | `gastos_anticipados` | Aumento → **sale** caja |
| Otros activos corrientes | `otros_activos_corrientes` | Aumento → **sale** caja |
| Cuentas por pagar – proveedores | `cuentas_pagar_proveedores` | Aumento pasivo → **entra** caja (positivo) |
| Préstamos CP (operativos / sobregiros) | `prestamos_corto_plazo` | Aumento pasivo → **entra** caja; **nota:** puede ser financiación; si se reclasifica en v1.1, documentar |
| IVA débito fiscal | `iva_debito_fiscal` | Aumento pasivo → típicamente **entra** caja diferida |
| ISR por pagar | `isr_por_pagar` | Aumento → **entra** caja |
| AFP / ISSS / retenciones por pagar | `afp_por_pagar`, `isss_por_pagar`, `retenciones_isr_empleados` | Aumento → **entra** caja |
| Otras cuentas por pagar corrientes | `otras_cuentas_pagar_corrientes` | Aumento → **entra** caja |

**Total actividades operativas** = suma algebraica de las líneas anteriores según convención fijada en implementación (documentar en pie del estado: “importes entre paréntesis representan salida de efectivo” o usar columnas Entradas/Salidas).

---

## 7. Actividades de inversión

Variación del periodo (Δ inicio → fin) sobre líneas de **activo no corriente** (excepto la porción ya tratada como no efectivo si se duplicara — en v1 **no** duplicar depreciación: ya va en ajustes operativos):

| Etiqueta sugerida | Clave balance |
|-------------------|---------------|
| Propiedad, planta y equipo (neto de componentes) | `propiedad_planta_equipo` (y coherencia con `depreciacion_acumulada` si se muestra línea neta PPE) |
| Activos intangibles | `activos_intangibles` |
| Inversiones a largo plazo | `inversiones_largo_plazo` |
| Activos por impuesto diferido | `activos_impuesto_diferido` |
| Otros activos no corrientes | `otros_activos_no_corrientes` |

**Convención v1:** aumento neto de estos activos (compra de activos) → **salida** de efectivo en inversión (signo negativo en columna única, según diseño).

---

## 8. Actividades de financiación

Variación Δ inicio → fin:

| Etiqueta sugerida | Clave balance |
|-------------------|---------------|
| Préstamos largo plazo | `prestamos_largo_plazo` |
| Provisión indemnizaciones | `provision_indemnizaciones` |
| Pasivos por impuesto diferido | `pasivos_impuesto_diferido` |
| Otros pasivos no corrientes | `otros_pasivos_no_corrientes` |
| Capital social | `capital_social` |
| Reserva legal | `reserva_legal` |
| Utilidades retenidas | `utilidades_retenidas` |
| Utilidad (pérdida) del ejercicio (cuenta patrimonial) | `utilidad_ejercicio` |
| Superávit por revaluación | `superavit_revaluacion` |

**Convención v1:** aumento de deuda financiera y de aportes de capital → **entrada** de efectivo; dividendos / reducciones de capital → **salida** (según signo real de Δ).

**Nota:** Movimientos de `prestamos_corto_plazo` pueden discutirse entre operación y financiación; **v1:** mantener como en tabla sección 6; si se mueve a financiación, actualizar este documento y los tests.

---

## 9. Conciliación de efectivo (parte “híbrida” del v1)

### 9.1 Variación de la línea balance `efectivo_equivalentes`

`Δ_efectivo = saldo_fin(efectivo_equivalentes) − saldo_inicio(efectivo_equivalentes)`  
(calculado con la misma clasificación de cuentas que el balance NIIF: caja, bancos, equivalentes.)

### 9.2 “Puente” desde el total del flujo indirecto

Calcular:

`Flujo_neto_indirecto = Operación + Inversión + Financiación`  
(definido con la misma convención de signos que el total que debe igualar Δ_efectivo).

Presentar en anexo corto:

| Concepto | Importe |
|----------|---------|
| Variación neta de efectivo según estado (indirecto + inversiones + financiación) | … |
| Variación neta de efectivo según balance (Δ efectivo y equivalentes) | … |
| Diferencia (descuadre) | … |

Si `|diferencia| > tolerancia` (p. ej. 0,02 USD o 1 % del mayor de los dos, el que defina implementación): mostrar **advertencia** “Revise partidas, saldos iniciales y cuentas clasificadas en Otros…”.

### 9.3 Nota opcional (v1 si coste bajo)

Suma de `(debe − haber)` o movimiento neto **en el periodo** solo en cuentas hoja clasificadas en `efectivo_equivalentes` (movimiento bruto de caja) como **información complementaria**, sin sustituir el método indirecto.

---

## 10. Orden sugerido de presentación en PDF/Excel

1. Encabezado: empresa, NIT si aplica, periodo actual, moneda USD; si `comparar=1`, indicar también rango del **periodo anterior**.  
2. Título: **“Estado de flujos de efectivo (enfoque indirecto con conciliación de efectivo)”**.  
3. **Actividades operativas**  
   - Utilidad neta del ejercicio (estimada) — ref. ER  
   - Ajustes: depreciación y amortización operativas  
   - Variaciones de capital de trabajo (tabla sección 6)  
   - **Flujo neto de efectivo de actividades operativas**  
   - Con comparativa: cada línea anterior con columnas **Actual | Anterior | Diferencia** (y % opcional).  
4. **Actividades de inversión** (sección 7) → subtotal inversión (misma regla de columnas si comparativa).  
5. **Actividades de financiación** (sección 8) → subtotal financiación (idem).  
6. **Incremento (disminución) neto de efectivo y equivalentes** (idem).  
7. Efectivo al inicio; Efectivo al fin (desde línea balance) — en comparativa, mostrar ambos periodos **o** efectivo inicio/fin del corte de cada periodo según diseño acordado en implementación (recomendación: **dos filas** “Efectivo al inicio del periodo” / “Efectivo al fin del periodo” con columnas Actual | Anterior para lectura clara).  
8. **Anexo de conciliación** (sección 9) — preferible **replicar** el bloque de conciliación para cada periodo cuando `comparar=1`.  
9. Notas: metodología, dependencia del catálogo, estimaciones del ER, definición del periodo comparativo (sección 3.3), exclusiones v1.

---

## 11. Comportamiento ante datos incompletos

| Situación | Comportamiento |
|-----------|----------------|
| Sin `SaldoMensual` previo | Calcular inicio con `saldo_inicial` de catálogo + movimientos hasta víspera; si no hay, marcar líneas iniciales como “N/D” y mostrar advertencia. |
| Cuentas sin `rubro` o mal clasificadas | Van a `otros_*` en balance; el EFE hereda el mismo sesgo; leyenda en reporte. |
| Periodo de un solo día | Válido; Δ puede ser cero en muchas líneas. |
| Ecuación balance no cuadra (`ecuacion_cuadra` falso) | El EFE se genera igual; mostrar advertencia “Balance no cuadrado en el sistema; interprete con precaución.” |
| `comparar=1` con periodo actual de N días | El periodo anterior debe tener exactamente **N** días y coincidir con `EstadoResultadosNiifSvPresenter::periodoAnterior` (termina el día anterior a `fecha_inicio`). |
| Periodo anterior sin partidas | Mostrar ceros o “—” según convención de UI; no fallar la generación del PDF/Excel. |

---

## 12. Criterios de aceptación (Fase 1–2, para trazabilidad)

- Mismos `fecha_inicio`, `fecha_fin` y empresa que al generar balance y ER.  
- La línea “Utilidad neta…” coincide numéricamente con `LBL_UTIL_NETA` del `EstadoResultadosNiifSvPresenter` para ese rango.  
- `Δ_efectivo` del anexo coincide con la diferencia de saldos `efectivo_equivalentes` inicio vs fin según la misma clasificación que el balance.  
- Con **`comparar=1`**: el rango del periodo anterior coincide con el calculado por `EstadoResultadosNiifSvPresenter::periodoAnterior` para los mismos `fecha_inicio` y `fecha_fin`.  
- Con **`comparar=1`**: la utilidad neta de la columna “Anterior” coincide con `LBL_UTIL_NETA` del ER generado para ese rango anterior.  
- Tests unitarios con dataset mínimo: (1) solo operación, (2) compra de activo fijo, (3) préstamo nuevo, (4) depreciación en gasto; **(5)** con `comparar=1`, verificar fechas del periodo anterior y al menos un subtotal (p. ej. flujo operativo) distinto entre periodos en un fixture preparado a propósito.

---

## Anexo A — Claves internas de línea (balance NIIF SV)

Lista alineada con `BalanceGeneralNiifSvPresenter::emptyLineKeys()`:

`efectivo_equivalentes`, `cuentas_cobrar_clientes`, `documentos_cobrar`, `provision_incobrables`, `inventarios`, `iva_credito_fiscal`, `pago_cuenta_acumulado`, `gastos_anticipados`, `otros_activos_corrientes`, `propiedad_planta_equipo`, `depreciacion_acumulada`, `activos_intangibles`, `inversiones_largo_plazo`, `activos_impuesto_diferido`, `otros_activos_no_corrientes`, `cuentas_pagar_proveedores`, `prestamos_corto_plazo`, `iva_debito_fiscal`, `isr_por_pagar`, `afp_por_pagar`, `isss_por_pagar`, `retenciones_isr_empleados`, `otras_cuentas_pagar_corrientes`, `prestamos_largo_plazo`, `provision_indemnizaciones`, `pasivos_impuesto_diferido`, `otros_pasivos_no_corrientes`, `capital_social`, `reserva_legal`, `utilidades_retenidas`, `utilidad_ejercicio`, `superavit_revaluacion`.

---

## Historial de documento

| Versión | Fecha | Cambio |
|---------|-------|--------|
| 1.0 | 2026-05-04 | Creación Fase 0 — listo para implementación tras aprobación explícita |
| 1.1 | 2026-05-04 | Inclusión de comparativa con periodo anterior (`comparar=1`, misma duración en días que el ER); actualización secciones 2, 3.3, 10–12. |
