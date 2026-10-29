import { Injectable, inject } from '@angular/core';
import { Observable, of } from 'rxjs';
import { map, timeout } from 'rxjs/operators';
import { ApiService } from '@services/api.service';
import { DashboardAnalyticsApiService } from './dashboard-analytics-api.service';
import {
  ModeladoCalendarioCostoFila,
  ModeladoCalendarioOrigen,
  ModeladoCalendarioPendiente,
  ModeladoGastoFila,
  ModeladoGastoTipo,
  ModeladoPresupuestoReparto,
  ModeladoVista,
} from '../sections/finanzas/modelado/modelado.model';

const RUBRO: Record<string, string> = {
  personal: 'Personal',
  local: 'Local y servicios',
  ventas_marketing: 'Ventas y marketing',
  marketing: 'Ventas y marketing',
  admin: 'Administrativos',
  financieros: 'Financieros',
  costo: 'Costo de ventas',
  ventas: 'Ventas netas',
  otros: 'Otros',
};

const MES = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

export const MODELADO_VACIA: ModeladoVista = {
  anio: new Date().getFullYear(),
  ejecutadoEtiqueta: '',
  configuradoPct: 0,
  configuradoDetalle: {
    clasificacionPct: 0,
    calendarioPct: 0,
    presupuestoPct: 0,
    efectivoDias: null,
  },
  gastoSinCalendarioMensual: 0,
  perfilFiscal: {
    pais: '',
    moneda: '',
    ivaPct: null,
    pagoCuentaPct: null,
    vencimiento: '',
    aguinaldoRango: '',
  },
  modulosExternos: { tituloSeccion: 'Datos que vienen de otros módulos', modulos: [] },
  paso1Titulo: 'Paso 1 — Gastos fijos y variables',
  gastosFijosVariables: [],
  puntoEquilibrio: {
    costosFijosMes: 0,
    gastosVariablesPctVentas: null,
    costoVentasPctVentas: null,
    margenContribucionPct: null,
    ventasMinimasMes: null,
    ventasPromedio3Meses: 0,
    margenSeguridadPct: null,
    sinClasificarMonto: 0,
  },
  paso2Titulo: 'Paso 2 · Calendario de costos fijos',
  calendarioCostosFijos: [],
  calendarioPendiente: null,
  paso3Titulo: 'Paso 3 · Presupuesto',
  presupuesto: {
    mesColumnaLabel: '',
    reparto: 'igual',
    estacionalidadPesos: Array(12).fill(0),
    estacionalidadMeses: [],
    filas: [],
    utilidadOperativa: { monto: 0, pctVentas: null },
    metaVentas: {
      anio: new Date().getFullYear(),
      vsAnioAnteriorPct: null,
      avancePct: null,
      avanceEsperadoPct: null,
      montoAnual: 0,
    },
  },
  paso4Titulo: 'Paso 4 · Parámetros del pronóstico',
  parametrosPronostico: {
    efectivoHoy: null,
    efectivoFecha: '',
    colchonMinimo: null,
    costoCapitalPctMes: null,
    escenarioConservadorPct: -10,
    escenarioOptimistaPct: 12,
    historial: {
      ventasContadoPct: null,
      diasCobro: null,
      diasPago: null,
      ritmoVsPresupuesto3mPct: null,
    },
  },
};

