# Modelado

Modelado es la configuración de la suite financiera. No es un informe. Aquí la empresa dice qué gasto es fijo o variable, qué día sale de caja, cuál es el presupuesto del año y con qué efectivo se proyecta.

P&L, cierre y flujo leen esa configuración. El punto de equilibrio y el porcentaje «configurado» no se guardan: se calculan al leer.

## Quién hace qué

| Capa | Rol |
|------|-----|
| Angular | Pinta la pestaña y dispara los guardados |
| Laravel | Escribe las tablas `modelado_*` con el JWT. La empresa sale del token, no de un query param |
| Go (sp-analytics) | Arma la pantalla. Lee `modelado_*` y los hechos del warehouse, y calcula equilibrio, % configurado, presupuesto del mes y ejecutado |
| ETL de Go | Al reconstruir el P&L, usa el rubro guardado en `modelado_clasificaciones` |

La pantalla **no** usa el `GET` de Laravel. Ese endpoint solo devuelve lo guardado, sin cálculos. El `GET` que ve el navegador es el de Go.

```text
Angular
  |-- GET  goApiUrl/api/finanzas/modelado?empresa=&anio=     --> Go
  |-- PUT/POST/DELETE  API_URL/api/finanzas/modelado/...     --> Laravel
  v
Laravel escribe modelado_*  en la BD de la app (local: sp_new)
Go lee esas mismas tablas   con DB_APP (si falta, cae en DB_PROD)
Go lee el real              en DB_ANALYTICS (fact_pl_gerencial_mensuales, fact_gastos_mensuales)
```

En local las dos escrituras y la lectura de configuración tienen que apuntar a la misma base. Laravel usa `DB_DATABASE`. Analytics usa `DB_APP`. Si no coinciden, el guardado responde ok y la pantalla no muestra el cambio.

`GET /api/finanzas/modelado` de Go no se cachea. El resto de analytics sí, unos minutos. Modelado cambia en cuanto el usuario guarda.

## Frontend

| Archivo | Función |
|---------|---------|
| `Frontend/src/app/views/dashboard/sections/finanzas/modelado/` | Pantalla: lista, calendario, presupuesto, pronóstico |
| `Frontend/src/app/views/dashboard/services/finanzas-modelado-data.service.ts` | `obtener()` va a Go. El resto va a Laravel |

`obtener(anio)` llama a Go con la empresa del usuario logueado. Después de cada guardado, la pantalla vuelve a llamar a `obtener()` para repintar con los cálculos nuevos.

## Laravel

Rutas en `Backend/routes/modulos/finanzas/modelado.php`. Controlador: `App\Http\Controllers\Api\Finanzas\ModeladoController`. Todas piden JWT. `empresaId()` es `id_empresa` del usuario del token.

| Método | Ruta | Qué persiste |
|--------|------|----------------|
| GET | `finanzas/modelado` | Nada. Devuelve clasificaciones, calendario, presupuesto y pronóstico crudos. La pantalla no lo usa |
| PUT | `finanzas/modelado/clasificacion` | Rubro, tipo (`fijo` / `variable` / null) y monto de un gasto. Clave única por empresa |
| DELETE | `finanzas/modelado/clasificacion` | Borra una clasificación por `clave` |
| POST | `finanzas/modelado/calendario` | Alta de una regla manual (`origen = manual`) |
| PUT | `finanzas/modelado/calendario/{id}` | Cambia monto, frecuencia y día. Solo si `origen` es `manual` |
| DELETE | `finanzas/modelado/calendario/{id}` | Borra la regla. Solo si `origen` es `manual` |
| PUT | `finanzas/modelado/presupuesto` | Anual por rubro, reparto `igual` o `estacionalidad`, y 12 pesos si el reparto es estacional |
| PUT | `finanzas/modelado/pronostico` | Efectivo, fecha, colchón, costo de capital y la banda de escenarios |

El `DELETE` del calendario en Angular tiene que ir con barra antes del id (`finanzas/modelado/calendario/` + id). El helper HTTP concatena url e id.

