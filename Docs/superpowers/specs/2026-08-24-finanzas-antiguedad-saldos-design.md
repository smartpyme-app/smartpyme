# Finanzas: Antigüedad de saldos (CxC / CxP)

Hub de reportería en el submódulo Finanzas: reporte global e individual de antigüedad de saldos para cuentas por cobrar y por pagar, con pantalla, PDF y Excel. El acceso por cliente en Ventas se mantiene (duplicado).

## Objetivo

Cumplir el pedido de mejoras en Finanzas:

1. Reporte de antigüedad de saldos (global a fecha de corte + individual por cliente/proveedor).
2. Estado de cuentas de clientes visible también desde Finanzas.
3. Cuentas por pagar a proveedores en la misma sección.
4. CxC y CxP como **el mismo reporte** (misma UI, mismas columnas, misma información; cambia la fuente).

## Decisiones de producto (cerradas)

| Tema | Decisión |
|------|----------|
| Alcance | Global + individual; CxC y CxP; pantalla + PDF + Excel |
| Ubicación | Finanzas (Admin/Contador) + se mantiene PDF/acceso en Ventas → Clientes |
| Duplicar vs mover | Duplicar acceso; no quitar Ventas/Compras |
| Buckets | Contables: `0-30`, `31-60`, `61-90`, `91+` (misma fórmula que Nota 05 EEFF) |
| Permisos | Solo Administradores y Contadores vía `finanzas.reporteria.ver` |
| Prioridad | Todo el alcance en una entrega |

## Principio de implementación

Respetar diseño, lógica y código existente:

- **No** reescribir ni eliminar `ClientesController::estadoCuenta`, `/clientes/cuentas-cobrar`, `/proveedores/cuentas-pagar`.
- Reutilizar criterios operativos de CxC/CxP actuales para qué documentos entran y cómo se calcula el saldo.
- Reutilizar **solo la fórmula de buckets** de `NotasEstadosFinancierosService::nota05`, no su universo de documentos (Nota 05 incluye estados distintos al listado operativo).
- UI alineada a `cuentas-cobrar` / `cuentas-pagar` (header, modal filtros, tabla, botones Excel/PDF).
- Cablear el permiso `finanzas.reporteria.*` que ya existe en `permissions.php` pero no tiene menú.

## Criterio de saldo (operativo, igual CxC/CxP)

Documentos elegibles:

- Estado `Pendiente`
- `cotizacion = 0` (o null tratado como en CxC actual)
- Scope empresa del usuario / filtros

Saldo por documento:

```
saldo = total - abonos_confirmados - devoluciones_habilitadas
```

Solo documentos con `saldo > 0` (umbral centavos como en el resto del sistema).

Días de antigüedad a fecha de corte:

```
dias = diffInDays(fecha_documento, fecha_corte)
```

Buckets (contables):

| Bucket | Condición |
|--------|-----------|
| `0_30` | `dias <= 30` |
| `31_60` | `dias <= 60` |
| `61_90` | `dias <= 90` |
| `91_mas` | `dias > 90` |

CxC: fuente `Venta` (+ cliente, vendedor como en `VentasController::cxc`).  
CxP: fuente `Compra` (+ proveedor, mismos filtros aplicables; sin vendedor).

## Filtros

- Tipo: `cxc` \| `cxp` (toggle; misma grilla)
- Fecha de corte (obligatoria; default = hoy)
- Empresa
- Sucursal
- Cliente (CxC) / Proveedor (CxP)
- Vendedor (solo CxC; saldos de clientes de ese vendedor, misma lógica `id_vendedor` / detalle que CxC)
- Rangos de antigüedad: incluir/excluir buckets en la salida (columnas y totales)

## Vistas del reporte

### Global

Una fila por cliente (CxC) o proveedor (CxP):

- Nombre / identificación
- `0-30`, `31-60`, `61-90`, `91+`, Total
- Totales de columna al pie

### Individual

Mismos filtros + entidad seleccionada. Filas = documentos abiertos con:

- Fecha, documento/correlativo, vencimiento si aplica, saldo, bucket asignado
- Acción / enlace a PDF individual cuando exista (CxC: reutilizar endpoint actual de estado de cuenta; CxP: generar PDF del mismo servicio de antigüedad filtrado a ese proveedor)