interface ModeladoApi {
  anio?: number;
  configurado?: {
    globalPct?: number;
    clasificacionPct?: number;
    calendarioPct?: number;
    presupuestoPct?: number;
    efectivoDias?: number | null;
  };
  gastoSinCalendarioMensual?: number;
  perfilFiscal?: {
    pais?: string;
    moneda?: string;
    ivaPct?: number | null;
    pagoCuentaPct?: number | null;
    vencimiento?: string;
    aguinaldoRango?: string;
  };
  modulos?: {
    titulo?: string;
    estadoConexion?: string;
    accion?: string;
    lineas?: { etiqueta?: string; valor?: string; valorSecundario?: string }[];
  }[];
  gastos?: {
    id?: string;
    nombre?: string;
    rubro?: string;
    montoMensual?: number;
    tipo?: string | null;
    nuevo?: boolean;
  }[];
  puntoEquilibrio?: {
    costosFijosMes?: number;
    gastosVariablesPctVentas?: number | null;
    costoVentasPctVentas?: number | null;
    margenContribucionPct?: number | null;
    ventasMinimasMes?: number | null;
    ventasPromedio3Meses?: number;
    margenSeguridadPct?: number | null;
    sinClasificarMonto?: number;
  };
  calendario?: {
    id?: number;
    clave?: string;
    nombre?: string;
    detalle?: string;
    monto?: number;
    frecuencia?: string;
    dia?: string;
    proximo?: string;
    origen?: string;
    editable?: boolean;
  }[];
  calendarioPendiente?: { clave?: string; nombre?: string; monto?: number; nuevo?: boolean } | null;
  presupuesto?: {
    mesColumna?: string;
    reparto?: string;
    estacionalidad?: number[];
    ejecutadoDesde?: string;
    ejecutadoHasta?: string;
    filas?: {
      id?: string;
      categoria?: string;
      anual?: number;
      mesProyectado?: number;
      ejecutado?: number;
      presupuestoAcumulado?: number;
      vsPct?: number | null;
    }[];
    utilidadOperativa?: { monto?: number; pctVentas?: number | null };
    metaVentas?: {
      montoAnual?: number;
      vsAnioAnteriorPct?: number | null;
      avancePct?: number | null;
      avanceEsperadoPct?: number | null;
    };
  };
  pronostico?: {
    efectivo?: number | null;
    efectivoFecha?: string;
    colchon?: number | null;
    costoCapitalPctMes?: number | null;
    escenarioConservadorPct?: number;
    escenarioOptimistaPct?: number;
    historial?: {
      ventasContadoPct?: number | null;
      diasCobro?: number | null;
      diasPago?: number | null;
      ritmoVsPresupuesto3mPct?: number | null;
    };
  };
}

@Injectable({ providedIn: 'root' })
export class FinanzasModeladoDataService {
  private readonly analytics = inject(DashboardAnalyticsApiService);
  private readonly api = inject(ApiService);

  obtener(anio: number): Observable<ModeladoVista | null> {
    const empresa = this.analytics.idEmpresa;
    if (!empresa) {
      return of(null);
    }
    const url = `${this.analytics.baseUrl}/api/finanzas/modelado?empresa=${empresa}&anio=${anio}`;
    return this.analytics.get<ModeladoApi>(url).pipe(
      timeout(90_000),
      map((raw) => (raw ? mapModelado(raw, anio) : null)),
    );
  }

  guardarClasificacion(gasto: ModeladoGastoFila, tipo: ModeladoGastoTipo): Observable<unknown> {
    return this.api.putToUrl('finanzas/modelado/clasificacion', {
      clave: gasto.id,
      nombre: gasto.nombre,
      rubro: gasto.rubro || 'otros',
      tipo,
    });
  }

  crearCalendario(body: {
    clave?: string | null;
    nombre: string;
    monto: number;
    frecuencia: string;
    dia: string;
  }): Observable<unknown> {
    return this.api.store('finanzas/modelado/calendario', body);
  }

  actualizarCalendario(fila: ModeladoCalendarioCostoFila): Observable<unknown> {
    return this.api.putToUrl(`finanzas/modelado/calendario/${fila.id}`, {
      clave: fila.clave || null,
      nombre: fila.nombre,
      detalle: fila.detalle || null,
      monto: fila.monto,
      frecuencia: fila.frecuencia,
      dia: String(fila.dia),
    });
  }

  guardarPresupuesto(
    anio: number,
    reparto: ModeladoPresupuestoReparto,
    filas: { id: string; anual: number }[],
    pesos: number[],
  ): Observable<unknown> {
    const estacionalidad =
      reparto === 'estacionalidad'
        ? pesos.length === 12 && pesos.some((p) => p > 0)
          ? pesos
          : Array(12).fill(1)
        : undefined;
    return this.api.putToUrl('finanzas/modelado/presupuesto', {
      anio,
      reparto,
      filas: filas.map((f) => ({ rubro: f.id, anual: Number(f.anual) || 0 })),
      estacionalidad,
    });
  }

  guardarPronostico(p: ModeladoVista['parametrosPronostico']): Observable<unknown> {
    return this.api.putToUrl('finanzas/modelado/pronostico', {
      efectivo: p.efectivoHoy,
      efectivoFecha: p.efectivoFecha || null,
      colchon: p.colchonMinimo,
      costoCapitalPctMes: p.costoCapitalPctMes,
      escenarioConservadorPct: p.escenarioConservadorPct,
      escenarioOptimistaPct: p.escenarioOptimistaPct,
    });
  }
}

