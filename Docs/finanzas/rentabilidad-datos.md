# Análisis de datos — Rentabilidad (Suite Financiera)

Documento de referencia para el backend en `sp-analytics`. Cruza **lo que la UI ya pinta** (`Frontend/src/app/views/dashboard/sections/finanzas/rentabilidad/`) con **los hechos que el warehouse ya tiene**.

**Estado frontend:** la pestaña llama `GET /api/finanzas/rentabilidad`. No hay mock ni fallback. Mes / trimestre / año se piden al API; la pantalla no multiplica el mes.

**Estado backend:** `GET /api/finanzas/rentabilidad` en sp-analytics. Hechos nuevos: `fact_compras_producto_mensuales` y `fact_proyectos` / `fact_proyecto_mensual` (script `EJECUCION FINAL/11_rentabilidad.sql`, paso ETL `rentabilidad`). Sucursal, producto y cliente se agregan de los hechos de ventas. El frontend de la pestaña sigue en mock.

Modelado (rubro configurable, gasto fijo/variable, presupuesto por área) queda fuera de este corte.

---

## 1. Qué debe responder la pantalla

Una sola carga por filtros. El usuario elige horizonte y dimensión. Cada dimensión es una tabla. Al tocar una fila se abre el detalle de cómo se armó ese margen. Los cinco KPIs de arriba los arma el frontend con la fila abierta (`rentabilidad-kpis.builder.ts`). El API manda números y tonos, no frases.

| Dimensión | Tabla | Detalle |
|-----------|--------|---------|
| Sucursal | Ventas, gastos, utilidad, margen, tendencia, CXC, CXP, vs año anterior | Puente, productos, clientes, devoluciones, vencidos |
| Producto | Puesto, unidades, ventas, utilidad, margen, inventario, costo y precio vs 6 meses | Serie precio/costo, sucursales, clientes, descuento |
| Cliente | Compró, ganancia, de cada $100, al final quedan, saldo, días de pago, última compra | Vs el resto de clientes, facturas, productos, sucursales |
| Proveedor | Compró, % de compras, ganó al vender, gana por $1, precio a 6 meses, días de pago | Compras vs ventas de sus productos, CXP, clientes |
| Proyecto | Contrato, % facturado, % presupuesto, margen planeado vs a la fecha, por cobrar, dinero en obra | Avance, resultado, costo por categoría, materiales |

Contrato TypeScript: `rentabilidad.model.ts` (`RentabilidadMock` y las filas por dimensión).

---

## 2. Filtros

| Parámetro | Valores | Efecto |
|-----------|---------|--------|
| `empresa` | id | Obligatorio |
| `anio`, `mes` | corte | Mes de referencia. Mismo criterio que el P&L: año completo hasta el mes en curso |
| `horizonte` | `mes` \| `trimestre` \| `anio` | `mes` = ese mes. `trimestre` = tres meses hasta el corte. `anio` = enero hasta el corte |
| `sucursal` | vacío = consolidado, o id | Igual que el P&L (`id_sucursal = 0` en consolidado solo si el hecho lo trae; si no, sumar sucursales) |

Base de montos: **sin IVA**, alineada al P&L gerencial y a `fact_ventas_productos_mensuales` (`ventas_sin_iva`, `costo_total`).

Comparaciones:

| Campo UI | Ventana |
|----------|---------|
| `ventasVsAnioPct`, `ventasVsPct` | Mismo horizonte del año anterior |
| Costo y precio «vs 6 meses» | Promedio del costo/precio de hace 6 meses contra el del periodo |
| Tendencia | Hasta 9 puntos mensuales hasta el corte (margen o ventas, según la fila) |

Si la base de la comparación es 0, el porcentaje va `null`. No se devuelve 0.

---

## 3. Fórmulas

Utilidad y margen, en producto, cliente y proveedor, son **margen bruto de lo vendido**:

```
utilidad = ventas_sin_iva − costo_vendido
margen   = utilidad / ventas_sin_iva
de cada $100 = margen × 100
```

`costo_vendido` es `costo_total` de `fact_ventas_productos_mensuales` (el mismo costo que el P&L usa como costo de ventas de producto, sin sumar compras otra vez).

### 3.1 Sucursal

