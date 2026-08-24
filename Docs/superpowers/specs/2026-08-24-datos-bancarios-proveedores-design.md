# Diseño: Datos bancarios de proveedores

**Fecha:** 2026-08-24  
**Estado:** Aprobado (pendiente revisión de este archivo)  
**Ticket:** [SP-2123](https://smartpyme.atlassian.net/browse/SP-2123)  
**Cliente:** VALDES, SUAREZ & VELASCO, LIMITADA (empresa 799)  
**Tipo:** Mejora (misma entidad Proveedor; columnas nuevas + UI + Excel)

---

## 1. Problema

Hoy el proveedor no guarda cuenta bancaria. El cliente necesita registrarla como en Empleados, usarla después en pagos, y verla en el Excel de Compras totales junto con la forma de pago de la compra.

Empleados ya tiene: `banco`, `tipo_cuenta`, `numero_cuenta`, `titular_cuenta`, `forma_pago`. Proveedores no.

## 2. Objetivos

1. Apartado **Información Bancaria** en crear/editar proveedor (pantalla y modal).
2. Una sola cuenta por proveedor, mismos 5 campos que Empleados.
3. Validación **todo o nada** del bloque de cuenta.
4. Importación Excel de proveedores (personas y empresas) con esas columnas.
5. Excel **Compras totales** con forma de pago de la compra y datos bancarios del proveedor.

## 3. Fuera de alcance

- Pagos masivos Banco Agrícola ([SP-2140](https://smartpyme.atlassian.net/browse/SP-2140)).
- Varias cuentas por proveedor.
- PDF de compra, Cuentas por pagar, historial/detalle/categorías de reportes.
- Catálogo de bancos; `banco` sigue siendo texto libre.
- Extraer un componente Angular compartido (el formulario ya está duplicado pantalla/modal).
- Cambiar validación de Empleados.

## 4. Decisiones

| Tema | Decisión |
|------|----------|
| Almacenamiento | Columnas en `proveedores` (igual que `empleados`) |
| Cardinalidad | Una cuenta por proveedor |
| Campos | `banco`, `tipo_cuenta`, `numero_cuenta`, `titular_cuenta`, `forma_pago` |
| `tipo_cuenta` | `Ahorro` \| `Corriente` |
| `forma_pago` del proveedor | `Transferencia` \| `Cheque` \| `Efectivo` (valores iguales a Empleados) |
| Validación | Bloque opcional. Si cualquiera de los 5 tiene valor, son obligatorios `banco`, `tipo_cuenta`, `numero_cuenta` y `titular_cuenta`. `forma_pago` sigue opcional |
| Tipos de proveedor | Persona, Empresa y Extranjero |
| Superficies de captura | Pantalla `/proveedor`, modal `crear-proveedor`, import Excel |
| Reporte | Solo Excel Compras totales (`ComprasExport`) |
| Columnas en ese Excel | Siempre presentes; vacías si el proveedor no tiene cuenta |
| Alcance producto | Todas las empresas, no solo 799 |

## 5. Modelo de datos

Migración `add_datos_bancarios_to_proveedores_table`:

| Columna | Tipo | Null |
|---------|------|------|
| `banco` | string | sí |
| `tipo_cuenta` | string | sí |
| `numero_cuenta` | string | sí |
| `titular_cuenta` | string | sí |
| `forma_pago` | string | sí |

Agregar los cinco al `$fillable` de `App\Models\Compras\Proveedores\Proveedor`.

No hay tabla hija. No hay JSON.

## 6. Validación

Misma regla en API (`StoreProveedorRequest`) e importadores Excel.

Bloque vacío (todos null/blancos) → válido.

Si **cualquiera** de los cinco viene con texto (tras trim):

- `banco`: required, string, max 255
- `tipo_cuenta`: required, `in:Ahorro,Corriente`
- `numero_cuenta`: required, string, max 50 (igual que Empleados)
- `titular_cuenta`: required, string, max 255
- `forma_pago`: nullable, `in:Transferencia,Cheque,Efectivo`

Implementación Laravel: `required_with` cruzado entre los cuatro de cuenta y `forma_pago` (si solo llega `forma_pago`, los otros cuatro fallan). Trim en `prepareForValidation` de los cinco.

HTTP 422 con errores por campo. El formulario ya muestra `alertService.error`. En importación, error por fila como el resto de columnas.

## 7. UI

Copiar controles de `administrar-empleado.component.html` (tab bancaria):

- Banco: input texto
- Tipo de cuenta: select Ahorro / Corriente
- Número de cuenta: input texto
- Titular de la cuenta: input texto
- Forma de pago: select Transferencia bancaria / Cheque / Efectivo (`value` Transferencia, Cheque, Efectivo)

Ubicación: **Información Bancaria** después de contabilidad (si aplica) y antes del botón Guardar. Visible para Persona, Empresa y Extranjero.

Archivos:

- `Frontend/src/app/views/compras/proveedores/proveedor/proveedor.component.html`
- `Frontend/src/app/shared/modals/crear-proveedor/crear-proveedor.component.html`

El `store('proveedor', this.proveedor)` ya manda el objeto completo; no hace falta armar payload a mano si los `[(ngModel)]` apuntan a esas claves.

No hay pestañas nuevas (el formulario de proveedor es un scroll, no tabs como Empleados).

## 8. Importación y plantillas Excel

Columnas al **final** de personas y empresas, encabezados que slugean a los nombres de columna:

| Encabezado | Slug Maatwebsite |
|------------|------------------|
| Banco | `banco` |
| Tipo_cuenta | `tipo_cuenta` |
| Numero_cuenta | `numero_cuenta` |
| Titular_cuenta | `titular_cuenta` |
| Forma_pago | `forma_pago` |

Archivos a tocar:

- `Backend/app/Imports/ProveedoresPersonas.php`
- `Backend/app/Imports/ProveedoresEmpresas.php`
- `Backend/app/Exports/ProveedoresPersonasPlantillaExport.php`
- `Backend/app/Exports/ProveedoresEmpresasPlantillaExport.php`
- `Backend/app/Exports/ProveedoresPersonasExport.php` (export del listado: round-trip)
- `Backend/app/Exports/ProveedoresEmpresasExport.php`
- Plantillas estáticas en `Backend/public/docs/`:
  - `proveedores-personas-format.xlsx` y `-format-hn.xlsx` / `-format-cr.xlsx`
  - `proveedores-empresas-format.xlsx` y `-format-hn.xlsx` / `-format-cr.xlsx`

El modal de import usa esos `/docs/` (SV por defecto, HN si el país es Honduras). CR existe en disco aunque el import aún no la selecciona; se actualiza igual para no dejar plantillas viejas.

Valores de `Tipo_cuenta` y `Forma_pago` iguales a los del formulario. Misma regla todo o nada por fila.

## 9. Excel Compras totales

`Backend/app/Exports/ComprasExport.php`, usado al descargar **Compras totales** desde Compras.

Columnas nuevas al final, siempre:

1. Forma de pago → `compra.forma_pago` (la de la compra, no la del proveedor)
2. Banco → `proveedor.banco`
3. Tipo de cuenta → `proveedor.tipo_cuenta`
4. Número de cuenta → `proveedor.numero_cuenta`
5. Titular → `proveedor.titular_cuenta`

Si no hay proveedor o no hay cuenta, celdas vacías.

Cargar `proveedor` con `with('proveedor')` en `collection()` y leer `$row->proveedor` en `map()` (hoy hace `proveedor()->pluck()` por fila).

No filtrar filas por forma de pago. El filtro que ya existe en Compras sigue igual.

## 10. Pruebas

Un archivo de request y uno de export, al estilo `StoreGastoRequestTest` / `LibroComprasExportTest`:

1. `StoreProveedorRequest`: bloque vacío pasa; solo `numero_cuenta` falla (faltan banco, tipo, titular); los cuatro de cuenta llenos pasan aunque `forma_pago` vaya vacío; `tipo_cuenta` inválido falla.
2. `ComprasExport`: `headings()` incluye las cinco columnas nuevas; `map()` de una compra con proveedor con cuenta las rellena; sin cuenta, vacías.

Sin test e2e Angular. Specs existentes del modal/pantalla no se obligan a reescribir salvo que el compile se rompa.

## 11. Flujo

```
Formulario / modal / Excel import
        → StoreProveedorRequest o rules() del import
        → proveedores (5 columnas)
        → ComprasExport lee compra.forma_pago + proveedor.*
```

SP-2140 podrá leer las mismas columnas más adelante; este ticket no genera archivo bancario.

## 12. Criterios de aceptación (SP-2123)

- Apartado de datos bancarios en el registro de proveedores (pantalla, modal, import).
- Campos requeridos validados (todo o nada del bloque de cuenta).
- Excel de Compras totales muestra forma de pago de la compra y cuenta del proveedor.
