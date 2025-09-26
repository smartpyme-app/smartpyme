import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { ApiService } from './api.service';
import { 
  TipoClienteEmpresa, 
  TipoClienteBase, 
  CreateTipoClienteRequest, 
  UpdateTipoClienteRequest,
  ApiResponse,
  PaginatedResponse 
} from '../models/fidelizacion.interface';

// Interfaces para clientes con fidelización
export interface ClienteFidelizacion {
  id: number;
  nombre: string;
  correo: string;
  telefono: string;
  dui?: string;
  ncr?: string;
  tipo: string;
  enable: boolean;
  tipo_cliente_fidelizacion?: TipoClienteEmpresa;
  puntos_acumulados?: number;
  puntos_disponibles?: number;
  puntos_vencidos?: number;
  ultima_compra?: string;
  total_compras?: number;
  total_gastado?: number;
  nivel_actual?: number;
  fecha_registro?: string;
  fecha_ultima_actividad?: string;
}

export interface ClienteDetalles {
  id: number;
  nombre: string;
  correo: string;
  telefono: string;
  dui?: string;
  ncr?: string;
  tipo: string;
  enable: boolean;
  tipo_cliente_fidelizacion?: TipoClienteEmpresa;
  puntos_acumulados?: number;
  puntos_disponibles?: number;
  puntos_vencidos?: number;
  puntos_por_ganar?: number;
  ultima_compra?: string;
  total_compras?: number;
  total_gastado?: number;
  nivel_actual?: number;
  fecha_registro?: string;
  fecha_ultima_actividad?: string;
}

export interface HistorialPunto {
  id: number;
  fecha: string;
  descripcion: string;
  puntos: number;
  tipo: 'ganado' | 'canjeado' | 'ajustado' | 'vencido' | 'otro';
  referencia?: string;
  monto_asociado?: number;
  fecha_expiracion?: string;
}

export interface Beneficio {
  id: number;
  nombre: string;
  descripcion: string;
  puntos_requeridos: number;
  descuento_porcentaje?: number;
  descuento_monto?: number;
  disponible: boolean;
}

// Interfaces para el nuevo sistema de canje FIFO
export interface PuntosDisponiblesInfo {
  puntos_disponibles: number;
  puntos_totales_ganados: number;
  puntos_totales_canjeados: number;
  ganancias_detalle: GananciaDetalle[];
  configuracion?: ConfiguracionCliente;
  error?: string;
}

export interface ConfiguracionCliente {
  valor_punto: number;
  minimo_canje: number;
  maximo_canje: number;
  tipo_cliente: string;
  nivel: number;
  puntos_por_dolar?: number;
}

export interface GananciaDetalle {
  id: number;
  puntos_originales: number;
  puntos_disponibles: number;
  fecha_ganancia: string;
  fecha_expiracion: string;
  dias_para_expirar: number;
  venta_id: number;
}


export interface ResultadoCanje {
  success: boolean;
  transaccion_id?: number;
  puntos_canjeados?: number;
  puntos_antes?: number;
  puntos_despues?: number;
  detalles_consumo?: any[];
  mensaje?: string;
  error?: string;
}

export interface HistorialCanje {
  id: number;
  puntos_canjeados: number;
  descripcion: string;
  fecha_canje: string;
  puntos_antes: number;
  puntos_despues: number;
  detalles_consumo: ConsumoDetalle[];
}

export interface ConsumoDetalle {
  ganancia_id: number;
  puntos_consumidos: number;
  fecha_ganancia_original: string;
  fecha_expiracion_original: string;
}

@Injectable({
  providedIn: 'root'
})
export class FidelizacionService {

  constructor(
    private http: HttpClient,
    private apiService: ApiService
  ) { }

  /**
   * Obtener todos los tipos de cliente de la empresa
   */
  getTiposCliente(params?: any): Observable<PaginatedResponse<TipoClienteEmpresa>> {
    let url = `${this.apiService.baseUrl}/api/fidelizacion/tipos-cliente`;
    
    if (params) {
      const queryParams = new URLSearchParams();
      Object.keys(params).forEach(key => {
        if (params[key] !== null && params[key] !== undefined && params[key] !== '') {
          queryParams.append(key, params[key]);
        }
      });
      if (queryParams.toString()) {
        url += `?${queryParams.toString()}`;
      }
    }
    
    // Agregar headers para evitar caché
    const headers = {
      'Cache-Control': 'no-cache, no-store, must-revalidate',
      'Pragma': 'no-cache',
      'Expires': '0'
    };
    
    return this.http.get<PaginatedResponse<TipoClienteEmpresa>>(url, { headers });
  }

