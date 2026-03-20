import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from '@services/api.service';

const BASE = 'restaurante/';

export interface Mesa {
  id: number;
  numero: string;
  capacidad: number;
  zona?: string;
  estado: 'libre' | 'ocupada' | 'pendiente_pago' | 'reservada';
  activo: boolean;
  orden: number;
  tiempo_abierta?: string;
  sesion_activa?: any;
  reservas_activas?: Reserva[];
}

export interface SesionMesa {
  id: number;
  mesa_id: number;
  num_comensales: number;
  observaciones?: string;
  estado: string;
  mesa?: Mesa;
  mesero?: any;
  orden_detalle?: any[];
}

@Injectable({ providedIn: 'root' })
export class RestauranteService {
  constructor(private api: ApiService) {}

  getMesas(params?: { id_sucursal?: number; activo?: boolean }): Observable<Mesa[]> {
    return this.api.getAll(BASE + 'mesas', params || {});
  }

  getMesa(id: number): Observable<Mesa> {
    return this.api.read(BASE + 'mesas/', id);
  }

  crearMesa(data: Partial<Mesa>): Observable<Mesa> {
    return this.api.store(BASE + 'mesas', data);
  }

  actualizarMesa(id: number, data: Partial<Mesa>): Observable<Mesa> {
    return this.api.update(BASE + 'mesas', id, data);
  }

  abrirSesion(mesaId: number, data: { num_comensales?: number; observaciones?: string }): Observable<SesionMesa> {
    return this.api.store(BASE + 'sesiones-mesa', { mesa_id: mesaId, ...data });
  }

  getSesion(id: number): Observable<SesionMesa> {
    return this.api.read(BASE + 'sesiones-mesa/', id);
  }

  agregarItem(sesionId: number, data: { producto_id: number; cantidad: number; notas?: string }): Observable<any> {
    return this.api.store(BASE + `sesiones-mesa/${sesionId}/items`, data);
  }

  actualizarItem(sesionId: number, itemId: number, data: { cantidad?: number; notas?: string }): Observable<any> {
    return this.api.update(BASE + `sesiones-mesa/${sesionId}/items`, itemId, data);
  }

  eliminarItem(sesionId: number, itemId: number): Observable<void> {
    return this.api.delete(BASE + `sesiones-mesa/${sesionId}/items/`, itemId);
  }

  enviarComanda(sesionId: number): Observable<any> {
    return this.api.store(BASE + `sesiones-mesa/${sesionId}/comandas`, {});
  }

  solicitarCuenta(sesionId: number): Observable<any> {
    return this.api.store(BASE + `sesiones-mesa/${sesionId}/pre-cuenta`, {});
  }

  dividirCuenta(preCuentaId: number, data: { tipo: 'equitativa' | 'por_items'; num_pagadores: number; asignaciones?: { orden_detalle_id: number; pagador_index: number }[] }): Observable<any[]> {
    return this.api.store(BASE + `pre-cuentas/${preCuentaId}/dividir`, data);
  }

  imprimirPreCuenta(preCuentaId: number): Observable<string> {
    return this.api.getAsText(BASE + `pre-cuentas/${preCuentaId}/imprimir`);
  }

  prepararFactura(preCuentaId: number): Observable<any> {
    return this.api.store(BASE + `pre-cuentas/${preCuentaId}/facturar`, {});
  }

  marcarPreCuentaFacturada(preCuentaId: number, facturaId: number): Observable<{ sesion_cerrada: boolean }> {
    return this.api.putToUrl(`restaurante/pre-cuentas/${preCuentaId}/marcar-facturada`, { factura_id: facturaId });
  }

  getComandas(): Observable<any[]> {
    return this.api.getAll(BASE + 'comandas');
  }

  actualizarEstadoComanda(comandaId: number, estado: 'pendiente' | 'preparando' | 'listo'): Observable<any> {
    return this.api.putToUrl(`restaurante/comandas/${comandaId}/estado`, { estado });
  }

  imprimirComanda(comandaId: number): Observable<string> {
    return this.api.getAsText(BASE + `comandas/${comandaId}/imprimir`);
  }

  // Reservas
  getReservas(params?: { fecha?: string; estado?: string }): Observable<Reserva[]> {
    return this.api.getAll(BASE + 'reservas', params || {});
  }

  crearReserva(data: Partial<Reserva>): Observable<Reserva> {
    return this.api.store(BASE + 'reservas', data);
  }

  cancelarReserva(id: number): Observable<Reserva> {
    return this.api.putToUrl(`restaurante/reservas/${id}/cancelar`, {});
  }

  convertirReservaEnSesion(id: number): Observable<SesionMesa> {
    return this.api.putToUrl(`restaurante/reservas/${id}/convertir-sesion`, {});
  }
}

export interface Reserva {
  id: number;
  mesa_id: number;
  fecha_reserva: string;
  hora_reserva: string;
  cliente_nombre?: string;
  cliente_telefono?: string;
  observaciones?: string;
  estado: 'pendiente' | 'confirmada' | 'cumplida' | 'cancelada' | 'no_show';
  mesa?: Mesa;
}
