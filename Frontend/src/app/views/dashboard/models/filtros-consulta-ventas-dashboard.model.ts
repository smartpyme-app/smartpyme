/**
 * Parámetros enviados al padre y a `DashboardAnalyticsApiService` para consultas de ventas.
 */
export interface FiltrosConsultaVentasDashboard {
  anio: string;
  mes?: string;
  sucursal?: string | string[];
  estado?: string;
  canal?: string;
  cliente?: string;
  vendedor?: string;
  categoria?: string;
  /** ID(s) de producto: un valor o CSV en query `id_producto`. */
  idProducto?: string | number;
  seccion?: string;
}