  /**
   * Obtener tipos de cliente base disponibles
   */
  getTiposBase(): Observable<ApiResponse<TipoClienteBase[]>> {
    return this.http.get<ApiResponse<TipoClienteBase[]>>(
      `${this.apiService.baseUrl}/api/fidelizacion/tipos-cliente/tipos-base`
    );
  }

  /**
   * Crear nuevo tipo de cliente
   */
  createTipoCliente(data: CreateTipoClienteRequest): Observable<ApiResponse<TipoClienteEmpresa>> {
    return this.http.post<ApiResponse<TipoClienteEmpresa>>(
      `${this.apiService.baseUrl}/api/fidelizacion/tipos-cliente`,
      data
    );
  }

  /**
   * Actualizar tipo de cliente
   */
  updateTipoCliente(id: number, data: UpdateTipoClienteRequest): Observable<ApiResponse<TipoClienteEmpresa>> {
    // Agregar headers para evitar caché
    const headers = {
      'Cache-Control': 'no-cache, no-store, must-revalidate',
      'Pragma': 'no-cache',
      'Expires': '0'
    };
    
    return this.http.put<ApiResponse<TipoClienteEmpresa>>(
      `${this.apiService.baseUrl}/api/fidelizacion/tipos-cliente/${id}`,
      data,
      { headers }
    );
  }

  /**
   * Eliminar tipo de cliente
   */
  deleteTipoCliente(id: number): Observable<ApiResponse<any>> {
    return this.http.delete<ApiResponse<any>>(
      `${this.apiService.baseUrl}/api/fidelizacion/tipos-cliente/${id}`
    );
  }

  /**
   * Cambiar estado activo/inactivo
   */
  toggleStatus(id: number): Observable<ApiResponse<any>> {
    return this.http.patch<ApiResponse<any>>(
      `${this.apiService.baseUrl}/api/fidelizacion/tipos-cliente/${id}/toggle-status`,
      {}
    );
  }

  /**
   * Obtener nombre del nivel
   */
  getNivelNombre(nivel: number): string {
    switch (nivel) {
      case 1:
        return 'Standard';
      case 2:
        return 'VIP';
      case 3:
        return 'Ultra VIP';
      default:
        return 'Personalizado';
    }
  }

  /**
   * Obtener clase CSS para el nivel
   */
  getNivelClass(nivel: number): string {
    switch (nivel) {
      case 1:
        return 'badge bg-secondary';
      case 2:
        return 'badge bg-warning';
      case 3:
        return 'badge bg-danger';
      default:
        return 'badge bg-info';
    }
  }

  /**
   * Validar configuración de puntos
   */
  validatePuntosConfig(data: CreateTipoClienteRequest | UpdateTipoClienteRequest): string[] {
    const errors: string[] = [];

    if (data.puntos_por_dolar <= 0) {
      errors.push('Los puntos por dólar deben ser mayor a 0');
    }

    if (data.minimo_canje <= 0) {
      errors.push('El mínimo de canje debe ser mayor a 0');
    }

    if (data.maximo_canje <= 0) {
      errors.push('El máximo de canje debe ser mayor a 0');
    }

    if (data.minimo_canje >= data.maximo_canje) {
      errors.push('El mínimo de canje debe ser menor al máximo de canje');
    }

    if (data.expiracion_meses <= 0) {
      errors.push('Los meses de expiración deben ser mayor a 0');
    }

    return errors;
  }

  // ===== MÉTODOS PARA CLIENTES CON FIDELIZACIÓN =====

  /**
   * Obtener todos los clientes con información de fidelización
   */
  getClientesFidelizacion(params?: any): Observable<PaginatedResponse<ClienteFidelizacion>> {
    let url = `${this.apiService.baseUrl}/api/fidelizacion/clientes`;
    
    if (params) {
      const queryParams = new URLSearchParams();
      Object.keys(params).forEach(key => {
        if (params[key] !== null && params[key] !== undefined && params[key] !== '') {
          queryParams.append(key, params[key]);
        }
      });
      if (queryParams.toString()) {
        url += `?${queryParams.toString()}`;
      }
    }
    
    return this.http.get<PaginatedResponse<ClienteFidelizacion>>(url);
  }

  /**
   * Obtener clientes por tipo específico
   */
  getClientesPorTipo(tipoId: number, params?: any): Observable<PaginatedResponse<ClienteFidelizacion>> {
    let url = `${this.apiService.baseUrl}/api/fidelizacion/clientes/tipo/${tipoId}`;
    
    if (params) {
      const queryParams = new URLSearchParams();
      Object.keys(params).forEach(key => {
        if (params[key] !== null && params[key] !== undefined && params[key] !== '') {
          queryParams.append(key, params[key]);
        }
      });
      if (queryParams.toString()) {
        url += `?${queryParams.toString()}`;
      }
    }
    
    return this.http.get<PaginatedResponse<ClienteFidelizacion>>(url);
  }