| Campo | Cálculo |
|-------|---------|
| `ventas` | Suma de ventas netas sin IVA de la sucursal en el horizonte |
| `gastos` | Egresos pagados/confirmados de la sucursal en el horizonte (sin compras de mercadería, para no duplicar el costo que ya va en el margen del producto) |
| `utilidad` | `ventas − costo_vendido − gastos` |
| `margenPct` | `utilidad / ventas × 100` |
| `ventasVsAnioPct` | Variación de ventas vs el mismo horizonte del año anterior |
| `cxc`, `cxp` | Saldo pendiente de la sucursal a la fecha de corte (no se escala por horizonte: es un saldo, no un flujo) |
| `cxcAlerta` | Hay vencido (`cxc` con más de 30 días, o el bucket que ya usa el dashboard de cuentas) |
| `detalle.puente` | Ventas → (−) costo → (−) gastos → utilidad, con % sobre ventas |
| `detalle.productos` | Top productos de esa sucursal: ventas, % de las ventas de la sucursal, margen, margen de la empresa |
| `detalle.clientes` | Top clientes de esa sucursal |
| `detalle.devoluciones` | Notas de crédito / devoluciones del periodo, total y desglose |

El KPI de la UI dice «margen bruto» y a la vez resta gastos. En esta fila el margen **sí resta gastos**. En producto y cliente el margen **no** reparte gastos de sucursal: solo costo de lo vendido. Hay que mantener esa diferencia. No prorratear alquiler o planilla sobre cada producto.

### 3.2 Producto

| Campo | Cálculo |
|-------|---------|
| `puesto` | Orden por ventas del periodo, 1 = más vendido |
| `unidades` | `cantidad_vendida` |
| `ventas`, `utilidad`, `margenPct` | Fórmulas de la sección 3 |
| `margenBajo` | Margen del producto por debajo del margen de la empresa en el mismo periodo |
| `margenAnioPct` | Margen del producto en el año hasta el corte |
| `inventario` | `dias` de stock, `agota` si los días de cobertura son bajos, o `sinVenta` (días sin venta y monto inmovilizado) desde el hecho de inventario |
| `precioVsPct`, `costoVsPct` | Precio y costo unitario del periodo vs hace 6 meses |
| `costoAlerta` | El costo subió y el precio no lo acompañó |
| `detalle.clientes` | Quién lo compró: unidades, precio, % descuento, margen |
| `detalle.sucursales` | Ventas del producto por sucursal |
| `detalle.precioSerie`, `costoSerie` | Serie mensual |

`tono`: `riesgo` si margen bajo o costo en alerta; `muerta` si `sinVenta`; `activa` es la fila abierta (la pone el frontend); el resto `normal`.

### 3.3 Cliente

| Campo | Cálculo |
|-------|---------|
| `compro` | Ventas sin IVA al cliente |
| `ganancia` | Utilidad bruta de esas ventas |
| `deCada100` | Ganancia por cada 100 de venta |
| `deCada100Bajo` | Por debajo del promedio de la empresa |
| `alFinalQuedan` | Lo que queda por cada 100 después de esperar el cobro. Si no hay dato de costo financiero, es la misma ganancia bruta y el campo de «esperar el pago» en el detalle va en 0 |
| `leDebe` | Saldo CXC. `null` y `pagoContado = true` si no maneja crédito |
| `pagoDias`, `pagoPactado`, `pagoLento` | Días reales de cobro vs plazo pactado. `pagoLento` si cobra más lento que el plazo |
| `ultimaCompra`, `ultimaCompraAlerta` | Fecha de la última venta. Alerta si supera el umbral de inactividad (el mock usa ~45 días; dejar la regla en un solo sitio del API) |
| `parteVentasPct` | Ventas del cliente / ventas de la empresa |
| `detalle.comparacionEste` / `comparacionTodos` | De cada 100: mercadería (costo), esperar el pago, lo que queda. «Todos» es el promedio de la empresa |
| `detalle.facturas` | Últimas facturas del periodo: código, fecha, monto. `nota` solo si hay un dato (descuento, vencida), no una frase inventada |
| `detalle.productos` | Qué le vendió y su de-cada-100 vs el normal de ese producto |

### 3.4 Proveedor

