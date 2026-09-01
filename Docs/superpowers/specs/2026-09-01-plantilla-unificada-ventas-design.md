# Design: Plantilla unificada de importación de ventas

**Fecha:** 2026-09-01  
**Estado:** Aprobado en conversación; pendiente revisión final del archivo  
**Alcance:** Unificar las plantillas Excel de consumidor final y crédito fiscal en una sola, con validación por fila visible (fila + columna + motivo) y correlativos históricos obligatorios.

## Problema

Hay dos plantillas (`ventas-consumidor-final-format.xlsx` y `ventas-credito-fiscal-format.xlsx`) con columnas distintas. El importador (`VentasExcelImport`) ya lee ambas, pero **adivina** el tipo: si la fila trae NIT → crédito fiscal y cliente Empresa; si no → consumidor final y cliente Persona. Eso falla cuando una persona natural contribuyente pide CCF (NIT + NRC) o cuando una empresa compra con Factura.

Además:

- Crédito fiscal no trae `correlativo`; consumidor final sí.
- El dropdown de `forma_pago` solo tiene Tarjeta; el sistema y Hacienda usan más métodos.
- Los errores se concatenan en un string y el modal de importación no muestra fila ni columna.
- `GenerarPlantillasCommand` no coincide con los Excel reales; el frontend ofrece dos links y `Backend/public/docs/` no tiene esos archivos.

## Objetivos

1. Una sola plantilla `ventas-format.xlsx` y un solo link de descarga.
2. Discriminar con campos explícitos: `tipo_cliente` y `tipo_documento_venta`.
3. Permitir Persona + Crédito fiscal (NIT y NRC obligatorios).
4. Exigir `correlativo` en todas las filas (ventas históricas).
5. Ampliar `forma_pago` a los nombres que ya usa facturación/DTE.
6. Si hay un error de validación, no guardar nada y listar fila + columna + motivo en el modal.
7. Si no hay errores, cerrar el modal y mostrar success.

## Fuera de alcance

- Compatibilidad con las dos plantillas actuales (se dejan de ofrecer y de importar).
- Buscar o descontar productos de inventario.
- Mostrar códigos MH (`01`, `02`…) en el Excel; el DTE los resuelve después por nombre.
- Importación de ventas para Costa Rica u Honduras (sigue el flujo SV actual).
- Importación parcial (filas buenas sí, malas no).

## Decisiones

| Decisión | Elección |
|----------|----------|
| Enfoque | Una plantilla, un importador, un link |
| Discriminador | `tipo_cliente` + `tipo_documento_venta` (nunca inferir por NIT) |
| CCF + Persona | Permitido; `nit` y `nrc` obligatorios |
| Correlativo | Obligatorio (histórico); no se autogenera |
| tipo_item | Siempre `Servicio`; `id_producto = 0`; no se busca producto |
| forma_pago | Nombres: Efectivo, Tarjeta de crédito/débito, Cheque, Transferencia, Vales, Chivo Wallet, Bitcoin |
| Errores | Lista estructurada; modal abierto; rollback total |
| Éxito | Cerrar modal + alerta success |
| Plantillas viejas | Dejar de generar, servir e importar |

## Plantilla

Archivo: `Backend/public/docs/ventas-format.xlsx`  
Hojas: `Plantilla ventas` (datos), `Valores` (listas), `Instrucciones`.  
Solo se importa la primera hoja.

### Columnas (fila 1, no modificar)

`tipo_cliente`, `tipo_documento_venta`, `correlativo`, `estado_factura`, `nombre`, `tipo_documento`, `num_documento`, `nombre_comercial`, `nit`, `nrc`, `giro`, `departamento`, `municipio`, `distrito`, `direccion`, `telefono`, `correo`, `fecha`, `descripcion`, `tipo_item`, `forma_pago`, `no_sujeta`, `exenta`, `gravada`, `subtotal`, `iva`, `iva_retenido`, `total`, `condicion`, `fecha_pago`

### Listas desplegables

| Campo | Valores |
|-------|---------|
| `tipo_cliente` | Persona, Empresa |
| `tipo_documento_venta` | Factura, Ticket, Crédito fiscal, Factura de exportación |
| `tipo_documento` | DUI, NIT, Pasaporte, Carnet de residente, Otro |
| `estado_factura` | Pagada, Pendiente, Anulada |
| `tipo_item` | Servicio (si viene vacío o Producto, se guarda Servicio igual; no hay inventario) |
| `forma_pago` | Efectivo, Tarjeta de crédito/débito, Cheque, Transferencia, Vales, Chivo Wallet, Bitcoin |
| `condicion` | Contado, Crédito |

Ubicación (departamento, municipio, distrito) y giro se resuelven por nombre, como ahora, usando la hoja `Valores`.

### Campos obligatorios

Siempre: `tipo_cliente`, `tipo_documento_venta`, `correlativo`, `nombre`, `fecha`, `descripcion`, `total`.

Si `tipo_documento_venta` es Crédito fiscal (Persona o Empresa): `nit` y `nrc`. `giro` no es obligatorio.

Si `tipo_cliente` es Persona y el documento no es Crédito fiscal: `tipo_documento` y `num_documento` cuando hay documento. Nombre `Consumidor Final` puede ir sin documento.

Si `tipo_cliente` es Empresa: `nombre_comercial` opcional; si está vacío se usa `nombre`.

Si `condicion` es Crédito: `fecha_pago` obligatorio.