  /**
   * Obtener detalles completos de un cliente
   */
  getClienteDetalles(clienteId: number): Observable<ApiResponse<ClienteDetalles>> {
    return this.http.get<ApiResponse<ClienteDetalles>>(
      `${this.apiService.baseUrl}/api/fidelizacion/clientes/${clienteId}/detalles`
    );
  }

  /**
   * Cambiar tipo de cliente
   */
  cambiarTipoCliente(clienteId: number, tipoId: number): Observable<ApiResponse<any>> {
    return this.http.patch<ApiResponse<any>>(
      `${this.apiService.baseUrl}/api/fidelizacion/clientes/${clienteId}/cambiar-tipo`,
      { id_tipo_cliente: tipoId }
    );
  }

  /**
   * Obtener historial de puntos de un cliente
   */
  getHistorialPuntos(clienteId: number, params?: any): Observable<PaginatedResponse<HistorialPunto>> {
    let url = `${this.apiService.baseUrl}/api/fidelizacion/clientes/${clienteId}/historial-puntos`;
    
    if (params) {
      const queryParams = new URLSearchParams();
      Object.keys(params).forEach(key => {
        if (params[key] !== null && params[key] !== undefined && params[key] !== '') {
          queryParams.append(key, params[key]);
        }
      });
      if (queryParams.toString()) {
        url += `?${queryParams.toString()}`;
      }
    }
    
    return this.http.get<PaginatedResponse<HistorialPunto>>(url);
  }

  /**
   * Obtener beneficios disponibles para un cliente
   */
  getBeneficiosDisponibles(clienteId: number): Observable<ApiResponse<Beneficio[]>> {
    return this.http.get<ApiResponse<Beneficio[]>>(
      `${this.apiService.baseUrl}/api/fidelizacion/clientes/${clienteId}/beneficios-disponibles`
    );
  }

  /**
   * Canjear puntos de un cliente
   */
  canjearPuntos(clienteId: number, data: { puntos: number, descripcion: string }): Observable<ApiResponse<any>> {
    return this.http.post<ApiResponse<any>>(
      `${this.apiService.baseUrl}/api/fidelizacion/clientes/${clienteId}/canjear-puntos`,
      data
    );
  }

  /**
   * Exportar clientes con fidelización
   */
  exportarClientes(params?: any): Observable<ApiResponse<any>> {
    let url = `${this.apiService.baseUrl}/api/fidelizacion/clientes/exportar`;
    
    if (params) {
      const queryParams = new URLSearchParams();
      Object.keys(params).forEach(key => {
        if (params[key] !== null && params[key] !== undefined && params[key] !== '') {
          queryParams.append(key, params[key]);
        }
      });
      if (queryParams.toString()) {
        url += `?${queryParams.toString()}`;
      }
    }
    
    return this.http.get<ApiResponse<any>>(url);
  }

  // ===== MÉTODOS PARA CANJE FIFO =====

  /**
   * Obtener información detallada de puntos disponibles para canje
   */
  getPuntosDisponiblesInfo(clienteId: number, empresaId: number): Observable<ApiResponse<PuntosDisponiblesInfo>> {
    return this.http.post<ApiResponse<PuntosDisponiblesInfo>>(
      `${this.apiService.baseUrl}/api/fidelizacion/canje/puntos-disponibles`,
      { cliente_id: clienteId, empresa_id: empresaId }
    );
  }


  /**
   * Procesar canje de puntos con lógica FIFO
   */
  procesarCanjeFinancial(clienteId: number, empresaId: number, puntosACanjear: number, descripcion?: string): Observable<ApiResponse<ResultadoCanje>> {
    return this.http.post<ApiResponse<ResultadoCanje>>(
      `${this.apiService.baseUrl}/api/fidelizacion/canje/procesar`,
      { 
        cliente_id: clienteId, 
        empresa_id: empresaId, 
        puntos_a_canjear: puntosACanjear,
        descripcion: descripcion 
      }
    );
  }

  /**
   * Obtener historial de canjes de un cliente
   */
  getHistorialCanjes(clienteId: number, empresaId: number, limite?: number): Observable<ApiResponse<HistorialCanje[]>> {
    return this.http.post<ApiResponse<HistorialCanje[]>>(
      `${this.apiService.baseUrl}/api/fidelizacion/canje/historial`,
      { 
        cliente_id: clienteId, 
        empresa_id: empresaId, 
        limite: limite || 20 
      }
    );
  }
}
