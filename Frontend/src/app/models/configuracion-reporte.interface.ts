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

  export const PERIODOS_REPORTE: { value: string; label: string }[] = [
    { value: 'hoy', label: 'Hoy' },
    { value: 'ayer', label: 'Ayer' },
    { value: 'ultimos3', label: 'Últimos 3 días' },
    { value: 'ultimos7', label: 'Últimos 7 días' },
    { value: 'semana', label: 'Esta semana' },
    { value: 'semanaAnterior', label: 'Semana anterior' },
    { value: 'ultimas2Semanas', label: 'Últimas 2 semanas' },
    { value: 'mes', label: 'Este mes' },
    { value: 'mesAnterior', label: 'Mes anterior' },
    { value: 'ultimos3Meses', label: 'Últimos 3 meses' },
    { value: 'ultimos6Meses', label: 'Últimos 6 meses' },
    { value: 'trimestre', label: 'Este trimestre' },
    { value: 'trimestreAnterior', label: 'Trimestre anterior' },
    { value: 'anio', label: 'Este año' },
    { value: 'anioAnterior', label: 'Año anterior' },
  ];
  
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
      return 'Hoy';
    }
    return PERIODOS_REPORTE.find((p) => p.value === periodo)?.label ?? periodo;
  }