# Diseño: Facturas y tickets en formato 80 mm

**Fecha:** 2026-09-30  
**Estado:** Aprobado en conversación  
**Tipo:** Preferencia de facturación + plantilla de impresión nueva  
**Fuera de alcance:** Semáforo de tiempo en comandas (se guarda el tiempo por cambio de estado para reportes; se diseña aparte). Recibo, cotización, nota de crédito, nota de débito y el resto de documentos. Reemplazar plantillas de empresa que ya son propias.

---

## 1. Problema

Hoy la factura comercial sale en formato carta, y el ticket de 80 mm genérico no sigue la disposición de referencia (encabezado, venta, cliente, líneas, totales). Honduras necesita CAI, rango, fecha límite y el correlativo `001-001-01-00000879` en ese ticket. La plantilla de Santre ya hace eso, pero es de una sola empresa y no se reutiliza.

Cualquier empresa, de cualquier país, debe poder elegir ese formato. El comprobante electrónico sigue con su ticket legal.

## 2. Decisiones

| Tema | Decisión |
|------|----------|
| Quién puede usarlo | Cualquier país |
| Control | Switch en Preferencias del sistema → Facturación |
| Clave | `imprimir_factura_ticket_80mm` en `custom_empresa.configuraciones`. Default `false` |
| Plantilla | Nueva, `reportes.facturacion.ticket-80mm`. Santre es solo referencia visual |
| Documentos | Factura, Ticket, y en Honduras Factura con RTN y Factura sin RTN |
| Electrónico | DTE de El Salvador y factura/tiquete de Costa Rica no cambian |
| Plantillas propias que no se reemplazan | Empresa 818 (Santre), 716 o flag `factura_ticket_accesorios_hn`, 420 (Inversiones André), 614 (Accesorios HN carta), 700 (Ohle) |
| Resto de plantillas carta por empresa | Con el switch apagado se quedan. Con el switch encendido, Factura, Ticket y (en Honduras) Factura con RTN y Factura sin RTN pasan a la plantilla nueva |
| PDF | El switch existente `ticket_en_pdf` se respeta: PDF de 80 mm o ventana de impresión |
| Rancho Sofía | No tiene plantilla en el código. El switch les aplica igual que a cualquier empresa sin formato propio |

## 3. Cuándo se usa la plantilla nueva

Orden fijo:

1. Documento electrónico (DTE El Salvador, Factura Electrónica o Tiquete Electrónico de Costa Rica): ticket legal actual.
2. Empresa 818, 716 (o `factura_ticket_accesorios_hn`), 420, 614 o 700: su plantilla actual.
3. Switch encendido y el documento es Factura, Ticket, Factura con RTN o Factura sin RTN: `ticket-80mm`.
4. Cualquier otro caso: el camino de hoy.

Recibo, cotización, nota de crédito y nota de débito quedan en el paso 4.

## 4. Contenido de `ticket-80mm`

Ancho 80 mm. Moneda de la empresa, no lempiras fijos.

- Encabezado: logo si existe, nombre, dirección, teléfono, correo, identificación fiscal con la etiqueta del país (RTN en Honduras, NIT en El Salvador, la que ya usa el ticket actual en los demás).
- Venta: sucursal, tipo de documento, número, fecha y hora, forma de pago.
- Cliente: nombre y documento fiscal. Sin cliente: «Consumidor final».
- Líneas: descripción, cantidad, precio unitario, total.
- Totales: subtotal, exento, impuesto, descuento, total. Honduras desglosa ISV 15 % y 18 %. Los demás países, una línea de impuesto con el nombre que ya usa el sistema.
- Pie Honduras: CAI, rango autorizado, fecha límite, correlativo con `FormatoCorrelativoHn` (`001-001-01-` + 8 dígitos), total en letras en lempiras, leyenda «La factura es beneficio de todos, exíjala».
- Pie otros países: número de documento tal como está guardado, total en letras en la moneda de la empresa. Sin CAI.
- Siempre: observaciones del documento si existen, «Documento generado por SmartPyme» y fecha de impresión.

Si falta CAI, rango, logo o cliente, esa línea no se imprime. La impresión no se detiene. Si Honduras no trae punto de emisión, se imprime el correlativo tal cual.

Datos de Honduras que ya existen: CAI en `documento.resolucion` (o `configuraciones.factura_cai`), rango en `documento.rangos` o `numero_autorizacion` (o `factura_rango_autorizado`), fecha límite en `documento.fecha` (o `factura_fecha_limite`). El correlativo numérico sigue guardado en `ventas.correlativo`; el formato `001-001-01-00000879` es solo de impresión.

## 5. Dónde vive el cambio

Una función responde si esta venta usa la plantilla nueva. No se copia la regla en cada controlador.

La consultan los tres puntos que hoy imprimen factura o ticket:

- `GenerarDocumentosController`
- `VentasController::generarDoc`
- `DocumentoService` (`generarTicket` y el camino de factura)

El switch en `empresa.component.html`, grupo Facturación, sigue el patrón de `ticket_en_pdf`: getter/setter en `empresa.component.ts`, default `false` en `Empresa`, y la clave en la allowlist booleana de `EmpresasController` y en `UpdateCustomConfigRequest`.

## 6. Prueba

Una prueba de la función de decisión y del HTML:

- Switch apagado: no elige `ticket-80mm`.
- Switch encendido y Factura sin plantilla propia: elige `ticket-80mm`.
- DTE, y empresa 818: no la eligen.
- HTML de Honduras: incluye CAI y un correlativo `001-001-01-` de 8 dígitos.
- HTML de otro país: no incluye CAI.

## 7. Seguimiento

Semáforo de comandas: cada cambio de estado guarda el tiempo en base de datos para reportes. No forma parte de este cambio.
