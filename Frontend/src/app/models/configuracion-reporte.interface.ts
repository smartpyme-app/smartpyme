export interface ConfiguracionReporte {
    id?: number;
    nombre_reporte?: string;
    tipo_reporte: string;
    frecuencia: 'diario' | 'semanal' | 'mensual';
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