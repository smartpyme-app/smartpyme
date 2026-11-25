export type PlFilaEstilo = 'normal' | 'subtotal' | 'total';

export type PlVistaEstadoId = 'mes' | 'anio_mensual';

export interface PlEstadoResultadosFila {
  lineId?: string;
  concepto: string;
  detalle?: string;
  indent?: boolean;
  estilo?: PlFilaEstilo;
  mes: number;
  pctVentasMes: number | null;
  vsAbs: number;
  vsPct: number;
  acum: number;
  pctVentasAcum: number | null;
  /** 12 montos firmados (ene..dic) cuando vista anual mensual. */
  meses?: number[];
  totalAnual?: number;
}

export type PlGastoPctBarTone = 'orange' | 'blue';

export interface PlGastoProveedor {
  nombre: string;
  monto: number;
  pct: number;
}

export interface PlGastoPctVentaFila {
  concepto: string;
  rubro?: string;
  montoMes?: number;
  pctMes: number;
  pctComparacion: number;
  barTone: PlGastoPctBarTone;
  proveedores?: PlGastoProveedor[];
}

export interface PlGastosPctVentasMock {
  filas: PlGastoPctVentaFila[];
}
