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
    
    return this.http.get<PaginatedResponse<TipoClienteEmpresa>>(url);
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
    return this.http.put<ApiResponse<TipoClienteEmpresa>>(
      `${this.apiService.baseUrl}/api/fidelizacion/tipos-cliente/${id}`,
      data
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
}
