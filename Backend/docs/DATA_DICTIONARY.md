# 📊 Diccionario de Datos - API Externa SmartPYME

## 📋 Información General

Este documento describe todas las columnas y campos de datos que se envían a través de la API Externa de SmartPYME para los endpoints de **Ventas** e **Inventario**.

---

## 🛒 VENTAS (Sales)

### 📄 Estructura Principal de Venta

| Campo | Tipo | Descripción | Ejemplo | Notas |
|-------|------|-------------|---------|-------|
| `id` | Integer | Identificador único de la venta | `12345` | Clave primaria, autoincremental |
| `fecha` | Date | Fecha de la venta | `"2024-10-21"` | Formato: YYYY-MM-DD |
| `correlativo` | String | Número correlativo de la venta | `"V-001234"` | Único por empresa |
| `estado` | String | Estado actual de la venta | `"Completada"` | Valores: Completada, Pendiente, Anulada, Cotizacion |
| `forma_pago` | String | Método de pago utilizado | `"Efectivo"` | Ej: Efectivo, Tarjeta, Transferencia |
| `monto_pago` | Decimal(10,2) | Monto pagado por el cliente | `150.75` | Puede ser diferente al total (pagos parciales) |
| `cambio` | Decimal(10,2) | Cambio devuelto al cliente | `4.25` | Solo aplica para pagos en efectivo |
| `iva_percibido` | Decimal(10,2) | IVA percibido en la transacción | `19.50` | Impuesto aplicado |
| `iva_retenido` | Decimal(10,2) | IVA retenido en la transacción | `2.30` | Retención fiscal |
| `renta_retenida` | Decimal(10,2) | Retención de renta aplicada | `5.00` | Retención fiscal |
| `iva` | Decimal(10,2) | Total de IVA de la venta | `19.50` | Impuesto sobre el valor agregado |
| `total_costo` | Decimal(10,2) | Costo total de los productos vendidos | `85.00` | Suma de costos de todos los productos |
| `descuento` | Decimal(10,2) | Descuento aplicado a la venta | `10.00` | Descuento general de la venta |
| `sub_total` | Decimal(10,2) | Subtotal antes de impuestos | `130.00` | Total antes de IVA |
| `no_sujeta` | Decimal(10,2) | Monto no sujeto a impuestos | `0.00` | Productos exentos de impuestos |
| `exenta` | Decimal(10,2) | Monto exento de impuestos | `0.00` | Productos con exención fiscal |
| `gravada` | Decimal(10,2) | Monto gravado con impuestos | `130.00` | Base imponible para IVA |
| `cuenta_a_terceros` | Decimal(10,2) | Monto por cuenta de terceros | `0.00` | Servicios facturados por terceros |
| `total` | Decimal(10,2) | Total final de la venta | `149.50` | Monto total a pagar |
| `propina` | Decimal(10,2) | Propina incluida | `0.00` | Propina del servicio |
| `observaciones` | Text | Comentarios adicionales | `"Cliente frecuente"` | Notas internas de la venta |
| `recurrente` | Boolean | Indica si es venta recurrente | `false` | true/false |
| `cotizacion` | Boolean | Indica si es una cotización | `false` | true/false |
| `descripcion_impresion` | Text | Texto personalizado para impresión | `"Gracias por su compra"` | Mensaje en factura |
| `saldo` | Decimal(10,2) | Saldo pendiente de pago | `0.00` | Para ventas a crédito |
| `created_at` | Timestamp | Fecha de creación del registro | `"2024-10-21T10:30:00Z"` | ISO 8601 format |
| `updated_at` | Timestamp | Fecha de última actualización | `"2024-10-21T10:30:00Z"` | ISO 8601 format |

### 👥 Campos Relacionados (Nombres)

| Campo | Tipo | Descripción | Ejemplo |
|-------|------|-------------|---------|
| `nombre_cliente` | String | Nombre del cliente | `"Juan Pérez"` |
| `nombre_usuario` | String | Usuario que registró la venta | `"admin"` |
| `nombre_vendedor` | String | Vendedor asignado | `"María García"` |
| `nombre_sucursal` | String | Sucursal donde se realizó | `"Sucursal Centro"` |
| `nombre_canal` | String | Canal de venta | `"Mostrador"` |
| `nombre_documento` | String | Tipo de documento fiscal | `"Factura"` |

### 🛍️ Detalles de Venta (Sale Details)

