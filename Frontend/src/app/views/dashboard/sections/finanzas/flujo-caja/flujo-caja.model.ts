export type FlujoVistaId = 'semanal' | 'mensual';

export interface FlujoCajaKpiResumen {
  puntoBajo4Semanas: number;
  puntoBajo4SemanasSub: string;
  vsColchon: number;
  puntoBajo12Semanas: number;
  puntoBajo12SemanasSub: string;
  cobroNecesarioPct: number;
  metaNecesariaPct: number;
  semanasBajoColchon: number;
  totalSemanasProyeccion: number;
}

export interface FlujoCajaMock {
  resumenEjecutivo: string;
  kpis: FlujoCajaKpiResumen;
  efectivoInicial: number;
  efectivoInicialFecha: string;
  efectivoInicialNota: string;
  colchon: number;
  cicloEfectivoDias: number;
  cicloCobroDias: number;
  cicloInventarioDias: number;
  cicloPagoDias: number;
  cobroCxCHistoricoPct: number;
  metasVentas: { mes: string; monto: number }[];
  metaRitmoPct: number;
  notaBandaEscenarios: string;
  alertaSemanaBajoColchon: string;
  semanas: {
    labels: string[];
    neto: number[];
    saldo: number[];
    saldoBajo: number[];
    saldoAlto: number[];
  };
  semanaCritica: { id: string; saldo: number };
}

export interface FlujoSegmentoBarra {
  etiqueta: string;
  monto: number;
  tono: 'green' | 'orange' | 'yellow' | 'red' | 'blue';
}

export interface FlujoContactoPrioridad {
  nombre: string;
  factura: string;
  diasVencido: number;
  monto: number;
  urgente: boolean;
}

export interface FlujoPagoGrande {
  descripcion: string;
  vence: string;
  monto: number;
  urgente: boolean;
}

export interface FlujoSalidaFija {
  descripcion: string;
  fecha: string;
  monto: number;
}

export interface FlujoPosicionMock {
  cuentasCobrar: {
    total: number;
    segmentos: FlujoSegmentoBarra[];
    llamarPrimero: FlujoContactoPrioridad[];
  };
  cuentasPagar: {
    total: number;
    segmentos: FlujoSegmentoBarra[];
    pagosGrandes: FlujoPagoGrande[];
  };
  salidasFijas: {
    items: FlujoSalidaFija[];
  };
}

export interface FlujoSemanaDetalleFila {
  id: string;
  rango: string;
  inicial: number;
  ventasSinIva: number;
  deContado: number;
  cobros: number | null;
  fijos: number;
  impuestos: number;
  proveedores: number;
  otros: number;
  neto: number;
  final: number;
}

export interface FlujoSemanaDetalleMock {
  filas: FlujoSemanaDetalleFila[];
  notaPie: string;
  pctContado: number;
}

export interface FlujoSemanaMovimiento {
  concepto: string;
  monto: number;
}

export interface FlujoSemanaDesglose {
  id: string;
  rango: string;
  entradas: FlujoSemanaMovimiento[];
  salidas: FlujoSemanaMovimiento[];
  neto: number;
}
