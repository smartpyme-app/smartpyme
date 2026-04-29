import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { Observable, of } from 'rxjs';
import { catchError, map } from 'rxjs/operators';

import { ApiService } from '@services/api.service';
import {
  PedidoCanal,
  PedidoCanalPayload,
  RestauranteService
} from '@services/restaurante.service';
import { AlertService } from '@services/alert.service';

interface LineaLocal {
  producto_id: number;
  nombre: string;
  cantidad: number;
  precio: number;
  descuento: number;
  notas: string;
}

@Component({
  selector: 'app-pedido-form',
  templateUrl: './pedido-form.component.html',
  styleUrls: ['./pedido-form.component.css']
})
export class PedidoFormComponent implements OnInit {
  modoEdicion = false;
  pedidoId: number | null = null;
  guardando = false;
  cargando = false;

  fecha = '';
  canal = '';
  referenciaExterna = '';
  clienteId: number | null = null;
  observaciones = '';
  clientes: any[] = [];

  lineas: LineaLocal[] = [];
  bodegas: any[] = [];
  idBodega: number | null = null;

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private restauranteService: RestauranteService,
    private apiService: ApiService,
    private alertService: AlertService
  ) {}

  ngOnInit(): void {
    const idParam = this.route.snapshot.paramMap.get('id');
    this.modoEdicion = !!idParam;
    if (idParam) {
      this.pedidoId = +idParam;
    }

    this.apiService.getAll('clientes/list').subscribe({
      next: (c) => {
        this.clientes = Array.isArray(c) ? c : [];
      },
      error: () => {
        this.clientes = [];
      }
    });

    this.apiService.getAll('bodegas/list').subscribe({
      next: (b: any) => {
        this.bodegas = Array.isArray(b) ? b : [];
        if (this.apiService.auth_user().tipo != 'Administrador') {
          this.bodegas = this.bodegas.filter(
            (item: any) => item.id_sucursal == this.apiService.auth_user().id_sucursal
          );
        }
      },
      error: () => {
        this.bodegas = [];
      }
    });

    if (this.modoEdicion && this.pedidoId) {
      this.cargarPedido(this.pedidoId);
    } else {
      this.fecha = new Date().toISOString().slice(0, 10);
      this.idBodega = this.apiService.auth_user().id_bodega ?? null;
    }
  }

  cargarPedido(id: number): void {
    this.cargando = true;
    this.restauranteService.getPedido(id).subscribe({
      next: (p: PedidoCanal) => {
        if (p.estado !== 'borrador') {
          this.alertService.warning('No editable', 'Solo se pueden editar pedidos en borrador.');
          this.router.navigate(['/pedidos']);
          return;
        }
        this.fecha = typeof p.fecha === 'string' ? p.fecha.slice(0, 10) : String(p.fecha).slice(0, 10);
        this.canal = p.canal || '';
        this.referenciaExterna = p.referencia_externa || '';
        this.clienteId = p.cliente_id ?? null;
        this.observaciones = p.observaciones || '';
        this.idBodega = p.id_bodega ?? this.apiService.auth_user().id_bodega ?? null;
        this.lineas = (p.detalles || []).map((d) => ({
          producto_id: d.producto_id,
          nombre: d.producto?.nombre || 'Producto #' + d.producto_id,
          cantidad: +d.cantidad,
          precio: +d.precio,
          descuento: +(d.descuento || 0),
          notas: d.notas || ''
        }));
        this.cargando = false;
      },
      error: (err) => {
        this.alertService.error(err);
        this.cargando = false;
        this.router.navigate(['/pedidos']);
      }
    });
  }

  /** Búsqueda remota de clientes (igual que en citas/eventos). */
  searchClientes = (term: string): Observable<any[]> => {
    if (!term || term.length < 2) {
      return of([]);
    }
    return this.apiService.getAll(`clientes/search?q=${encodeURIComponent(term)}`).pipe(
      map((response: any) =>
        Array.isArray(response) ? response : response?.data ?? []
      ),
      catchError(() => of([]))
    );
  };

  getClienteDisplay = (cliente: any): string =>
    cliente?.tipo === 'Empresa' ? cliente.nombre_empresa : cliente.nombre_completo;

  onProductoSelect(producto: any): void {
    const precio = parseFloat(
      producto.precio_publico ?? producto.precio ?? producto.precio_venta ?? 0
    );
    this.lineas.push({
      producto_id: producto.id,
      nombre: producto.nombre || producto.descripcion || 'Producto',
      cantidad: 1,
      precio: precio,
      descuento: 0,
      notas: ''
    });
  }

  quitarLinea(i: number): void {
    this.lineas.splice(i, 1);
  }

  subtotalLinea(l: LineaLocal): number {
    return l.cantidad * l.precio;
  }

  totalLinea(l: LineaLocal): number {
    return Math.max(0, this.subtotalLinea(l) - (l.descuento || 0));
  }

  totalPedido(): number {
    return this.lineas.reduce((s, l) => s + this.totalLinea(l), 0);
  }

  volver(): void {
    this.router.navigate(['/pedidos']);
  }

  guardar(): void {
    if (!this.fecha) {
      this.alertService.warning('Fecha requerida', 'Indique la fecha del pedido.');
      return;
    }
    if (!this.lineas.length) {
      this.alertService.warning('Detalle vacío', 'Agregue al menos un producto.');
      return;
    }

    const detalles = this.lineas.map((l) => ({
      producto_id: l.producto_id,
      cantidad: l.cantidad,
      precio: l.precio,
      descuento: l.descuento || 0,
      notas: l.notas?.trim() || undefined
    }));

    const payload: PedidoCanalPayload = {
      fecha: this.fecha,
      canal: this.canal.trim() || undefined,
      referencia_externa: this.referenciaExterna.trim() || undefined,
      cliente_id: this.clienteId || undefined,
      observaciones: this.observaciones.trim() || undefined,
      id_bodega: this.idBodega ?? undefined,
      detalles
    };

    this.guardando = true;
    const obs = this.modoEdicion && this.pedidoId
      ? this.restauranteService.actualizarPedido(this.pedidoId, payload)
      : this.restauranteService.crearPedido(payload);

    obs.subscribe({
      next: () => {
        this.guardando = false;
        this.alertService.success('Pedido guardado', '');
        this.router.navigate(['/pedidos']);
      },
      error: (err) => {
        this.guardando = false;
        this.alertService.error(err);
      }
    });
  }
}
