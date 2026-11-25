# Análisis de datos — Modelado (Suite Financiera)

Documento de referencia para el backend. Cruza **lo que la UI ya pinta** (`Frontend/src/app/views/dashboard/sections/finanzas/modelado/`) con **lo que hay que guardar** y **lo que se calcula o se lee de otro módulo**.

**Estado frontend:** la pestaña lee `GET /api/finanzas/modelado` y guarda con el JWT de Laravel (clasificación, calendario, presupuesto y pronóstico). Sucursales, áreas, planillas y préstamos no tienen datos todavía.

**Estado backend:** Laravel guarda la configuración y Go arma la lectura.

- Escritura, con JWT, en `Backend`: `PUT /api/finanzas/modelado/clasificacion`, `POST|PUT|DELETE /api/finanzas/modelado/calendario`, `PUT /api/finanzas/modelado/presupuesto`, `PUT /api/finanzas/modelado/pronostico`. `GET /api/finanzas/modelado` devuelve solo lo guardado, sin cálculos. La migración es `2026_10_28_160000_create_modelado_tables.php`.
- Lectura de la pantalla: `GET /api/finanzas/modelado` en sp-analytics. Lee esas tablas en `DB_APP` (si no existe, `DB_PROD`) y el real en `fact_pl_gerencial_mensuales`. Calcula punto de equilibrio, % configurado, mes proyectado y ejecutado. Planillas, préstamos y días de cobro o pago no entran todavía.
- Flujo usa el efectivo, el colchón y la banda de `modelado_pronosticos` cuando la empresa los guardó. Si no hay fila, sigue con `flujo_caja_parametros` y −10 / +12.
- Cierre toma el presupuesto del mes desde el anual guardado. El ETL del P&L prefiere el rubro clasificado y deja la lista fija solo si ese gasto no está en el mapa. Eso se ve en el P&L cuando vuelve a correr el ETL.

En local, Laravel escribe en `sp_new`. Analytics usa `DB_APP=sp_new` para esas tablas y `DB_PROD` para el resto.

Modelado es la configuración. P&L, cierre, flujo y rentabilidad la leen. No se duplica aquí el real del mes: el real sigue saliendo de `fact_pl_gerencial_mensuales`.

---

## 1. Qué debe responder la pantalla

Cuatro pasos, más una barra de qué tan armado está el modelo.

| Bloque | Qué muestra | ¿Se escribe? |
|--------|-------------|--------------|
| Configurado | Un % global y cuatro partes: clasificación, calendario, presupuesto, antigüedad del efectivo | No. Sale de lo demás |
| Gasto sin calendario | Monto mensual que todavía no tiene día de pago | No. Es la suma de lo que falta en el paso 2 |
| Perfil fiscal | País, moneda, IVA, pago a cuenta, día de vencimiento, ventana de aguinaldo | Casi no. País y moneda son de la empresa. El resto, en El Salvador, es regla del país |
| Otros módulos | Planillas y préstamos: última conexión y cuatro líneas cada uno | No. Se leen. Esas filas del calendario van bloqueadas |
| Paso 1 | Cada gasto recurrente, su rubro del P&L, el monto mensual y fijo o variable | Sí: el tipo y el rubro. El monto y el nombre salen del gasto real |
| Punto de equilibrio | Fijos, variables, costo de ventas, ventas mínimas, margen de seguridad | No. Se calcula |
| Paso 2 | Calendario de salidas de caja: monto, frecuencia, día, próximo | Sí, las filas manuales. Planillas y préstamos no se editan |
| Paso 3 | Presupuesto anual de las siete líneas del P&L, el mes, lo ejecutado y la meta de ventas | Sí: el anual y cómo se reparte el año. Ejecutado y variaciones se leen |
| Paso 4 | Efectivo, colchón, costo de capital, banda de escenarios, e historial | Sí los cuatro primeros. El historial se lee |

Los títulos «Paso 1», «Paso 2»… los puede armar el frontend. No viajan como frase del API.

---

## 2. Qué hay que insertar

Una fila por empresa, salvo el presupuesto y la estacionalidad, que son por año.

