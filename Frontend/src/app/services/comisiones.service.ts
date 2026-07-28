import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from './api.service';

export interface ComisionSubcategoriaConfig {
  id_subcategoria: number;
  nombre: string;
  porcentaje: number | null;
  config_id?: number | null;
}

export interface ComisionCategoriaConfig {
  id_categoria: number;
  nombre: string;
  porcentaje: number;
  config_id?: number | null;
  subcategorias: ComisionSubcategoriaConfig[];
}

export interface ComisionVendedor {
  id: number;
  name: string;
  email?: string;
}

export interface ComisionLiquidacion {
  id: number;
  id_vendedor: number;
  id_periodo: number;
  total_comision: number;
  pagado_at: string | null;
  vendedor?: ComisionVendedor;
}

export interface ComisionPeriodo {
  id: number;
  fecha_inicio: string;
  fecha_fin: string;
  estado: 'abierto' | 'cerrado' | 'pagado';
  liquidaciones?: ComisionLiquidacion[];
}

export interface ComisionMovimiento {
  id: number;
  id_vendedor: number;
  id_periodo: number;
  id_categoria: number;
  monto_comision: number;
  monto_base: number;
  porcentaje: number;
  origen: string;
  fecha_evento: string;
  vendedor?: ComisionVendedor;
  categoria?: { id: number; nombre: string };
  subcategoria?: { id: number; nombre: string };
}

export interface ComisionApiResponse<T> {
  success: boolean;
  data: T;
  message?: string;
}

export interface ComisionMovimientosResponse {
  success: boolean;
  data: ComisionMovimiento[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

@Injectable({
  providedIn: 'root'
})
export class ComisionesService {

  constructor(private apiService: ApiService) {}

  getCategorias(): Observable<ComisionApiResponse<ComisionCategoriaConfig[]>> {
    return this.apiService.getAll('comisiones/config/categorias');
  }

  actualizarCategoria(idCategoria: number, porcentaje: number): Observable<ComisionApiResponse<unknown>> {
    return this.apiService.update('comisiones/config/categorias', idCategoria, { porcentaje });
  }

  actualizarSubcategoria(idSubcategoria: number, porcentaje: number): Observable<ComisionApiResponse<unknown>> {
    return this.apiService.update('comisiones/config/subcategorias', idSubcategoria, { porcentaje });
  }

  getPeriodos(estado?: string): Observable<ComisionApiResponse<ComisionPeriodo[]>> {
    const params = estado ? { estado } : {};
    return this.apiService.getAll('comisiones/periodos', params);
  }

  cerrarPeriodo(id: number): Observable<ComisionApiResponse<ComisionPeriodo>> {
    return this.apiService.store(`comisiones/periodos/${id}/cerrar`, {});
  }

  pagarLiquidacion(id: number): Observable<ComisionApiResponse<ComisionLiquidacion>> {
    return this.apiService.store(`comisiones/liquidaciones/${id}/pagar`, {});
  }

  getMovimientos(filtros: Record<string, unknown> = {}): Observable<ComisionMovimientosResponse> {
    return this.apiService.getAll('comisiones/movimientos', filtros);
  }

  exportExcel(desde: string, hasta: string): Observable<Blob> {
    return this.apiService.export('comisiones/export', { desde, hasta });
  }

  descargarComprobante(idVendedor: number, periodoId: number): Observable<Blob> {
    return this.apiService.export(`comisiones/comprobante/${idVendedor}`, { periodo_id: periodoId });
  }
}
