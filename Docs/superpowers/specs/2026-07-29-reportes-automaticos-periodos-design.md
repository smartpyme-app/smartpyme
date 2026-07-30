# Reportes automáticos: periodos de fechas simplificados

## Objetivo

Reducir y renombrar las opciones de periodo de datos en reportes automáticos para que sean pocas, claras y orientadas al pasado (lo que el cliente usa de verdad).

## Decisión de cálculo: mixto (C)

- **Rodante (incluye hoy):** Del día, últimos 3 días, última semana, últimos 15 días.
- **Calendario hasta hoy:** Mes, últimos 3 meses, últimos 6 meses, año (desde inicio del mes/año → hoy).

## Catálogo UI (solo estas 8)

| Label | Clave DB | Rango |
|---|---|---|
| Del día | `hoy` | hoy → hoy |
| Últimos 3 días | `ultimos3` | hoy−2 → hoy |
| Última semana | `ultimos7` | hoy−6 → hoy |
| Últimos 15 días | `ultimos15` | hoy−14 → hoy *(nuevo)* |
| Mes | `mes` | 1 del mes actual → hoy |
| Últimos 3 meses | `ultimos3Meses` | 1 del mes−2 → hoy |
| Últimos 6 meses | `ultimos6Meses` | 1 del mes−5 → hoy |
| Año | `anio` | 1 ene → **hoy** |

Default al crear configuración: `hoy`.

## Cambios por capa

### Frontend

- Actualizar `PERIODOS_REPORTE` y `labelPeriodo` en `configuracion-reporte.interface.ts`.
- Quitar UI de expandir/colapsar periodos (`periodosExpandidos` / `toggleMostrarPeriodos`).
- Chips de atajo de fechas (modal config + modal prueba) = solo las 8 opciones.
- Ajustar `seleccionarPeriodo()`: agregar `ultimos15`; `anio` termina en hoy (no 31 dic); eliminar cases solo usados por chips viejos si ya no se invocan (pueden quedar en backend).

### Backend

- `App\Support\ReportePeriodo`:
  - Agregar `ultimos15`.
  - `anio`: fin = hoy (no fin de año).
  - Mantener resolución de claves legacy (`ayer`, `semana`, `mesAnterior`, etc.) para configs ya guardadas.
- Actualizar `Backend/scripts/check_reporte_periodo.php` (incluir `ultimos15` y `anio` → hoy).

### Compatibilidad

- Sin migración de DB.
- Configs con periodos fuera del catálogo nuevo siguen enviándose vía backend.
- En listado FE, `labelPeriodo` muestra label del catálogo nuevo; si es legacy, mapa mínimo de labels o la clave cruda.

## Fuera de alcance

- Frecuencia de envío, horarios, tipos de reporte, templates de mail.
- Migrar/reescribir valores `periodo` ya persistidos.

## Verificación

- Self-check: `php Backend/scripts/check_reporte_periodo.php` (o el runner PHP del proyecto).
- Manual: crear/editar reporte automático → solo 8 periodos; envío programado con `periodo=hoy` y `ultimos15` resuelve fechas correctas.