| Campo | Cálculo |
|-------|---------|
| `compro` | Compras pagadas/confirmadas al proveedor en el horizonte |
| `parteComprasPct` | Compras al proveedor / compras de la empresa |
| `dependenciaAlta` | Parte de compras por encima de un umbral fijo (el mock marca ~39%; usar 30% hasta que Modelado lo configure) |
| `ganoAlVender` | Utilidad de las ventas de productos comprados a ese proveedor |
| `ganaPorDolar` | `ganoAlVender / compro` |
| `precio6mPct` | Variación del costo de compra vs hace 6 meses |
| `diasPago` | Días promedio para pagarle, desde CXP |
| `detalle` | Serie compras / ventas / margen, CXP por vencer, clientes a los que se les vendió su producto, producto principal |

`otraCotizacionMonto`, `otraCotizacionFuente` y `otraCotizacionVsPct` **no tienen fuente**. Van vacíos. No se inventa un proveedor alterno.

### 3.5 Proyecto

| Campo | Cálculo |
|-------|---------|
| `contrato` | Monto del proyecto o del presupuesto de obra |
| `facturadoPct` | Facturado (ventas con `id_proyecto`) / contrato |
| `presupuestoGastadoPct` | Gastado (gastos y compras con `id_proyecto`) / presupuesto |
| `margenPlaneadoPct` | Margen del presupuesto |
| `margenFechaPct` | (facturado − gastado) / facturado |
| `margenFechaBajo` | Margen a la fecha menor que el planeado |
| `porCobrar` | Facturado no cobrado del proyecto |
| `dineroEnObra` | Pagado a proveedores/gastos de la obra − cobrado. `null` si no se puede cuadrar |
| `detalle.materiales` | Cotizado vs comprado (cantidad y diferencia de precio) **solo si el presupuesto trae cantidad y precio**. Si no, lista vacía |
| `detalle.resultado` | Planeado vs a la fecha: contrato, costo, utilidad, margen |

El horizonte no multiplica el contrato ni el presupuesto. Son saldos de la obra. Solo facturado, gastado y cobrado del periodo respetan el horizonte; el contrato es el total del proyecto.

---

## 4. Tonos

Reglas fijas, las mismas que ya usa el builder del frontend:

| Señal | Regla |
|-------|--------|
| Margen | Menor que 0 → rojo. Menor que 15% → naranja. Resto → azul |
| Gastos / ventas (sucursal) | Mayor que 90% → rojo. Mayor que 80% → naranja |
| Margen bajo (producto o cliente) | Por debajo del margen de la empresa |
| CXC | Vencido → alerta |
| Proveedor, parte de compras | ≥ 30% → dependencia alta |
| Precio del proveedor a 6 meses | Subida ≥ 8% → rojo. Subida > 0 → naranja |
| Días de pago al proveedor | Menor que el promedio de los demás → naranja (paga más rápido de lo que cobra, como en el mock) |

El frontend sigue armando el texto de la tarjeta («n.º 3 en ventas», «paga al contado») a partir de estos números. El API no genera esas frases.

---

## 5. Fuentes que ya existen

| Necesidad | Hecho o vista | Alcance |
|-----------|----------------|---------|
| Ventas, unidades, descuento, costo, sucursal, producto, cliente | `fact_ventas_productos_mensuales` | Sucursal, producto, cliente |
| Ventas netas por sucursal | `fact_ventas_netas_mensuales` | Sucursal |
| Devoluciones | `fact_devoluciones_mensuales` | Sucursal |
| Gastos por sucursal y proveedor | `fact_gastos_mensuales` | Sucursal, proveedor (compras = `fuente = compra`) |
| Nombre de cliente, producto, sucursal, proveedor | `dim_clientes`, `dim_productos`, `dim_sucursales`, `dim_proveedores` | Catálogo |
| CXC vencido, saldo, días | Vistas `vw_dashboard_cxc_*` | Cliente |
| CXP | Vistas de cuentas por pagar del dashboard de gastos/cuentas | Proveedor |
| Días de inventario y sin venta | Hecho de inventario del ETL `inventario` | Producto |

Estas tres dimensiones se pueden calcular **sin hecho nuevo**, agregando en la consulta del API sobre esos hechos, con el mismo filtro de periodo que el P&L:

1. Sucursal
2. Producto
3. Cliente

El costo de «Servicio de importación de paquetería» (costo de catálogo muy por encima del precio) va a salir también aquí. Es el mismo `costo_total`. No se tapa en rentabilidad.

---

## 6. Lo que falta en el warehouse

| Dato de la UI | Por qué no sale hoy | Qué hacer |
|---------------|---------------------|-----------|
| Ganancia al vender lo comprado a un proveedor | `fact_gastos_mensuales` no guarda el producto de la compra | ETL: detalle de compra en producción → producto, y cruzar con `fact_ventas_productos_mensuales` |
| Otra cotización | No hay cotizaciones de compra comparables en analytics | Campo vacío |
| Proyecto (contrato, facturado, gastado, cobrado) | `id_proyecto` vive en producción (`ventas`, `compras`, `egresos`, `presupuestos`) y no está en los hechos | Hecho `fact_proyecto_rentabilidad` leyendo esas tablas |
| Materiales cotizado vs comprado | Solo si el presupuesto del proyecto tiene cantidad y precio unitario | Lista vacía cuando el presupuesto es solo un monto |
| Costo de esperar el cobro | No hay tasa ni costo financiero por factura | `esperarPago = 0` y `alFinalQuedan = deCada100` hasta que exista esa tasa |
| Modelado (rubro, fijo/variable) | Fuera de este corte | No cambia estas fórmulas |

---

## 7. API propuesta

Un endpoint, el mismo patrón que el P&L.

```http
GET /api/finanzas/pl-gerencial
```

no se toca. El nuevo es:

```http
GET /api/finanzas/rentabilidad?empresa=&anio=&mes=
  &horizonte=mes|trimestre|anio
  &sucursal=
```

Header: `Authorization: Bearer {API_SECRET}`

Cuerpo, alineado a `RentabilidadMock`:

```json
{
  "meta": { "periodoTitulo": "octubre 2026", "horizonte": "mes" },
  "filasSucursal": [],
  "filasProducto": [],
  "filasCliente": [],
  "filasProveedor": [],
  "filasProyecto": []
}
```

Cada fila trae su `detalle`. No hace falta un segundo request al abrir la fila: el mock ya lo trae embebido y la pantalla lo espera así.

`kpis` de la raíz no se envían. Esos cinco los calcula `kpisDesdeSucursal` / `Producto` / `Cliente` / `Proveedor` / `Proyecto` con la fila. En modo alertas la otra pestaña sigue con su propio mock.

Montos con dos decimales. Porcentajes con un decimal cuando la UI usa `toFixed(1)`, dos cuando es margen de tabla.

### Orden de construcción

1. **Sucursal, producto y cliente** sobre hechos actuales. Conecta el frontend y se elimina el mock de esas tres listas.
2. **Proveedor** cuando el ETL una compra → producto. Mientras tanto la fila puede salir solo con `compro`, `parteComprasPct`, `diasPago` y `ganoAlVender` en 0, marcado, o esperar al paso 2 y no publicar la dimensión a medias.
3. **Proyecto** con hecho nuevo desde `id_proyecto`. Materiales vacíos si no hay cotización por cantidad.

Recomendación: el primer endpoint publica las cinco claves. Proveedor y proyecto van en `[]` hasta que su hecho exista, y la UI muestra vacío, no el mock. Así no se mezclan empresas reales con «Constructora Meléndez».

---

## 8. Cambios de frontend cuando el API exista

No son de este documento de backend, pero el contrato los asume:

- Dejar de usar `RENTABILIDAD_MOCK` y `factorHorizonte()` (el ×3 y ×9).
- Pedir de nuevo al cambiar horizonte o sucursal.
- Formatear montos con dos decimales (`formatEmpresaCurrency`), igual que el P&L.
- Seguir construyendo las frases de las tarjetas en el cliente, a partir de los números.

---

## 9. Qué no entra

- Modelado como configuración de rubros o de presupuesto por área.
- Frases generadas en el servidor.
- PDF y Lucas.
- Cierre de mes y period-lock.
- Prorratear gastos de sucursal dentro del margen de cada producto o cliente.
- Corregir costos de catálogo absurdos: se muestran, no se recortan.