## Salidas

| Canal | Comportamiento |
|-------|----------------|
| Pantalla | Grilla Angular con filtros, paginación o scroll según volumen, toggle CxC/CxP |
| PDF | DomPDF (landscape letter como estado de cuenta clientes); global o individual según filtros |
| Excel | Maatwebsite Export (mismo patrón que `CuentasCobrarExport` / `CuentasPagarExport`) |

Una sola fuente de datos (servicio) alimenta JSON, PDF y Excel.

## Arquitectura técnica

```
FE Finanzas (pantalla)
  → GET /api/.../antiguedad-saldos          (JSON)
  → GET /api/.../antiguedad-saldos/pdf
  → GET /api/.../antiguedad-saldos/excel
       → AntiguedadSaldosService
            → Venta | Compra + abonos/devoluciones
            → buckets + agregación
```

### Backend (nuevo, mínimo)

- `App\Services\Finanzas\AntiguedadSaldosService` (o carpeta Reportes si el proyecto agrupa así reportes financieros)
- Controller delgado (p. ej. bajo `Api\Finanzas` o `Api\Reportes`) que valida request y delega
- Rutas nuevas en módulo finanzas/reportería (no meter lógica en `VentasController` / `ComprasController`)
- Blade PDF nuevo bajo `resources/views/reportes/finanzas/` (no sobrescribir `reportes/clientes/estado-cuenta.blade.php`)
- Export Excel nuevo (clase dedicada; no mutar exports CxC/CxP existentes)

### Frontend (nuevo, mínimo)

- Ruta p. ej. `/finanzas/antiguedad-saldos`
- Componente standalone/módulo siguiendo estilo de `cuentas-cobrar`
- Sidebar Finanzas: ítem “Antigüedad de saldos” si `finanzas.reporteria.ver`
- Sidebar Finanzas: enlaces hermanos a `/clientes/cuentas-cobrar` y `/proveedores/cuentas-pagar` (acceso duplicado; no mover rutas ni quitar Ventas/Compras)

### Permisos y roles

- Menú y endpoints: `finanzas.reporteria.ver` (+ `finanzas.ver` como padre de menú, igual que el resto de Finanzas)
- Asignar en seeder/roles a **Administrador** y **Contador Superior** (`finanzas.reporteria.ver`). Contador Auxiliar: no incluir salvo que el negocio pida lo contrario en una iteración posterior.
- Usuarios de ventas siguen usando el PDF por cliente existente; no requieren `finanzas.reporteria`

## Qué no entra en este cambio

- Reemplazar o rediseñar Nota 05 de estados financieros
- Quitar listados CxC/CxP de Ventas/Compras
- Cambiar buckets del PDF histórico de Ventas (salvo mejora opcional posterior para alinear columnas; fuera del MVP de este spec)
- Dashboard BI / control-cuentas

## Criterios de aceptación

1. Desde Finanzas, Admin/Contador abre antigüedad de saldos; sin permiso no ve el ítem ni la API.
2. Toggle CxC/CxP muestra la misma estructura de columnas con datos coherentes.
3. Fecha de corte + filtros (empresa, sucursal, cliente/proveedor, vendedor en CxC, buckets) alteran pantalla, PDF y Excel de forma consistente.
4. Vista global lista todas las entidades con saldo > 0 a la fecha.
5. Vista individual lista documentos de esa entidad con bucket contable.
6. Export PDF y Excel reflejan los mismos filtros activos.
7. Ventas → Cliente → Estado de cuenta sigue funcionando igual.
8. `/clientes/cuentas-cobrar` y `/proveedores/cuentas-pagar` siguen intactos y además son accesibles desde el menú Finanzas (enlaces duplicados).

## Orden de implementación sugerido

1. Servicio + tests/assert de buckets y saldo con fixtures mínimos.
2. API JSON + permiso en rutas.
3. Pantalla + menú Finanzas.
4. Excel.
5. PDF global/individual.
6. Enlaces duplicados CxC/CxP en menú Finanzas.
7. Seed roles Admin/Contador.