`estado_factura` vacío → `Pagada`.  
`forma_pago` vacío → error (hay que elegir una de la lista).  
`tipo_item` vacío → `Servicio`.

Cada fila es un ítem. Varias filas con el mismo correlativo + mismo `tipo_documento_venta` + mismo cliente = una venta con varios detalles.

Inconsistencias de agrupación (error, no se importa):

- Mismo `correlativo` + mismo `tipo_documento_venta` con clientes distintos.
- Mismo `correlativo` con `tipo_documento_venta` distinto entre filas.

## Errores

Cada error:

```json
{ "fila": 12, "columna": "nit", "mensaje": "obligatorio porque tipo_documento_venta es Crédito fiscal" }
```

`fila` es el número de Excel (encabezado = 1, primera data = 2).  
Texto para el usuario: `Fila 12, columna nit: obligatorio porque tipo_documento_venta es Crédito fiscal.`

Respuesta de error (HTTP 422):

```json
{
  "message": "No se importó ninguna venta. Hay 3 errores.",
  "procesadas": 0,
  "errores": [
    { "fila": 12, "columna": "nit", "mensaje": "..." }
  ]
}
```

Cualquier error de validación o de negocio (documento de sucursal inexistente, fecha inválida, valor fuera de lista) aborta el import completo (`DB::rollback`). No hay importación parcial.

Respuesta de éxito (HTTP 200):

```json
{
  "message": "Se importaron 15 ventas correctamente.",
  "procesadas": 15,
  "errores": []
}
```

El frontend, en el modal de importar ventas:

- Error: modal abierto, tabla Fila | Columna | Error (scroll), se puede volver a subir el archivo.
- Éxito: cierra el modal y muestra alerta success con el `message`.

## Flujo de importación

1. Validar archivo (xlsx/xls/csv, máx. 10 MB) — ya existe en `ImportVentasRequest`.
2. Leer primera hoja. Encabezados deben incluir las columnas de la plantilla unificada; si faltan las clave (`tipo_cliente`, `tipo_documento_venta`, `correlativo`) → un error de archivo, no por fila.
3. Recorrer filas; ignorar filas vacías.
4. Validar cada fila; acumular errores con fila/columna. Si hay errores → rollback, 422, lista.
5. Agrupar por `correlativo` + `tipo_documento_venta` + identidad del cliente (`num_documento` o `nit`/`nrc`).
6. Cliente:
   - Persona: buscar por `num_documento`; crear tipo Persona. Si CCF, persistir nit, nrc, giro.
   - Empresa: buscar por nit o nrc; crear tipo Empresa.
7. Documento: match por nombre de `tipo_documento_venta` en la sucursal. Si no existe → error en esa columna.
8. Cabecera de venta: correlativo del Excel, forma_pago del Excel, montos de las filas agrupadas, `importado = true`.
9. Detalle: descripción del Excel, `tipo_item = Servicio`, `id_producto = 0`.
10. Actualizar `documentos.correlativo` a `max(actual, correlativo_importado + 1)` para no chocar con facturas nuevas.
11. Commit. Devolver `procesadas`.

## Archivos a tocar

- `Backend/app/Imports/VentasExcelImport.php` — validación, agrupación, errores estructurados; dejar de inferir por NIT.
- `Backend/app/Http/Controllers/Api/Ventas/VentasImportController.php` — 422 con `errores[]`; 200 con success.
- `Backend/app/Console/Commands/GenerarPlantillasCommand.php` — una sola plantilla `ventas-format.xlsx`.
- `Backend/public/docs/ventas-format.xlsx` — artefactos generados (dejar de generar/servir las dos viejas).
- `Frontend/src/app/shared/parts/importar-excel/importar-excel.component.html` — un link; tabla de errores.
- `Frontend/src/app/shared/parts/importar-excel/importar-excel.component.ts` — pintar `errores`, no cerrar en fallo, cerrar en éxito.
- Tests unitarios del importador y, si aplica, del componente de importación.

## Pruebas

Mínimo (PHPUnit sobre `VentasExcelImport` / controller):

1. Fila Persona + Factura + correlativo + total → válida; cliente tipo Persona; detalle Servicio.
2. Fila Persona + Crédito fiscal sin nit/nrc → error en columna `nit` y/o `nrc`, fila correcta, `procesadas = 0`.
3. Fila Persona + Crédito fiscal con nit y nrc → válida; cliente Persona con nit/nrc.
4. Fila Empresa + Factura → válida; no exige nrc.
5. Fila sin correlativo → error columna `correlativo`.
6. `forma_pago` fuera de lista → error columna `forma_pago`.
7. Dos filas mismo correlativo + mismo cliente + mismo documento → una venta, dos detalles.
8. Mismo correlativo, dos clientes → error de agrupación con filas involucradas.
9. Un error en fila 5 y datos bien en fila 3 → no se guarda ninguna venta.
10. Encabezados de plantilla vieja (sin `tipo_cliente`) → error de archivo, no importar.

## Contexto actual (referencia)

- Import: `VentasExcelImport` + `VentasImportController::importar`.
- UI: `importar-excel` cuando `nombre === 'ventas'` (dos links).
- Generación: `php artisan ventas:generar-plantillas`.
- DTE mapea `forma_pago` → `cod_metodo_pago` (01 Efectivo, 02 Tarjeta, 04 Cheque, 05 Transferencia, 06 Vales, 09 Chivo Wallet, 11 Bitcoin).
- Cliente `tipo` es `Persona` o `Empresa`; CCF del DTE usa `cliente.nit` y `cliente.ncr` sin mirar `tipo`.
