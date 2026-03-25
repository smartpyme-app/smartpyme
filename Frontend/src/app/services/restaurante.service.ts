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

  // Pedidos de canal (Spoties / manual); respuesta paginada (Laravel paginator)
  getPedidos(params?: Record<string, string | number>): Observable<any> {
    return this.api.getAll(BASE + 'pedidos', params || {});
  }

  imprimirPedidoCanal(id: number): Observable<string> {
    return this.api.getAsText(BASE + `pedidos/${id}/imprimir`);
  }

  confirmarPedidoCanal(id: number): Observable<PedidoCanal> {
    return this.api.putToUrl(`restaurante/pedidos/${id}/confirmar`, {});
  }

  anularPedidoCanal(id: number): Observable<PedidoCanal> {
    return this.api.putToUrl(`restaurante/pedidos/${id}/anular`, {});
  }

  prepararFacturaPedidoCanal(id: number): Observable<any> {
    return this.api.store(BASE + `pedidos/${id}/preparar-factura`, {});
  }

  marcarPedidoCanalFacturado(pedidoId: number, ventaId: number): Observable<PedidoCanal> {
    return this.api.putToUrl(`restaurante/pedidos/${pedidoId}/marcar-facturado`, {
      venta_id: ventaId
    });
  }

  getPedido(id: number): Observable<PedidoCanal> {
    return this.api.read(BASE + 'pedidos/', id);
  }

  crearPedido(data: PedidoCanalPayload): Observable<PedidoCanal> {
    return this.api.store(BASE + 'pedidos', data);
  }

  actualizarPedido(id: number, data: Partial<PedidoCanalPayload>): Observable<PedidoCanal> {
    return this.api.update(BASE + 'pedidos', id, data);
  }

  eliminarPedido(id: number): Observable<{ ok: boolean }> {
    return this.api.delete(BASE + 'pedidos/', id);
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

export type PedidoCanalEstado = 'borrador' | 'pendiente_facturar' | 'facturado' | 'anulado';

export interface PedidoCanalDetalle {
  id?: number;
  pedido_id?: number;
  producto_id: number;
  cantidad: number;
  precio: number;
  descuento?: number;
  subtotal?: number;
  total?: number;
  notas?: string;
  producto?: { id: number; nombre?: string; codigo?: string };
}

export interface PedidoCanal {
  id: number;
  id_empresa: number;
  id_sucursal?: number;
  usuario_id: number;
  fecha: string;
  canal?: string;
  referencia_externa?: string;
  estado: PedidoCanalEstado;
  id_venta?: number;
  cliente_id?: number;
  observaciones?: string;
  subtotal: number;
  descuento: number;
  total: number;
  detalles?: PedidoCanalDetalle[];
  cliente?: { id: number; nombre_completo?: string; nombre_empresa?: string };
  usuario?: { nombre?: string; name?: string; email?: string };
}

export interface PedidoCanalPayload {
  fecha: string;
  canal?: string;
  referencia_externa?: string;
  cliente_id?: number;
  observaciones?: string;
  id_sucursal?: number;
  detalles: Array<{
    producto_id: number;
    cantidad: number;
    precio: number;
    descuento?: number;
    notas?: string;
  }>;
}
