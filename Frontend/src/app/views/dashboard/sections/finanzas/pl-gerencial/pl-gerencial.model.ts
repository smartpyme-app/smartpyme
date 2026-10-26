export type PlFilaEstilo = 'normal' | 'subtotal' | 'total';

export interface PlEstadoResultadosFila {
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