| Dato | Quién lo escribe | Para qué lo usan las otras pestañas |
|------|------------------|--------------------------------------|
| Rubro P&L de cada gasto (`personal`, `local`, `ventas_marketing`, `admin`, `financieros`, `costo`) | Paso 1 | El ETL del P&L hoy usa una lista fija de `tipo_gasto`. Cuando exista este mapa, el P&L usa el mapa de la empresa y la lista fija solo si ese gasto no está clasificado |
| Tipo `fijo` o `variable`, o vacío si nadie lo marcó | Paso 1 | Punto de equilibrio. Cierre no debe inventar «gasto sin clasificar» |
| Regla de calendario: nombre, monto de caja, frecuencia (`quincenal`, `mensual`, `anual`), día, origen `manual` | Paso 2 | Flujo de caja arma las salidas de las 12 semanas. Cierre arma el checklist de «¿ya se registró esta salida?» |
| Presupuesto anual de las siete líneas y el modo de reparto (`estacionalidad` o `igual`) | Paso 3 | Cierre (columna presupuesto) y la comparación «vs presupuesto» del P&L. Hay que partir el anual en doce meses. No copiar el anual entero en cada mes, que es lo que hace hoy el ETL de `presupuestos` |
| Peso de cada mes (12 números) si el reparto es por estacionalidad | Paso 3 | El mes proyectado de la tabla y el presupuesto de ese mes en cierre y flujo |
| Efectivo de caja, su fecha y colchón | Paso 4 | Se guardan en `modelado_pronosticos`. Flujo los lee de ahí. Si no hay fila, usa `flujo_caja_parametros` |
| Costo de capital, % por mes | Paso 4 | Rentabilidad lo deja en 0 («costo de esperar el cobro»). No está en el fact |
| Banda de escenarios (el mock usa −10 y +12) | Paso 4 | Flujo hoy tiene esos dos números fijos. Pasan a ser los de esta pantalla |

El monto mensual del paso 1 no se guarda. Si se guarda, se desfasa del gasto real del último mes.

La vista Sucursales y la vista Áreas no tienen filas propias en el mock: los botones no cambian la tabla. En este corte el presupuesto es de empresa. Sucursal y área quedan fuera hasta que exista un presupuesto por sucursal o un catálogo de áreas. No se reparte el anual de la empresa entre sucursales a ojo.

Planillas y préstamos no se insertan en Modelado. Si el módulo no tiene datos, esa tarjeta y esas filas del calendario no aparecen.

---

## 3. Qué hay que obtener

| Dato | De dónde | Cómo |
|------|----------|------|
| Lista del paso 1 | Gastos reales de la empresa | Conceptos con movimiento reciente (`tipo_gasto` o categoría de gasto), con el monto del último mes cerrado. `nuevo` si todavía no hay rubro ni tipo guardados |
| Rubro sugerido | El `CASE` que ya usa el ETL del P&L | Solo como valor inicial. No pisa lo que la empresa ya guardó |
| Costo de ventas % y ventas promedio 3 meses | `fact_pl_gerencial_mensuales`, consolidado, sin IVA | Promedio de los tres meses cerrados anteriores. Ventas netas = brutas − devoluciones − descuentos |
| Ejecutado Ene–mes | La misma fact, suma de enero al último mes cerrado | El encabezado «Ene–Sep» sale del rango, no va escrito en la plantilla |
| Presupuesto del mes y acumulado | Lo insertado en el paso 3, ya partido | Ver fórmulas |
| Ventas del año anterior | La misma fact, año anterior | Para «vs. año anterior» de la meta |
| % de ventas de contado | `fact_ventas_netas_mensuales`, últimos 3 meses | `ventas_contado / (contado + crédito)`. Flujo ya lo calcula |
| Ritmo vs presupuesto, 3 meses | Ventas netas de 3 meses / presupuesto de ventas de esos 3 meses | Si no hay presupuesto partido, el ritmo va `null`. No se publica 99.6 |
| Días de cobro y de pago | No hay fecha real de cobro ni de pago en el warehouse | Van `null`. El mock usa 30 y 32 sin fuente |
| Planillas | Módulo de planillas en producción | Empleados, sucursales, última planilla, nómina neta, días, ISSS/AFP, aguinaldo. Si no hay planilla, la tarjeta no se inventa |
| Préstamos | Módulo de préstamos en producción | Préstamo activo, saldo, cuota, día, cuotas restantes, interés del mes (va al P&L) y capital (solo caja) |
| Moneda y país | Empresa | IVA 13 %, pago a cuenta 1.75 % y aguinaldo entre el 12 y el 20 de diciembre son el perfil de El Salvador, no un hecho por empresa. El vencimiento del mock dice «día hábil 10»; el cumplimiento del contador usa el día 14 del mes siguiente. Hasta unificar esa regla, el perfil muestra la del contador y no se guarda otro día |

El punto de equilibrio no se persiste. Con la clasificación guardada y las ventas de tres meses:

```
costos fijos          = suma de montos del paso 1 con tipo fijo
gastos variables %    = suma de los variables / ventas promedio 3 meses
margen de contribución = 100 − gastos variables % − costo de ventas %
ventas mínimas        = costos fijos / (margen de contribución / 100)
margen de seguridad   = (ventas promedio − ventas mínimas) / ventas promedio
sin clasificar        = suma de los que no tienen tipo
```

Si las ventas promedio son 0, los porcentajes van `null` y las ventas mínimas también.

En el mock, los fijos suman 9,485 y los variables son el 3.2 % de 39,523. El 63.6 % de costo de ventas no está en la lista del paso 1: sale del P&L. El «sin clasificar» de la tarjeta naranja ya lo calcula la pantalla sumando lo que no tiene tipo. El 300 del mock no hace falta mandarlo aparte.

El % configurado tampoco se guarda:

| Parte | Cuenta |
|-------|--------|
| Clasificación | Gastos con tipo / gastos de la lista |
| Calendario | Gastos fijos que ya tienen regla / gastos fijos. 100 si no hay fijos |
| Presupuesto | Líneas con anual distinto de 0 / 7 |
| Efectivo | 100 si hay fecha de efectivo, si no 0 |
| Global | Promedio de esas cuatro. En el mock da 98: (93 + 100 + 100 + 100) / 4 |

«Gasto sin calendario» es la suma mensual de los fijos (y de los nuevos sin tipo) que no están en el paso 2.

El mes proyectado de cada línea:

```
igual cada mes     = anual / 12
estacionalidad     = anual × peso del mes / suma de los 12 pesos
```

Las barras del mock no producen la columna de octubre (ventas 42,120 no salen de repartir 520,000 con esos pesos). El API calcula el mes. No se copian los montos del mock.

Lo ejecutado contra presupuesto, de enero al último mes cerrado:

```
vs % = (ejecutado − presupuesto acumulado) / |presupuesto acumulado|
```

Sin presupuesto acumulado, el % va `null`.

Utilidad operativa presupuestada = ventas netas anuales − costo − los cinco rubros de gasto. El % es esa utilidad / ventas netas. También es calculada.

La meta de ventas es el anual de la fila Ventas netas. El input suelto del mock no es otro dato. Avance = ejecutado de ventas / meta. Avance esperado = parte del año ya transcurrida (meses cerrados / 12, o la suma de pesos de esos meses si el reparto es estacional). Vs. año anterior = meta / ventas del año anterior − 1.

«Próximo» del calendario es la siguiente fecha a partir de hoy según frecuencia y día. El API manda la fecha. El frontend arma «15 oct». En el componente, al agregar una fila, el próximo queda escrito como «día nov»: eso no se replica.

---

## 4. Qué ya existe y no alcanza

| Necesidad | Qué hay | Hueco |
|-----------|---------|--------|
| Rubro del gasto | `CASE` de `tipo_gasto` en `etl/pl_gerencial.go` | Es de todo el producto, no de la empresa. No distingue fijo de variable. «Gastos varios» cae en otros, no en administrativos |
| Presupuesto | Tabla `presupuestos` (columnas sueltas: planilla, alquiler, marketing…) y `fact_presupuestos_mensuales` (solo ingresos y egresos) | El fact pega el monto del rango en cada mes. Las columnas no son las siete líneas del P&L |
| Efectivo y colchón | `modelado_pronosticos` | La pantalla de Modelado los escribe. Flujo los lee antes que `flujo_caja_parametros` |
| Salidas con fecha | `flujo_caja_salidas_fijas` | Es una fecha concreta, no una regla mensual. El calendario de Modelado es la regla; las fechas de las 12 semanas se generan al leer flujo |
| Ventas de contado | `fact_ventas_netas_mensuales.ventas_contado` | Sirve para el historial del paso 4 |
| Días de cobro y de pago | CXC y CXP tienen vencimiento pactado, no fecha de pago real | El ciclo de flujo ya va `null` por esto. Modelado no lo rellena con 30 y 32 |
| Planillas y préstamos | Existen en producción (planillas, aguinaldos, préstamos) | No están en el warehouse de finanzas. Leerlos es una consulta a producción, no un hecho mensual |

---

## 5. API propuesta

Lectura de todo el expediente. Escritura solo de lo que la persona cambia. El punto de equilibrio, el % configurado, el ejecutado y el historial no se mandan de vuelta para guardarlos.

```http
GET /api/finanzas/modelado?empresa=&anio=
```

Header: `Authorization: Bearer {API_SECRET}`

