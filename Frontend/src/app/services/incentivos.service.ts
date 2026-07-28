import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from './api.service';

export interface IncentivosPeriodo {
  inicio: string;
  fin: string;
}

export interface TotalAPagarDesglosado {
  comisiones: number;
  bonos_aprobados_o_pagados: number;
  desglose: true;
}

export interface VendedorIncentivosResumen {
  id_vendedor: number;
  nombre: string;
  total_a_pagar: TotalAPagarDesglosado;
}

export interface VendedorIncentivosDetalle {
  id_vendedor: number;
  periodo: IncentivosPeriodo;
  ventas_por_categoria?: Array<{ id_categoria: number; nombre: string; monto: number }>;
  comisiones?: {
    por_categoria: Array<{ id_categoria: number; nombre: string; monto: number }>;
    por_redencion_gift: number;
    total: number;
  };
  bonos?: Array<{ id_regla: number; nombre: string; monto: number; estado: string }>;
  redenciones_gift?: Array<{ codigo: string; monto: number }>;
  progreso_bono?: Array<{ regla: string; actual: number; meta: number; faltante: number }>;
  total_a_pagar: TotalAPagarDesglosado;
}

export interface VendedorIncentivosListResponse {
  success: boolean;
  periodo: IncentivosPeriodo;
  data: VendedorIncentivosResumen[];
}

export interface VendedorIncentivosDetalleResponse {
  success: boolean;
  data: VendedorIncentivosDetalle;
}

@Injectable({
  providedIn: 'root'
})
export class IncentivosService {
  constructor(private apiService: ApiService) {}

  listarVendedores(desde: string, hasta: string): Observable<VendedorIncentivosListResponse> {
    return this.apiService.getAll('incentivos/vendedores', { desde, hasta });
  }

  detalleVendedor(idVendedor: number, desde: string, hasta: string): Observable<VendedorIncentivosDetalleResponse> {
    return this.apiService.getAll(`incentivos/vendedores/${idVendedor}`, { desde, hasta });
  }
}
