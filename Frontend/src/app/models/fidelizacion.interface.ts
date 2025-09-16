export interface TipoClienteBase {
  id: number;
  code: string;
  nombre: string;
  descripcion: string;
  orden: number;
}

export interface TipoClienteEmpresa {
  id: number;
  nivel: number;
  nombre_efectivo: string;
  descripcion_efectiva: string;
  code_efectivo: string;
  activo: boolean;
  puntos_por_dolar: number;
  valor_punto: number;
  minimo_canje: number;
  maximo_canje: number;
  expiracion_meses: number;
  is_default: boolean;
  is_personalizado: boolean;
  nivel_nombre: string;
  configuracion_avanzada: any;
  created_at: string;
  updated_at: string;
  tipo_base?: TipoClienteBase;
}

export interface ReglaUpgrade {
  tipo: string;
  umbral: number;
  nivel_destino?: number;
  periodo_meses?: number;
  descripcion: string;
  activo: boolean;
}

export interface ConfiguracionAvanzada {
  valor_punto: number;
  multiplicador_especial: boolean;
  multiplicador_valor?: number;
  descuento_cumpleanos: boolean;
  descuento_cumpleanos_porcentaje?: number;
  acceso_exclusivo?: boolean;
  soporte_prioritario?: boolean;
  beneficios_exclusivos?: {
    descuento_maximo_adicional?: number;
    puntos_bienvenida_anual?: number;
    acceso_eventos_vip?: boolean;
    entrega_express_gratis?: boolean;
    asistente_personal?: boolean;
  };
  upgrade_automatico: {
    habilitado: boolean;
    reglas: ReglaUpgrade[];
  };
}

export interface CreateTipoClienteRequest {
  id_tipo_base?: number;
  nivel: number;
  nombre_personalizado?: string;
  puntos_por_dolar: number;
  minimo_canje: number;
  maximo_canje: number;
  expiracion_meses: number;
  is_default?: boolean;
  configuracion_avanzada?: ConfiguracionAvanzada;
}

export interface UpdateTipoClienteRequest extends CreateTipoClienteRequest {
  activo?: boolean;
}

export interface ApiResponse<T> {
  success: boolean;
  message?: string;
  data?: T;
  errors?: any;
}

export interface PaginatedResponse<T> {
  success: boolean;
  data: {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
  };
  message?: string;
}
