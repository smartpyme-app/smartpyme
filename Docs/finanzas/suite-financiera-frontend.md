# Suite Financiera — datos en frontend

## Regla

Cuando una pestaña tenga **API de analytics (sp-analytics) estable y cableada en Angular**:

1. **No** usar fallback a mocks ni datos estáticos en producción.
2. **Eliminar** el archivo `*.mock.ts` de esa sección (o dejar solo fixtures de tests si hiciera falta).
3. Mostrar **cargando**, **error** o **vacío** si la API no responde.

## Estado por pestaña

| Pestaña | Backend | Mocks en UI |
|---------|---------|-------------|
| P&L Gerencial | `GET /api/finanzas/pl-gerencial` | Eliminados |
| Rentabilidad | `GET /api/finanzas/rentabilidad` | Eliminados |
| Flujo de caja | `GET /api/finanzas/flujo-caja` (sucursal compartida con P&L) | `finanzas-flujo-caja-data.service.ts` |
| Cierre de mes | `GET /api/finanzas/cierre-mes` | `finanzas-cierre-mes-data.service.ts` |
| Modelado | `GET /api/finanzas/modelado`. La pestaña guarda en Laravel | `finanzas-modelado-data.service.ts` |
| Alertas y resúmenes | Pendiente | `alertas-*.mock.ts` |

Al cerrar cada backend: borrar mocks, conectar servicio `*-data.service.ts` y actualizar esta tabla.
