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
  salario_base?: number;
  ajuste_salario_minimo?: number;
  total_a_pagar?: number;
  pagado_at: string | null;
  vendedor?: ComisionVendedor;
}

export type ComisionTipoCalculo = 'por_categoria' | 'por_volumen' | 'por_margen';
export type ComisionAlcance = 'global' | 'individual' | 'equipo';
export type ComisionMomento = 'al_pagar' | 'al_facturar' | 'por_abono';

export interface ComisionTramoVolumen {
  umbral: number;
  porcentaje: number;
}

export interface ComisionRegla {
  id: number;
  nombre: string;
  tipo_calculo: ComisionTipoCalculo;
  alcance: ComisionAlcance;
  id_vendedores: number[] | null;
  momento_devengo: ComisionMomento;
  reemplaza_global: boolean;
  config: { porcentaje?: number; salario_base?: number; tramos?: ComisionTramoVolumen[] };
  activo: boolean;
}

export interface ComisionReglaPayload {
  nombre: string;
  tipo_calculo: ComisionTipoCalculo;
  alcance: ComisionAlcance;
  id_vendedores?: number[] | null;
  momento_devengo: ComisionMomento;
  reemplaza_global?: boolean;
  config: ComisionRegla['config'];
  salario_base?: number;
  activo?: boolean;
}

export interface ComisionEstimadoVolumen {
  id_vendedor: number;
  id_regla: number | null;
  monto_base: number;
  porcentaje: number;
  monto: number;
}

export interface ComisionPeriodo {
  id: number;
  fecha_inicio: string;
  fecha_fin: string;
  estado: 'abierto' | 'cerrado' | 'pagado';
  liquidaciones?: ComisionLiquidacion[];
  estimado?: ComisionEstimadoVolumen[];
}

export interface ComisionMovimiento {
  id: number;
  id_vendedor: number;
  id_periodo: number;
  id_categoria: number;
  monto_comision: number;
  monto_base: number;
  porcentaje?: number;
  porcentaje_aplicado?: number;
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
  meta?: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
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

  getReglas(activo?: boolean): Observable<ComisionApiResponse<ComisionRegla[]>> {
    const params = activo !== undefined ? { activo } : {};
    return this.apiService.getAll('comisiones/config/reglas', params);
  }

  crearRegla(payload: ComisionReglaPayload): Observable<ComisionApiResponse<ComisionRegla>> {
    return this.apiService.store('comisiones/config/reglas', payload);
  }

  actualizarRegla(id: number, payload: Partial<ComisionReglaPayload>): Observable<ComisionApiResponse<ComisionRegla>> {
    return this.apiService.update('comisiones/config/reglas', id, payload);
  }

  getCategorias(filtros: Record<string, unknown> = {}): Observable<ComisionApiResponse<ComisionCategoriaConfig[]>> {
    return this.apiService.getAll('comisiones/config/categorias', filtros);
  }

  actualizarCategoria(idCategoria: number, porcentaje: number, idRegla?: number): Observable<ComisionApiResponse<unknown>> {
    const body: Record<string, number> = { porcentaje };
    if (idRegla) {
      body['id_regla'] = idRegla;
    }
    return this.apiService.update('comisiones/config/categorias', idCategoria, body);
  }

  actualizarSubcategoria(idSubcategoria: number, porcentaje: number, idRegla?: number): Observable<ComisionApiResponse<unknown>> {
    const body: Record<string, number> = { porcentaje };
    if (idRegla) {
      body['id_regla'] = idRegla;
    }
    return this.apiService.update('comisiones/config/subcategorias', idSubcategoria, body);
  }

  getPeriodos(estado?: string): Observable<ComisionApiResponse<ComisionPeriodo[]>> {
    const params = estado ? { estado } : {};
    return this.apiService.getAll('comisiones/periodos', params);
  }

  getPeriodo(id: number, estimado = false): Observable<ComisionApiResponse<ComisionPeriodo>> {
    return this.apiService.getAll(`comisiones/periodos/${id}`, estimado ? { estimado: 1 } : {});
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
