import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from './api.service';

export interface BonoTramo {
  meta: number;
  bono: number;
}

export interface BonoRegla {
  id: number;
  nombre: string;
  tipo: 'meta_fija' | 'escalonado';
  ventana: string;
  config: { meta?: number; bono?: number; tramos?: BonoTramo[] };
  activo: boolean;
  alcance: 'global' | 'vendedores';
  id_vendedores: number[] | null;
}

export interface BonoGenerado {
  id: number;
  id_vendedor: number;
  id_regla: number;
  periodo_inicio: string;
  periodo_fin: string;
  monto_ventas_base: number;
  monto: number;
  estado: 'pendiente' | 'aprobado' | 'pagado';
  aprobado_at: string | null;
  pagado_at: string | null;
  vendedor?: { id: number; name: string };
  regla?: { id: number; nombre: string };
  aprobado_por?: { id: number; name: string };
}

export interface BonoEvaluacionResumen {
  empresas: number;
  reglas_evaluadas: number;
  vendedores_procesados: number;
  creados: number;
  actualizados: number;
  omitidos_monto: number;
  protegidos: number;
  eliminados?: number;
  periodo?: { inicio: string; fin: string };
}

export interface BonoApiResponse<T> {
  success: boolean;
  data: T;
  message?: string;
}

export interface BonoReglaPayload {
  nombre: string;
  tipo: 'meta_fija' | 'escalonado';
  ventana?: string;
  config: BonoRegla['config'];
  activo?: boolean;
  alcance: 'global' | 'vendedores';
  id_vendedores?: number[] | null;
}

@Injectable({
  providedIn: 'root'
})
export class BonosService {
  constructor(private apiService: ApiService) {}

  getReglas(activo?: boolean): Observable<BonoApiResponse<BonoRegla[]>> {
    const params = activo !== undefined ? { activo } : {};
    return this.apiService.getAll('bonos/reglas', params);
  }

  getRegla(id: number): Observable<BonoApiResponse<BonoRegla>> {
    return this.apiService.read('bonos/reglas', id);
  }

  crearRegla(payload: BonoReglaPayload): Observable<BonoApiResponse<BonoRegla>> {
    return this.apiService.store('bonos/reglas', payload);
  }

  actualizarRegla(id: number, payload: Partial<BonoReglaPayload>): Observable<BonoApiResponse<BonoRegla>> {
    return this.apiService.update('bonos/reglas', id, payload);
  }

  eliminarRegla(id: number): Observable<BonoApiResponse<BonoRegla>> {
    return this.apiService.delete('bonos/reglas', id);
  }

  getGenerados(filtros: Record<string, unknown> = {}): Observable<BonoApiResponse<BonoGenerado[]>> {
    return this.apiService.getAll('bonos/generados', filtros);
  }

  aprobar(id: number): Observable<BonoApiResponse<BonoGenerado>> {
    return this.apiService.store(`bonos/generados/${id}/aprobar`, {});
  }

  pagar(id: number): Observable<BonoApiResponse<BonoGenerado>> {
    return this.apiService.store(`bonos/generados/${id}/pagar`, {});
  }

  descargarComprobante(id: number): Observable<Blob> {
    return this.apiService.export(`bonos/generados/${id}/comprobante`, {});
  }

  evaluar(periodoInicio?: string, periodoFin?: string): Observable<BonoApiResponse<BonoEvaluacionResumen>> {
    const payload: Record<string, string> = {};
    if (periodoInicio) {
      payload['periodo_inicio'] = periodoInicio;
    }
    if (periodoFin) {
      payload['periodo_fin'] = periodoFin;
    }
    return this.apiService.store('bonos/evaluar', payload);
  }
}
