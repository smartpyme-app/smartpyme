export interface ModeladoConfiguradoDetalle {
  clasificacionPct: number;
  calendarioPct: number;
  presupuestoPct: number;
  efectivoDias: number | null;
}

export interface ModeladoPlanillaFila {
  concepto: string;
  detalle: string;
  monto: number;
  cuando: string;
  proximo: string;
  alAnio: number;
}

export interface ModeladoPlanillasModulo {
  empleados: number;
  sucursales: number;
  ultima: string;
  filas: ModeladoPlanillaFila[];
}

export interface ModeladoPrestamoFila {
  nombre: string;
  saldo: number;
  tasaAnual: number;
  cuota: number | null;
  diaPago: string;
  cuotasRestantes: number | null;
  interesMes: number | null;
  capitalMes: number | null;
}

export interface ModeladoPrestamosModulo {
  activos: number;
  filas: ModeladoPrestamoFila[];
}

export interface ModeladoModulosExternos {
  tituloSeccion: string;
  planillas: ModeladoPlanillasModulo | null;
  prestamos: ModeladoPrestamosModulo | null;
}

export type ModeladoGastoTipo = 'fijo' | 'variable';

export interface ModeladoGastoFila {
  id: string;
  nombre: string;
  rubro: string;
  categoria: string;
  montoMensual: number;
  tipo: ModeladoGastoTipo | null;
  pctVentas?: number | null;
  nuevo?: boolean;
  origen?: 'planillas' | 'prestamos';
  proveedor?: string;
  sucursal?: string;
  diaPago?: string;
  suspendido?: boolean;
}

export interface ModeladoVista {
  anio: number;
  ejecutadoEtiqueta: string;
  configuradoPct: number;
  configuradoDetalle: ModeladoConfiguradoDetalle;
  gastoSinCalendarioMensual: number;
  perfilFiscal: {
    pais: string;
    moneda: string;
    ivaPct: number | null;
    pagoCuentaPct: number | null;
    vencimiento: string;
    aguinaldoRango: string;
  };
  modulosExternos: ModeladoModulosExternos;
  paso1Titulo: string;
  gastosFijosVariables: ModeladoGastoFila[];
  ventasPromedio3Meses: number;
  paso2Titulo: string;
  calendarioCostosFijos: ModeladoCalendarioCostoFila[];
  calendarioPendiente: ModeladoCalendarioPendiente | null;
  paso3Titulo: string;
  presupuesto: ModeladoPresupuestoBloque;
  paso4Titulo: string;
  parametrosPronostico: ModeladoParametrosPronostico;
}

export interface ModeladoParametrosPronostico {
  efectivoHoy: number | null;
  efectivoFecha: string;
  colchonMinimo: number | null;
  costoCapitalPctMes: number | null;
  escenarioConservadorPct: number;
  escenarioOptimistaPct: number;
  historial: {
    ventasContadoPct: number | null;
    diasCobro: number | null;
    diasPago: number | null;
    ritmoVsPresupuesto3mPct: number | null;
  };
}

export type ModeladoPresupuestoVista = 'empresa' | 'sucursales' | 'departamentos' | 'areas';
export type ModeladoPresupuestoReparto = 'estacionalidad' | 'igual';

export interface ModeladoPresupuestoBloque {
  mesColumnaLabel: string;
  reparto: ModeladoPresupuestoReparto;
  estacionalidadPesos: number[];
  estacionalidadMeses: ModeladoEstacionalidadMes[];
  filas: ModeladoPresupuestoFila[];
  utilidadOperativa: {
    monto: number;
    pctVentas: number | null;
  };
  metaVentas: {
    anio: number;
    vsAnioAnteriorPct: number | null;
    avancePct: number | null;
    avanceEsperadoPct: number | null;
    montoAnual: number;
  };
  sucursales: ModeladoPresupuestoSucursales | null;
  departamentos: ModeladoPresupuestoEntidad | null;
  areas: ModeladoPresupuestoEntidad | null;
}

export interface ModeladoPresupuestoEntidadCol {
  id: number;
  nombre: string;
}

export interface ModeladoPresupuestoEntidadFila {
  id: string;
  categoria: string;
  porCol: Record<string, number>;
  suma: number;
  empresa: number;
}

export interface ModeladoPresupuestoEntidad {
  cols: ModeladoPresupuestoEntidadCol[];
  filas: ModeladoPresupuestoEntidadFila[];
  contribucion: {
    porCol: Record<string, number>;
    suma: number;
    empresa: number;
  };
}

export interface ModeladoPresupuestoSucursalCol {
  id: number;
  nombre: string;
}

export interface ModeladoPresupuestoSucursalFila {
  id: string;
  categoria: string;
  porSucursal: Record<string, number>;
  suma: number;
  empresa: number;
}

export interface ModeladoPresupuestoComparacionSucursal {
  idSucursal: number;
  nombre: string;
  ventasPresup: number;
  ventasReal: number;
  ventasVarPct: number | null;
  contribPresup: number;
  contribReal: number;
  contribVar: number;
  destacar: boolean;
}

export interface ModeladoPresupuestoSucursales {
  sucursales: ModeladoPresupuestoSucursalCol[];
  filas: ModeladoPresupuestoSucursalFila[];
  contribucion: {
    porSucursal: Record<string, number>;
    suma: number;
    empresa: number;
  };
  comparacionMes: {
    titulo: string;
    filas: ModeladoPresupuestoComparacionSucursal[];
  };
}

export interface ModeladoEstacionalidadMes {
  /** 0–100 altura relativa para mini gráfico */
  peso: number;
  destacado?: boolean;
}

export interface ModeladoPresupuestoFila {
  id: string;
  categoria: string;
  anual: number;
  mesProyectado: number;
  ejecutadoMonto: number;
  ejecutadoPresupuesto: number;
  vsPresupuestoPct: number | null;
}

export type ModeladoCalendarioOrigen = 'Planillas' | 'Préstamos';

export interface ModeladoCalendarioCostoFila {
  id: number;
  clave: string;
  nombre: string;
  detalle?: string;
  monto: number;
  bloqueado: boolean;
  origenModulo?: ModeladoCalendarioOrigen;
  frecuencia: string;
  dia: string;
  proximo: string;
  editableMonto: boolean;
  editableDia: boolean;
}

export interface ModeladoCalendarioPendiente {
  clave: string;
  nombre: string;
  monto: number;
  dia: string;
  nuevo?: boolean;
}
