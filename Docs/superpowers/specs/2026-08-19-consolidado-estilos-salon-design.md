# Consolidado Estilo’s Salón (SP-1864)

Reporte custom de ventas comisionables (Productos 100%, Servicios 90%) para las razones sociales de Estilo’s Salón. Correo automático por cron y descarga manual independiente de Reportes automáticos.

## Envío automático

Siempre del día 1 al corte. Se envía el mismo día que cierra el rango:

| Mes | Días de envío |
|---|---|
| 30 días | 7, 15, 22, 30 |
| 31 días | 8, 15, 23, 31 |
| Febrero | 6, 15, 21, 28 |

Febrero 29 (bisiesto): no se envía. El último correo de febrero es el 28 (1 al 28).

El comando `reporte:ventas-por-categoria-sucursal` corre diario a las 08:00 y no envía si no es día de corte. `--inicio` / `--fin` / `--dry-run` siguen forzando generación.

## Descarga manual

Componente standalone (`app-consolidado-estilos-salon`) enchufado hoy en Cierre de caja. Visible solo si `id_empresa` está en la lista de las 11 razones sociales.

Al abrir: Del = 1 del mes, Al = hoy. Ambas fechas son editables. Descarga el mismo Excel del cron (una hoja por empresa). No envía correo.

## Límites

- No se mezcla con Reportes automáticos.
- El componente no conoce el corte: se puede mover a otra vista.
- Lista de empresas: constante única en backend (`EstilosSalonPeriodo::EMPRESAS_IDS`).
