# Auxiliar de cuentas (módulo Contabilidad)

Tab **Auxiliar de cuentas** en el modal *Generar reporte contable* de Partidas. Es el detalle del mayor de una cuenta (o del árbol si se elige un padre): saldo inicial, movimientos y saldo corrido. No es un auxiliar de terceros (cliente/proveedor).

## Decisiones cerradas

| Tema | Decisión |
|------|----------|
| Dónde | Mismo modal de reportes en Partidas; tab nuevo debajo de Libro diario mayor |
| Qué es | Detalle del mayor por cuenta, no CxC/CxP/bancos ni cruce con cliente/proveedor |
| Cuenta | Obligatoria. No existe “Todas las cuentas” |
| Padre | Incluye la cuenta elegida y **todas las descendientes** (`id_cuenta_padre` recursivo) |
| Layout | Una tabla por cuenta con movimiento (igual que el mayor). No un saldo corrido mezclado |
| Motor | Reutilizar `construirLibroDiarioMayor`; el mayor actual no cambia (sigue filtrando por `id` exacto) |
| Salida | PDF y Excel, mismo permiso/sesión que el resto de reportes del modal |

## Qué ve el usuario

Campos: Del, Al, tipo (PDF/Excel), cuenta (buscable por código o nombre, sin opción “Todas”).

Documento titulado **Libro auxiliar**. Por cada cuenta del conjunto (hoja o hijas del padre) con movimiento en el período:

- Encabezado: código y nombre
- Fila de saldo inicial
- Filas: partida, correlativo, fecha, concepto, cargo, abono, saldo corrido
- Total por cuenta (cargo, abono, saldo final)

Naturaleza deudora/acreedora, partidas `Aplicada`/`Cerrada`, saldos iniciales y período: **igual que el libro diario mayor**.

Si no hay movimientos en el conjunto, el PDF/Excel sale vacío (mismo criterio que el mayor).

## Filtro de cuentas

1. La ruta no acepta `all` ni cuenta vacía → **422**.
2. Resolver el conjunto: `id` elegido ∪ descendientes por `id_cuenta_padre` (hijos, nietos, etc.) de la misma empresa.
3. Incluir la padre si ella misma tiene partidas (raro; las que aceptan datos suelen ser hojas).
4. Orden de tablas: código de catálogo, como el mayor.

Ejemplo: elige “Bancos” → tablas de Banco Agrícola, Banco América, etc. que hayan movido en el rango. Elige una hoja → una sola tabla, igual que filtrar esa cuenta en el mayor.

## Arquitectura

- Ruta nueva, mismo grupo `reports.no_cache`:  
  `GET /api/reportes/libro/auxiliar/{fecha_inicio}/{fecha_fin}/{cuenta}/{type}`  
  `{cuenta}` es el `id` de `catalogo_cuentas` (igual que diario/mayor), no el código.
- `construirLibroDiarioMayor` acepta el filtro como conjunto de ids. El mayor pasa `[id]`. El auxiliar resuelve el árbol y pasa esos ids. Un id inexistente o de otra empresa → 422.
- Vistas existentes del mayor (`libro_diario_mayor`, excel `libro_diario_mayor_excel`) y `DiarioMayorExport`: aceptan `titulo` opcional; default = título actual del mayor. El auxiliar pasa `Libro auxiliar`. **No** duplicar blades ni clase Export.
- Frontend (`partidas`): tab + `imprimirAuxiliarCuentas()`. Campo propio `cuenta_auxiliar` (no reutilizar `tipo_cuenta` en `'all'`). Opciones = catálogo sin “Todas las cuentas”. Fechas y tipo de descarga se siguen compartiendo con el resto del modal.

No reactivar `generarRepMovCuenta` ni las plantillas huérfanas `libro_diario_auxiliar*`.

## Fuera de alcance

- Auxiliar por cliente, proveedor o banco operativo
- Cambiar Libro diario, Libro diario mayor o Balance de comprobación
- Incluir partidas que no sean Aplicada/Cerrada
- Saldo único consolidado del padre
- Permiso nuevo (usa el mismo acceso al modal de partidas)

## Prueba mínima

Un test de feature (o unitario del conjunto de ids) que falle si se rompe lo siguiente:

- Sin cuenta / `all` → 422
- Cuenta hoja → mismos `id_cuenta` que el mayor para ese id
- Cuenta padre → incluye movimientos de al menos una hija
- Mayor con el id del padre **no** cambia (sigue siendo match exacto)
