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
| Rentabilidad | Pendiente | `rentabilidad.mock.ts` |
| Flujo de caja | Pendiente | `flujo-caja.mock.ts` |
| Cierre de mes | Pendiente | `cierre-mes.mock.ts` |
| Modelado | Pendiente | `modelado.mock.ts` |
| Alertas y resúmenes | Pendiente | `alertas-*.mock.ts` |

Al cerrar cada backend: borrar mocks, conectar servicio `*-data.service.ts` y actualizar esta tabla.