| Campo | Tipo | Descripción | Ejemplo | Notas |
|-------|------|-------------|---------|-------|
| `nombre_producto` | String | Nombre del producto vendido | `"Laptop Dell Inspiron"` | Nombre comercial |
| `codigo_producto` | String | Código interno del producto | `"LAP-DELL-001"` | SKU del producto vendido |
| `marca_producto` | String | Marca del producto vendido | `"Dell"` | Fabricante o marca del producto |
| `cantidad` | Decimal(10,3) | Cantidad vendida | `2.000` | Permite decimales para productos fraccionables |
| `precio` | Decimal(10,2) | Precio unitario de venta | `750.00` | Precio al público |
| `costo` | Decimal(10,2) | Costo unitario del producto | `600.00` | Costo de adquisición |
| `descuento` | Decimal(10,2) | Descuento aplicado al ítem | `50.00` | Descuento por producto |
| `total_costo` | Decimal(10,2) | Costo total del ítem | `1200.00` | costo × cantidad |
| `total` | Decimal(10,2) | Total del ítem | `1450.00` | (precio × cantidad) - descuento + IVA |
| `iva` | Decimal(10,2) | IVA del ítem | `187.75` | Impuesto aplicado al ítem |

---

## 📦 INVENTARIO (Inventory)

### 🏷️ Estructura Principal de Producto

| Campo | Tipo | Descripción | Ejemplo | Notas |
|-------|------|-------------|---------|-------|
| `id` | Integer | Identificador único del producto | `5678` | Clave primaria, autoincremental |
| `nombre` | String | Nombre del producto | `"Laptop Dell Inspiron 15"` | Nombre comercial completo |
| `descripcion` | Text | Descripción detallada | `"Laptop con procesador Intel i5..."` | Descripción técnica |
| `codigo` | String | Código interno del producto | `"LAP-DELL-001"` | SKU interno de la empresa |
| `barcode` | String | Código de barras | `"7501234567890"` | Código EAN/UPC |
| `nombre_categoria` | String | Categoría del producto | `"Electrónicos"` | Clasificación por categoría |
| `precio` | Decimal(10,2) | Precio de venta actual | `899.99` | Precio al público |
| `costo` | Decimal(10,2) | Costo actual de adquisición | `720.00` | Último costo de compra |
| `costo_anterior` | Decimal(10,2) | Costo anterior | `715.00` | Costo previo para comparación |
| `costo_promedio` | Decimal(10,2) | Costo promedio ponderado | `717.50` | Promedio de costos históricos |
| `marca` | String | Marca del producto | `"Dell"` | Fabricante o marca |
| `tipo` | String | Tipo de producto | `"Producto"` | Ej: Producto, Servicio, Combo |
| `enable` | Boolean | Estado activo del producto | `true` | true = activo, false = inactivo |
| `created_at` | Timestamp | Fecha de creación | `"2024-01-15T08:00:00Z"` | ISO 8601 format |
| `updated_at` | Timestamp | Fecha de actualización | `"2024-10-21T14:30:00Z"` | ISO 8601 format |

### 🏪 Stock por Bodega (Inventory Stock)

| Campo | Tipo | Descripción | Ejemplo | Notas |
|-------|------|-------------|---------|-------|
| `id_producto` | Integer | ID del producto | `5678` | Referencia al producto |
| `stock` | Decimal(10,3) | Cantidad disponible | `25.000` | Stock actual en la bodega |
| `stock_minimo` | Decimal(10,3) | Stock mínimo requerido | `5.000` | Punto de reorden |
| `stock_maximo` | Decimal(10,3) | Stock máximo permitido | `100.000` | Capacidad máxima |
| `nota` | Text | Observaciones del stock | `"Revisar vencimiento"` | Notas especiales |
| `nombre_bodega` | String | Nombre de la bodega | `"Bodega Principal"` | Ubicación física |
| `nombre_sucursal` | String | Sucursal de la bodega | `"Sucursal Centro"` | Sucursal que contiene la bodega |

---

## 🔄 DEVOLUCIONES (Returns)

### 📋 Estructura Principal de Devolución

| Campo | Tipo | Descripción | Ejemplo | Notas |
|-------|------|-------------|---------|-------|
| `id` | Integer | Identificador único de la devolución | `789` | Clave primaria, autoincremental |
| `fecha` | Date | Fecha de la devolución | `"2024-10-21"` | Formato: YYYY-MM-DD |
| `correlativo` | String | Número correlativo de la devolución | `"DEV-001234"` | Único por empresa |
| `tipo` | String | Tipo de devolución | `"Devolucion"` | Tipo de documento |
| `sub_total` | Decimal(10,2) | Subtotal antes de impuestos | `130.00` | Total antes de IVA |
| `no_sujeta` | Decimal(10,2) | Monto no sujeto a impuestos | `0.00` | Productos exentos de impuestos |
| `exenta` | Decimal(10,2) | Monto exento de impuestos | `0.00` | Productos con exención fiscal |
| `cuenta_a_terceros` | Decimal(10,2) | Monto por cuenta de terceros | `0.00` | Servicios facturados por terceros |
| `total` | Decimal(10,2) | Total final de la devolución | `149.50` | Monto total devuelto |
| `iva` | Decimal(10,2) | Total de IVA de la devolución | `19.50` | Impuesto sobre el valor agregado |
| `iva_retenido` | Decimal(10,2) | IVA retenido en la transacción | `2.30` | Retención fiscal |
| `observaciones` | Text | Comentarios adicionales | `"Producto defectuoso"` | Motivo de la devolución |
| `enable` | Boolean | Estado activo de la devolución | `true` | true = activa, false = inactiva |
| `id_venta` | Integer | ID de la venta original | `12345` | Referencia a la venta devuelta |
| `created_at` | Timestamp | Fecha de creación del registro | `"2024-10-21T10:30:00Z"` | ISO 8601 format |
| `updated_at` | Timestamp | Fecha de última actualización | `"2024-10-21T10:30:00Z"` | ISO 8601 format |