## Go

Ruta: `GET /api/finanzas/modelado?empresa=&anio=`. Handler: `finanzasHandler.Modelado` en `sp-analytics/api/modelado.go`. La empresa viene en el query, no en el JWT de Laravel. Tiene que ser la misma empresa del token, si no la pantalla lee otra configuración.

Lee de `DB_APP`:

| Tabla | Uso |
|-------|-----|
| `modelado_clasificaciones` | Paso 1. Clave, nombre, rubro, tipo, monto |
| `modelado_calendario` | Paso 2 |
| `modelado_presupuesto_repartos` | Cómo se parte el año |
| `modelado_presupuesto_lineas` | Anual por rubro |
| `modelado_estacionalidad` | Peso de cada mes |
| `modelado_pronosticos` | Paso 4 |
| `empresas` | País y moneda del perfil fiscal |

Lee de `DB_ANALYTICS`:

| Tabla | Uso |
|-------|-----|
| `fact_gastos_mensuales` | Monto del mes anterior por `tipo_gasto`, solo si la empresa no guardó monto para esa clave |
| `fact_pl_gerencial_mensuales` | Ventas y costo para el equilibrio, y el ejecutado del presupuesto. Consolidado (`id_sucursal = 0`) |

Otros consumidores, fuera de esta pantalla:

| Quién | Qué toma |
|-------|----------|
| ETL `pl_gerencial.go` | El rubro de `modelado_clasificaciones` al armar el P&L. Si el gasto no está clasificado, sigue el `CASE` fijo de `tipo_gasto` |
| `cierre_mes.go` | El presupuesto del mes, partido desde el anual |
| `flujo_caja.go` | Efectivo, colchón y banda. Si no hay pronóstico, usa `flujo_caja_parametros` y −10 / +12 |

## Qué configura cada paso

**Paso 1. Fijo o variable.** Catálogo fijo (Planilla, alquiler, energía, etc.) más lo que la empresa agrega (`clave` con prefijo `m:`). El monto guardado gana. El del mes anterior solo rellena la fila si todavía no hay monto guardado. El tipo alimenta el equilibrio y el % de clasificación.

**Paso 2. Calendario.** Día y frecuencia de salida de caja. Solo se borran o editan reglas `manual`. Planillas y préstamos, cuando existan, vendrán bloqueados. Hoy esas tarjetas salen vacías.

**Paso 3. Presupuesto.** Anual de las siete líneas del P&L (ventas, costo, personal, local, marketing, admin, financieros) y cómo se reparte el año. Go parte el anual en el mes que muestra la columna.

**Paso 4. Pronóstico.** Efectivo de hoy, colchón, costo de capital y los dos escenarios. El historial (contado, días de cobro y pago, ritmo) se lee; no se escribe en este paso.

## Qué calcula Go y no se guarda

| Dato | Cómo |
|------|------|
| Costos fijos / mes | Suma de filas del paso 1 con tipo `fijo` |
| Gastos variables % | Suma de las variables / ventas de los últimos 3 meses cerrados |
| Costo de ventas % | Sale del P&L, no de la lista del paso 1 |
| Ventas mínimas | Fijos / margen de contribución |
| Sin clasificar | Suma de filas sin tipo |
| % clasificación | Filas con tipo / filas de la lista. El catálogo entero cuenta |
| % calendario | Fijos que ya tienen regla / fijos |
| % presupuesto | Líneas con anual distinto de 0 / 7 |
| % efectivo | 100 si hay fecha de efectivo, si no 0 |
| % global | Promedio de esos cuatro |
| Gasto sin calendario | Suma mensual de fijos y de filas sin tipo que no están en el paso 2 |

## Local

1. Laravel: `DB_DATABASE` de la app (aquí `sp_new`).
2. Analytics: `DB_APP` con el mismo nombre, en el `.env` de `sp-analytics`.
3. Reiniciar `go run . api` después de cambiar Go o `DB_APP`. El proceso solo lee el `.env` al arrancar, y lo busca en el directorio desde el que se lanza.
)