function mapModelado(raw: ModeladoApi, anio: number): ModeladoVista {
  const year = raw.anio ?? anio;
  const pesos = (raw.presupuesto?.estacionalidad ?? []).map((n) => Number(n) || 0);
  while (pesos.length < 12) {
    pesos.push(0);
  }
  const reparto: ModeladoPresupuestoReparto =
    raw.presupuesto?.reparto === 'estacionalidad' ? 'estacionalidad' : 'igual';
  const eq = raw.puntoEquilibrio;
  const pron = raw.pronostico;
  const hist = pron?.historial;
  return {
    anio: year,
    ejecutadoEtiqueta: etiquetaRango(raw.presupuesto?.ejecutadoDesde, raw.presupuesto?.ejecutadoHasta),
    configuradoPct: raw.configurado?.globalPct ?? 0,
    configuradoDetalle: {
      clasificacionPct: raw.configurado?.clasificacionPct ?? 0,
      calendarioPct: raw.configurado?.calendarioPct ?? 0,
      presupuestoPct: raw.configurado?.presupuestoPct ?? 0,
      efectivoDias: raw.configurado?.efectivoDias ?? null,
    },
    gastoSinCalendarioMensual: raw.gastoSinCalendarioMensual ?? 0,
    perfilFiscal: {
      pais: raw.perfilFiscal?.pais || '—',
      moneda: raw.perfilFiscal?.moneda || '—',
      ivaPct: raw.perfilFiscal?.ivaPct ?? null,
      pagoCuentaPct: raw.perfilFiscal?.pagoCuentaPct ?? null,
      vencimiento: raw.perfilFiscal?.vencimiento ?? '',
      aguinaldoRango: raw.perfilFiscal?.aguinaldoRango || '—',
    },
    modulosExternos: {
      tituloSeccion: 'Datos que vienen de otros módulos',
      modulos: (raw.modulos ?? []).map((m) => ({
        titulo: m.titulo ?? '',
        estadoConexion: m.estadoConexion ?? '',
        accion: m.accion ?? '',
        lineas: (m.lineas ?? []).map((l) => ({
          etiqueta: l.etiqueta ?? '',
          valor: l.valor ?? '',
          valorSecundario: l.valorSecundario,
        })),
      })),
    },
    paso1Titulo: 'Paso 1 — Gastos fijos y variables',
    gastosFijosVariables: (raw.gastos ?? []).map(mapGasto),
    puntoEquilibrio: {
      costosFijosMes: eq?.costosFijosMes ?? 0,
      gastosVariablesPctVentas: eq?.gastosVariablesPctVentas ?? null,
      costoVentasPctVentas: eq?.costoVentasPctVentas ?? null,
      margenContribucionPct: eq?.margenContribucionPct ?? null,
      ventasMinimasMes: eq?.ventasMinimasMes ?? null,
      ventasPromedio3Meses: eq?.ventasPromedio3Meses ?? 0,
      margenSeguridadPct: eq?.margenSeguridadPct ?? null,
      sinClasificarMonto: eq?.sinClasificarMonto ?? 0,
    },
    paso2Titulo: 'Paso 2 · Calendario de costos fijos',
    calendarioCostosFijos: (raw.calendario ?? []).map(mapCalendario),
    calendarioPendiente: mapPendiente(raw.calendarioPendiente),
    paso3Titulo: `Paso 3 · Presupuesto ${year}`,
    presupuesto: {
      mesColumnaLabel: raw.presupuesto?.mesColumna ?? '',
      reparto,
      estacionalidadPesos: pesos.slice(0, 12),
      estacionalidadMeses: barras(pesos.slice(0, 12)),
      filas: (raw.presupuesto?.filas ?? []).map((f) => ({
        id: f.id ?? '',
        categoria: f.categoria ?? '',
        anual: f.anual ?? 0,
        mesProyectado: f.mesProyectado ?? 0,
        ejecutadoMonto: f.ejecutado ?? 0,
        ejecutadoPresupuesto: f.presupuestoAcumulado ?? 0,
        vsPresupuestoPct: f.vsPct ?? null,
      })),
      utilidadOperativa: {
        monto: raw.presupuesto?.utilidadOperativa?.monto ?? 0,
        pctVentas: raw.presupuesto?.utilidadOperativa?.pctVentas ?? null,
      },
      metaVentas: {
        anio: year,
        vsAnioAnteriorPct: raw.presupuesto?.metaVentas?.vsAnioAnteriorPct ?? null,
        avancePct: raw.presupuesto?.metaVentas?.avancePct ?? null,
        avanceEsperadoPct: raw.presupuesto?.metaVentas?.avanceEsperadoPct ?? null,
        montoAnual: raw.presupuesto?.metaVentas?.montoAnual ?? 0,
      },
    },
    paso4Titulo: 'Paso 4 · Parámetros del pronóstico',
    parametrosPronostico: {
      efectivoHoy: pron?.efectivo ?? null,
      efectivoFecha: pron?.efectivoFecha ?? '',
      colchonMinimo: pron?.colchon ?? null,
      costoCapitalPctMes: pron?.costoCapitalPctMes ?? null,
      escenarioConservadorPct: pron?.escenarioConservadorPct ?? -10,
      escenarioOptimistaPct: pron?.escenarioOptimistaPct ?? 12,
      historial: {
        ventasContadoPct: hist?.ventasContadoPct ?? null,
        diasCobro: hist?.diasCobro ?? null,
        diasPago: hist?.diasPago ?? null,
        ritmoVsPresupuesto3mPct: hist?.ritmoVsPresupuesto3mPct ?? null,
      },
    },
  };
}

