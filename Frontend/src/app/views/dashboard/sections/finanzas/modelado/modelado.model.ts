export interface ModeladoConfiguradoDetalle {
  clasificacionPct: number;
  calendarioPct: number;
  presupuestoPct: number;
  efectivoDias: number | null;
}

export interface ModeladoModuloLinea {
  etiqueta: string;
  valor: string;
  valorSecundario?: string;
}

export interface ModeladoModuloExterno {
  titulo: string;
  estadoConexion: string;
  lineas: ModeladoModuloLinea[];
  accion: string;
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
}

export interface ModeladoPuntoEquilibrio {
  costosFijosMes: number;
  gastosVariablesPctVentas: number | null;
  costoVentasPctVentas: number | null;
  margenContribucionPct: number | null;
  ventasMinimasMes: number | null;
  ventasPromedio3Meses: number;
  margenSeguridadPct: number | null;
  sinClasificarMonto: number;
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
  modulosExternos: {
    tituloSeccion: string;
    modulos: ModeladoModuloExterno[];
  };
  paso1Titulo: string;
  gastosFijosVariables: ModeladoGastoFila[];
  puntoEquilibrio: ModeladoPuntoEquilibrio;
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

export type ModeladoPresupuestoVista = 'empresa' | 'sucursales' | 'areas';
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
