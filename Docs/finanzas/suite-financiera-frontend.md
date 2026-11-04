# Suite Financiera — datos en frontend

Visión de producto y rol de cada pestaña: **[suite-financiera-analisis.md](./suite-financiera-analisis.md)**.

## Regla

Cuando una pestaña tenga **API de analytics (sp-analytics) estable y cableada en Angular**:

1. **No** usar fallback a mocks ni datos estáticos en producción.
2. **Eliminar** el archivo `*.mock.ts` de esa sección (o dejar solo fixtures de tests si hiciera falta).
3. Mostrar **cargando**, **error** o **vacío** si la API no responde.

## Filtros compartidos (P&L, Rentabilidad, Flujo)

En `finanzas.component`: **mes**, **año** y **sucursal** viven en la misma barra y se conservan al cambiar de pestaña. **Horizonte** (mes / trimestre / año) aplica a Rentabilidad. El P&L añade periodo (mes vs año completo), base contable y comparación. Flujo solo usa sucursal en el API (proyección desde hoy).

## Estado por pestaña

| Pestaña | Backend | Mocks en UI |
|---------|---------|-------------|
| P&L Gerencial | `GET /api/finanzas/pl-gerencial` | Eliminados |
| Rentabilidad | `GET /api/finanzas/rentabilidad` | Eliminados |
| Flujo de caja | `GET /api/finanzas/flujo-caja` | Eliminados |
| Cierre de mes | `GET /api/finanzas/cierre-mes` | `finanzas-cierre-mes-data.service.ts` |
| Modelado | `GET /api/finanzas/modelado`. La pestaña guarda en Laravel | `finanzas-modelado-data.service.ts` |
| Alertas y resúmenes | Pendiente | `alertas-*.mock.ts` |

Al cerrar cada backend: borrar mocks, conectar servicio `*-data.service.ts` y actualizar esta tabla.