```json
{
  "anio": 2026,
  "configurado": {
    "globalPct": 0,
    "clasificacionPct": 0,
    "calendarioPct": 0,
    "presupuestoPct": 0,
    "efectivoDias": null
  },
  "gastoSinCalendarioMensual": 0,
  "perfilFiscal": {
    "pais": "",
    "moneda": "",
    "ivaPct": 13,
    "pagoCuentaPct": 1.75,
    "vencimiento": "",
    "aguinaldoRango": "12-20 dic"
  },
  "modulos": [],
  "gastos": [],
  "puntoEquilibrio": {
    "costosFijosMes": 0,
    "gastosVariablesPctVentas": null,
    "costoVentasPctVentas": null,
    "margenContribucionPct": null,
    "ventasMinimasMes": null,
    "ventasPromedio3Meses": null,
    "margenSeguridadPct": null,
    "sinClasificarMonto": 0
  },
  "calendario": [],
  "calendarioPendiente": null,
  "presupuesto": {
    "mesColumna": "",
    "reparto": "igual",
    "estacionalidad": [],
    "filas": [],
    "utilidadOperativa": { "monto": 0, "pctVentas": null },
    "metaVentas": {
      "montoAnual": 0,
      "vsAnioAnteriorPct": null,
      "avancePct": null,
      "avanceEsperadoPct": null
    },
    "ejecutadoDesde": "",
    "ejecutadoHasta": ""
  },
  "pronostico": {
    "efectivo": null,
    "efectivoFecha": "",
    "colchon": null,
    "costoCapitalPctMes": null,
    "escenarioConservadorPct": -10,
    "escenarioOptimistaPct": 12,
    "historial": {
      "ventasContadoPct": null,
      "diasCobro": null,
      "diasPago": null,
      "ritmoVsPresupuesto3mPct": null
    }
  }
}
```

`gastos[]`: `id`, `nombre`, `rubro`, `montoMensual`, `tipo` (`fijo` | `variable` | null), `nuevo`.

`calendario[]`: `id`, `nombre`, `detalle`, `monto`, `frecuencia`, `dia`, `proximo` (fecha), `origen` (`manual` | `planillas` | `prestamos` o null), `editable`.

`presupuesto.filas[]`: `id` de la línea (`ventas`, `costo`, `personal`, `local`, `marketing`, `admin`, `financieros`), `anual`, `mesProyectado`, `ejecutado`, `presupuestoAcumulado`, `vsPct` (null si no hay presupuesto).

`modulos[]` vacío si planillas o préstamos no tienen datos. Cada línea lleva `etiqueta` y `valor` ya armados para esa tarjeta, o `etiqueta` más `monto` y el frontend formatea. Mejor monto numérico y el texto de la etiqueta, para no depender del formato del mock.

Escrituras, cada una con `empresa`:

| Acción | Qué persiste |
|--------|----------------|
| `PUT` clasificación | rubro y tipo de un gasto |
| `PUT` calendario | alta o cambio de una regla manual. No acepta cambiar una fila de planillas o préstamos |
| `DELETE` calendario | solo reglas manuales |
| `PUT` presupuesto | anuales, reparto y los 12 pesos. Al guardar, parte el año en meses para que cierre y flujo lean un monto por mes y por línea |
| `PUT` pronóstico | efectivo, fecha, colchón, costo de capital y los dos escenarios |

Listas vacías en `[]`. Porcentajes que no se pueden calcular en `null`, no en 0. Montos a dos decimales.

### Orden

1. Paso 1: lista real de gastos y guardado de rubro y tipo. El P&L sigue con la lista fija hasta que el mapa tenga filas; a partir de ahí, el mapa gana.
2. Paso 3: anual, reparto y partida mensual. Cierre deja de mostrar presupuesto 0 en cuanto exista esta partida. El fact viejo de ingresos y egresos no se usa para estas siete líneas.
3. Paso 4: escribir `flujo_caja_parametros` y la banda. Flujo deja de usar −10 y +12 fijos cuando la empresa guardó otros. El historial de contado ya se puede leer. Días de cobro y de pago siguen en blanco.
4. Paso 2: reglas manuales y, si la consulta a producción es barata, filas bloqueadas de planillas y préstamos. Si esa consulta no está, el calendario manual igual funciona y las tarjetas de módulos van vacías.
5. La barra «configurado» y el punto de equilibrio se calculan en el `GET`. No tienen tabla.

Sucursales, áreas y un segundo perfil de IVA por empresa quedan fuera de este corte.