### 👥 Campos Relacionados (Nombres)

| Campo | Tipo | Descripción | Ejemplo |
|-------|------|-------------|---------|
| `nombre_cliente` | String | Nombre del cliente | `"Juan Pérez"` |
| `nombre_usuario` | String | Usuario que registró la devolución | `"admin"` |
| `nombre_documento` | String | Tipo de documento fiscal | `"Nota de Crédito"` |

### 🔄 Detalles de Devolución (Return Details)

| Campo | Tipo | Descripción | Ejemplo | Notas |
|-------|------|-------------|---------|-------|
| `nombre_producto` | String | Nombre del producto devuelto | `"Laptop Dell Inspiron"` | Nombre comercial |
| `codigo_producto` | String | Código interno del producto | `"LAP-DELL-001"` | SKU del producto devuelto |
| `marca_producto` | String | Marca del producto devuelto | `"Dell"` | Fabricante o marca del producto |
| `descripcion` | String | Descripción del producto | `"Laptop Dell Inspiron 15"` | Descripción detallada |
| `cantidad` | Decimal(10,3) | Cantidad devuelta | `1.000` | Permite decimales para productos fraccionables |
| `precio` | Decimal(10,2) | Precio unitario original | `750.00` | Precio al que se vendió |
| `costo` | Decimal(10,2) | Costo unitario del producto | `600.00` | Costo de adquisición |
| `descuento` | Decimal(10,2) | Descuento aplicado al ítem | `50.00` | Descuento por producto |
| `no_sujeta` | Decimal(10,2) | Monto no sujeto a impuestos | `0.00` | Productos exentos |
| `cuenta_a_terceros` | Decimal(10,2) | Monto por cuenta de terceros | `0.00` | Servicios de terceros |
| `exenta` | Decimal(10,2) | Monto exento de impuestos | `0.00` | Productos con exención |
| `total` | Decimal(10,2) | Total del ítem devuelto | `700.00` | Monto total del producto devuelto |
| `medida` | String | Unidad de medida | `"Unidad"` | Ej: Unidad, Kg, Litro |

---

## 📊 TIPOS DE DATOS

### 🔢 Especificaciones Técnicas

| Tipo | Descripción | Rango/Formato | Ejemplo |
|------|-------------|---------------|---------|
| `Integer` | Número entero | -2,147,483,648 a 2,147,483,647 | `12345` |
| `Decimal(10,2)` | Número decimal con 2 decimales | Hasta 8 dígitos enteros, 2 decimales | `1234.56` |
| `Decimal(10,3)` | Número decimal con 3 decimales | Hasta 7 dígitos enteros, 3 decimales | `1234.567` |
| `String` | Texto variable | Hasta 255 caracteres | `"Texto ejemplo"` |
| `Text` | Texto largo | Hasta 65,535 caracteres | `"Descripción larga..."` |
| `Boolean` | Verdadero/Falso | true o false | `true` |
| `Date` | Solo fecha | YYYY-MM-DD | `"2024-10-21"` |
| `Timestamp` | Fecha y hora completa | ISO 8601 | `"2024-10-21T10:30:00Z"` |

---

## 🔍 VALORES ESPECIALES

### 📋 Estados de Venta

| Valor | Descripción |
|-------|-------------|
| `"Completada"` | Venta finalizada y pagada |
| `"Pendiente"` | Venta registrada, pago pendiente |
| `"Anulada"` | Venta cancelada |
| `"Cotizacion"` | Cotización, no es venta real |

### 💳 Formas de Pago Comunes

| Valor | Descripción |
|-------|-------------|
| `"Efectivo"` | Pago en efectivo |
| `"Tarjeta"` | Pago con tarjeta |
| `"Transferencia"` | Transferencia bancaria |
| `"Cheque"` | Pago con cheque |
| `"Crédito"` | Venta a crédito |

### 📦 Tipos de Producto

| Valor | Descripción |
|-------|-------------|
| `"Producto"` | Producto físico |
| `"Servicio"` | Servicio |

---

## ⚠️ CONSIDERACIONES IMPORTANTES
### 📊 Campos Calculados
- `total_costo`: Suma de costos de todos los detalles
- `total`: Suma final incluyendo impuestos y descuentos
- `costo_promedio`: Calculado automáticamente por el sistema

### 🔄 Relaciones
- **Ventas → Detalles**: Una venta puede tener múltiples detalles
- **Productos → Stock**: Un producto puede estar en múltiples bodegas
- **Campos con prefijo `nombre_`**: Son campos calculados/relacionados
---

*Última actualización: Octubre 2024*
