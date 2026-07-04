import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from '@services/api.service';

export interface AuditoriaFiltros {
  module?: string;
  user_id?: number;
  id_empresa?: number;
  fecha_inicio?: string;
  fecha_fin?: string;
  page?: number;
  paginate?: number;
}

@Injectable({ providedIn: 'root' })
export class AuditoriaService {
  constructor(private api: ApiService) {}

  listTenant(filtros: AuditoriaFiltros = {}): Observable<any> {
    return this.api.getAll('auditoria', filtros);
  }

  listPlatform(filtros: AuditoriaFiltros = {}): Observable<any> {
    return this.api.getAll('admin-auditoria', filtros);
  }
}
