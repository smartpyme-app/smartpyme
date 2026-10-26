export interface ModeladoConfiguradoDetalle {
  clasificacionPct: number;
  calendarioPct: number;
  presupuestoPct: number;
  efectivoDias: number;
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
  categoria: string;
  montoMensual: number;
  tipo: ModeladoGastoTipo | null;
  nuevo?: boolean;
}

export interface ModeladoPuntoEquilibrio {
  costosFijosMes: number;
  gastosVariablesPctVentas: number;
  costoVentasPctVentas: number;
  margenContribucionPct: number;
  ventasMinimasMes: number;
  ventasPromedio3Meses: number;
  margenSeguridadPct: number;
  sinClasificarMonto: number;
}

export interface ModeladoMock {
  configuradoPct: number;
  configuradoDetalle: ModeladoConfiguradoDetalle;
  gastoSinCalendarioMensual: number;
  perfilFiscal: {
    pais: string;
    moneda: string;
    ivaPct: number;
    pagoCuentaPct: number;
    vencimientoDiaHabil: number;
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
  efectivoHoy: number;
  efectivoFecha: string;
  colchonMinimo: number;
  costoCapitalPctMes: number;
  escenarioConservadorPct: number;
  escenarioOptimistaPct: number;
  historial: {
    ventasContadoPct: number;
    diasCobro: number;
    diasPago: number;
    ritmoVsPresupuesto3mPct: number;
  };
}

export type ModeladoPresupuestoVista = 'empresa' | 'sucursales' | 'areas';
export type ModeladoPresupuestoReparto = 'estacionalidad' | 'igual';

export interface ModeladoPresupuestoBloque {
  mesColumnaLabel: string;
  estacionalidadMeses: ModeladoEstacionalidadMes[];
  filas: ModeladoPresupuestoFila[];
  utilidadOperativa: {
    monto: number;
    pctVentas: number;
  };
  metaVentas: {
    anio: number;
    vsAnioAnteriorPct: number;
    avancePct: number;
    avanceEsperadoPct: number;
    montoAnual: number;
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
  vsPresupuestoPct: number;
}

export type ModeladoCalendarioOrigen = 'Planillas' | 'Préstamos';

export interface ModeladoCalendarioCostoFila {
  id: string;
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
  nombre: string;
  detalle?: string;
  monto: number;
  frecuencia: string;
  dia: number;
  nuevo?: boolean;
}
