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

export interface PlGastoPctVentaFila {
  concepto: string;
  pctMes: number;
  promedio6m: number;
  deltaPts: number;
  barTone: PlGastoPctBarTone;
  tendenciaAlcista: boolean;
  tendencia: number[];
}

export interface PlGastosPctVentasMock {
  filas: PlGastoPctVentaFila[];
}

export type PlKpiTono = 'rojo' | 'azul' | 'naranja';

export interface PlKpi {
  titulo: string;
  valor: string;
  tono: PlKpiTono;
  sublinea?: string;
}
