# Cambios: IVA por producto y desglose en facturación

Resumen para presentar al equipo.

---

## Campos nuevos en la base de datos

- **En productos:** se agregó un campo para el impuesto (porcentaje) del producto.
- **En detalles de venta:** se agregó un campo para guardar el impuesto aplicado en cada línea de la factura.

---

## Productos

- En productos se agregó la posibilidad de **elegir el impuesto** (porcentaje) por producto.
- El **precio con IVA** se muestra según el impuesto del producto (o el de la empresa si el producto no tiene uno asignado).

---

## Comando para asignar impuesto

- Existe un comando para **asignar el impuesto de la empresa** a los productos que aún no tienen impuesto definido (para migrar datos).

---

## Facturación (v1 y v2)

- En facturación v1 y v2 se **toma en cuenta el impuesto del producto** para cada detalle (línea).
- El IVA se **desglosa por tasa**: cada impuesto (10%, 15%, 18%, etc.) muestra solo el monto que corresponde a las líneas con ese porcentaje.

---

## Ventas

- En ventas se muestra el **desglose de impuestos según los detalles**: cada tasa con su monto en dinero y el porcentaje al lado del nombre.
