export type CierreMesEstado = 'cerrado' | 'activo' | 'pendiente';

export type CierreChecklistEstado = 'ok' | 'error' | 'alerta';

export type CierreVarTono = 'neutral' | 'pos' | 'neg' | 'warn';

export type CierrePresupuestoEstado = 'ok' | 'na' | 'warn';

export interface CierreMesItem {
  id: number;
  etiqueta: string;
  estado: CierreMesEstado;
}

export interface CierreChecklistItem {
  estado: CierreChecklistEstado;
  texto: string;
  accion?: string;
}

export interface CierrePresupuestoFila {
  categoria: string;
  presupuesto: number;
  real: number;
  varMesPct: number | null;
  varMesTono: CierreVarTono;
  varAcumMonto: number | null;
  varAcumPct: number | null;
  estado: CierrePresupuestoEstado;
}

export interface CierreImpuestoItem {
  concepto: string;
  monto: number;
}

export interface CierreGastoFueraRangoFila {
  categoria: string;
  mes: number;
  promedio6m: number;
  variacionPct: number;
}

export interface CierreGastosFueraRango {
  umbralPct: number;
  filas: CierreGastoFueraRangoFila[];
}

export interface CierreMesDetalle {
  mesTitulo: string;
  mesCertificar: string;
  mesEtiquetaCorto: string;
  checklist: CierreChecklistItem[];
  puedeCertificar: boolean;
  presupuesto: CierrePresupuestoFila[];
  impuestos: {
    vencimiento: string;
    items: CierreImpuestoItem[];
  };
  gastosFueraRango: CierreGastosFueraRango;
}

export interface CierreMesVista {
  anio: number;
  meses: CierreMesItem[];
  mesActivoId: number | null;
  mesesCerrados: number;
  totalMeses: number;
  detalle: CierreMesDetalle | null;
}
