import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from '@services/api.service';

export const AUDITORIA_MODULOS = [
  { value: '', label: 'Todos' },
  { value: 'ventas', label: 'Ventas' },
  { value: 'compras', label: 'Compras' },
  { value: 'cotizaciones', label: 'Cotizaciones' },
  { value: 'gastos', label: 'Gastos' },
  { value: 'inventario', label: 'Inventario' },
  { value: 'servicios', label: 'Servicios' },
  { value: 'ajustes', label: 'Ajustes' },
  { value: 'clientes', label: 'Clientes' },
  { value: 'proveedores', label: 'Proveedores' },
  { value: 'paquetes', label: 'Paquetes' },
  { value: 'pedidos', label: 'Pedidos' },
  { value: 'restaurante', label: 'Restaurante' },
  { value: 'planilla', label: 'Planilla' },
  { value: 'partidas', label: 'Partidas' },
  { value: 'configuraciones', label: 'Configuraciones' },
];

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
