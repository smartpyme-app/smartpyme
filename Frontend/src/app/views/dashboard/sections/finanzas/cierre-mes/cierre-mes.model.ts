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

export interface CierreChecklistGrupoItem {
  titulo: string;
  detalle?: string;
  estado: CierreChecklistEstado;
  monto?: number | null;
  accion?: string;
  tipoGasto?: string;
  concepto?: string;
  montoSugerido?: number | null;
}

export interface CierreChecklistGrupo {
  registrados: number;
  total: number;
  montoTotal: number;
  items: CierreChecklistGrupoItem[];
}

export interface CierreChecklistGrupos {
  fijos: CierreChecklistGrupo;
  variables: CierreChecklistGrupo;
  otros: CierreChecklistGrupoItem[];
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

export interface CierreResumenVar {
  label: string;
  valor: number | null;
}

export interface CierreMesResumen {
  ventas: {
    monto: number;
    vsMesAnt: CierreResumenVar;
    vsAnioAnt: CierreResumenVar;
    vsPresupuesto: CierreResumenVar;
  };
  gastos: {
    monto: number;
    vsMesAnt: CierreResumenVar;
    vsAnioAnt: CierreResumenVar;
    vsPresupuesto: CierreResumenVar;
  };
  rentabilidad: {
    margenPct: number;
    utilidad: number;
    vsMesAnt: CierreResumenVar;
    vsAnioAnt: CierreResumenVar;
    vsPresupuesto: CierreResumenVar;
  };
  flujo: {
    neto: number;
    saldo: number | null;
    saldoFecha: string;
    entro: number;
    salio: number;
    flujoAntLabel: string;
    flujoAnt: number;
  };
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

export interface CierreTopVendedorItem {
  nombre: string;
  sucursal: string;
  monto: number;
  deltaPct: number | null;
  pctDelMes: number;
  pctBarra: number;
}

export interface CierreTopProductoItem {
  nombre: string;
  categoria: string;
  monto: number;
  margenPct: number;
  pctDelMes: number;
  pctBarra: number;
}

export interface CierreTopVentas {
  mesLabel: string;
  mesAnteriorLabel: string;
  vendedores: CierreTopVendedorItem[];
  productos: CierreTopProductoItem[];
}

export interface CierreMesDetalle {
  mesTitulo: string;
  mesCertificar: string;
  mesEtiquetaCorto: string;
  checklist: CierreChecklistItem[];
  checklistGrupos: CierreChecklistGrupos | null;
  bloqueado: boolean;
  puedeCertificar: boolean;
  resumen: CierreMesResumen | null;
  presupuesto: CierrePresupuestoFila[];
  impuestos: {
    total: number;
    vencimiento: string;
    items: CierreImpuestoItem[];
  };
  gastosFueraRango: CierreGastosFueraRango;
  topVentas: CierreTopVentas;
}

export interface CierreMesVista {
  anio: number;
  meses: CierreMesItem[];
  mesActivoId: number | null;
  mesesCerrados: number;
  totalMeses: number;
  detalle: CierreMesDetalle | null;
}
