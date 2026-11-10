# Manual de usuario — Módulo de consignaciones (SmartPyme)

> Borrador para soporte y ventas. Completar capturas y textos finales al cerrar desarrollo.

## 1. ¿Para qué sirve?

Permite llevar **consignación de mercadería** con un **kardex de consigna aparte**:

- Mercadería del **proveedor** (entrada consigna) hasta liquidar lo vendido.
- Mercadería **entregada a tus clientes** en consigna (remisión) hasta facturar el consumo.

El **kardex de inventario habitual** sigue mostrando el movimiento físico en bodega. El **kardex de consigna** muestra propiedad, obligaciones y cierres **sin duplicar** inventario al pagar al proveedor.

## 2. ¿Quién puede usarlo?

- Debe estar habilitado por **SmartPyme (Super Admin)** en tu empresa.
- El administrador de la empresa activa el módulo en **Mi cuenta → Configuración del sistema**.
- Los usuarios necesitan permisos de **consignas** (compras/ventas) y **kardex de consigna** según su rol.

Si no está habilitado, **no verás cambios** respecto a SmartPyme estándar.

## 3. Activación (administrador)

1. Solicitar a SmartPyme la funcionalidad **Módulo de consignaciones**.
2. En **Mi cuenta → Módulos**: activar **Ledger / Kardex de consignaciones**.
3. Mantener **Venta a consigna** si usan entregas con remisión a clientes.
4. Revisar permisos del personal en **Roles y permisos**.

## 4. Flujos principales

### 4.1 Recibir mercadería del proveedor en consigna

1. **Compras → Nueva compra** → activar **Consigna** y documento de remisión según proceso actual.
2. Al guardar: el producto **ingresa a bodega** (inventario físico).
3. En **Kardex de consigna** aparece la **entrada** ligada al proveedor.

### 4.2 Vender producto que es del proveedor (consigna compra)

1. En **Facturación**, al agregar el producto, si hay mezcla de stock, el sistema pregunta **Consigna (compra)** o **Inventario normal**.
2. Elija **Consigna (compra)** si la unidad vendida era del proveedor.
3. Al pagar al proveedor use **Revisar consigna** (compras); **no** se vuelve a sumar inventario.

### 4.3 Entregar mercadería a tu cliente en consigna

1. Facturación → **Venta a consigna** (remisión).
2. El cliente recibe mercadería sin factura de consumo inmediato.
3. En **Revisar consigna** (ventas) facture lo consumido; el resto queda en consigna.

### 4.4 Consultar trazabilidad

- Menú **Inventario → Kardex de consigna** (nombre final según UI).
- Filtre por producto, proveedor, cliente, fechas.

## 5. Preguntas frecuentes (soporte)

**¿Por qué al pagar al proveedor no veo otra entrada en el kardex de producto?**  
Por diseño: el pago solo registra la **liquidación en kardex de consigna**. Duplicar entrada en inventario sería error.

**¿Pierdo el kardex antiguo?**  
No. El kardex de producto sigue igual. El de consigna es **adicional**.

**¿Afecta a empresas que no usan consigna?**  
No, si el módulo no está activado por SmartPyme.

**¿Qué pasa con reportes de ventas “canal Consigna”?**  
Se mantiene la etiqueta en reportes cuando la venta usó stock de consigna compra (comportamiento existente).

## 6. Capacitación sugerida (ventas)

- Diferenciar **venta normal**, **venta desde consigna proveedor**, **venta consigna a cliente**.
- Enfatizar: **modal de origen** = de quién era la mercadería económicamente, no solo dónde está físicamente.

## 7. Soporte técnico

- Ticket Jira: **SP-2344**
- Documentación técnica: carpeta `Docs/consignaciones-v2/` en repositorio

---

*Versión del manual: borrador — actualizar al release.*