function mapGasto(g: NonNullable<ModeladoApi['gastos']>[number]): ModeladoGastoFila {
  const tipo = g.tipo === 'fijo' || g.tipo === 'variable' ? g.tipo : null;
  const rubro = g.rubro ?? '';
  return {
    id: g.id ?? '',
    nombre: g.nombre || g.id || 'Gasto',
    rubro,
    categoria: RUBRO[rubro] || rubro || 'Sin rubro',
    montoMensual: g.montoMensual ?? 0,
    tipo,
    nuevo: g.nuevo === true,
  };
}

function mapCalendario(c: NonNullable<ModeladoApi['calendario']>[number]): ModeladoCalendarioCostoFila {
  const origen = origenModulo(c.origen);
  return {
    id: c.id ?? 0,
    clave: c.clave ?? '',
    nombre: c.nombre ?? '',
    detalle: c.detalle || undefined,
    monto: c.monto ?? 0,
    bloqueado: c.editable === false,
    origenModulo: origen,
    frecuencia: c.frecuencia || 'mensual',
    dia: c.dia ?? '',
    proximo: c.proximo || '—',
    editableMonto: c.editable !== false,
    editableDia: c.editable !== false,
  };
}

function origenModulo(origen?: string): ModeladoCalendarioOrigen | undefined {
  if (origen === 'Planillas' || origen === 'planillas') {
    return 'Planillas';
  }
  if (origen === 'Préstamos' || origen === 'prestamos') {
    return 'Préstamos';
  }
  return undefined;
}

function mapPendiente(
  raw: ModeladoApi['calendarioPendiente'],
): ModeladoCalendarioPendiente | null {
  if (!raw?.nombre && !raw?.clave) {
    return null;
  }
  return {
    clave: raw.clave ?? '',
    nombre: raw.nombre || raw.clave || '',
    monto: raw.monto ?? 0,
    dia: '1',
    nuevo: raw.nuevo === true,
  };
}

function barras(pesos: number[]): { peso: number; destacado?: boolean }[] {
  const max = Math.max(...pesos, 0);
  const mes = new Date().getMonth();
  return pesos.map((p, i) => ({
    peso: max > 0 ? Math.max(8, Math.round((p / max) * 100)) : 40,
    destacado: i === mes,
  }));
}

function etiquetaRango(desde?: string, hasta?: string): string {
  const a = mesDeIso(desde);
  const b = mesDeIso(hasta);
  if (!a || !b) {
    return 'Sin ejecutado';
  }
  return a === b ? a : `${a}–${b}`;
}

function mesDeIso(iso?: string): string {
  const m = Number((iso ?? '').slice(5, 7));
  return m >= 1 && m <= 12 ? MES[m - 1] : '';
}
