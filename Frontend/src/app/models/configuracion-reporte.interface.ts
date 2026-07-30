export interface ConfiguracionReporte {
    id?: number;
    nombre_reporte?: string;
    tipo_reporte: string;
    frecuencia: 'diario' | 'semanal' | 'mensual';
    /** Período relativo de datos; null/omitido = hoy (compat legacy) */
    periodo?: string;
    destinatarios: string[];
    envio_matutino: boolean;
    hora_matutino: string;
    envio_mediodia: boolean;
    hora_mediodia: string;
    envio_nocturno: boolean;
    hora_nocturno: string;
    dia_mes?: number;
    dias_semana?: number[];
    asunto_correo?: string;
    configuracion: any[];
    sucursales: number[];
    activo: boolean;
    created_at?: string;
    updated_at?: string;
  }

  /** Catálogo UI (8 opciones). Claves alineadas con ReportePeriodo backend. */
  export const PERIODOS_REPORTE: { value: string; label: string }[] = [
    { value: 'hoy', label: 'Del día' },
    { value: 'ultimos3', label: 'Últimos 3 días' },
    { value: 'ultimos7', label: 'Última semana' },
    { value: 'ultimos15', label: 'Últimos 15 días' },
    { value: 'mes', label: 'Mes' },
    { value: 'ultimos3Meses', label: 'Últimos 3 meses' },
    { value: 'ultimos6Meses', label: 'Últimos 6 meses' },
    { value: 'anio', label: 'Año' },
  ];

  /** Labels para configs guardadas con claves fuera del catálogo nuevo. */
  const PERIODOS_LEGACY: Record<string, string> = {
    ayer: 'Ayer',
    semana: 'Esta semana',
    semanaAnterior: 'Semana anterior',
    ultimas2Semanas: 'Últimas 2 semanas',
    mesAnterior: 'Mes anterior',
    trimestre: 'Este trimestre',
    trimestreAnterior: 'Trimestre anterior',
    anioAnterior: 'Año anterior',
  };
  
  export const TIPOS_REPORTE = {
    VENTAS_POR_VENDEDOR: 'ventas-por-vendedor',
    VENTAS_POR_CATEGORIA_VENDEDOR: 'ventas-por-categoria-vendedor',
    ESTADO_FINANCIERO_CONSOLIDADO: 'estado-financiero-consolidado-sucursales',
    DETALLE_VENTAS_VENDEDOR: 'detalle-ventas-vendedor',
    DETALLE_VENTAS_TOTALES: 'detalle-ventas-totales',
    DETALLE_VENTAS_POR_PRODUCTO: 'detalle-ventas-por-producto',
    VENTAS_DIARIAS: 'ventas-diarias',
    PRODUCTOS_VENDIDOS: 'productos-vendidos'
  };
  
  export function crearConfiguracionDefault(): ConfiguracionReporte {
    return {
      activo: true,
      tipo_reporte: '',
      frecuencia: 'diario',
      periodo: 'hoy',
      destinatarios: [],
      envio_matutino: true,
      hora_matutino: '08:00',
      envio_mediodia: false,
      hora_mediodia: '13:00',
      envio_nocturno: false,
      hora_nocturno: '19:00',
      dia_mes: 1,
      asunto_correo: '',
      configuracion: [],
      sucursales: []
    };
  }

  export function labelPeriodo(periodo?: string | null): string {
    if (!periodo) {
      return 'Del día';
    }
    return (
      PERIODOS_REPORTE.find((p) => p.value === periodo)?.label ??
      PERIODOS_LEGACY[periodo] ??
      periodo
    );
  }
