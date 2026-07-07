import { Component, OnInit, ViewChild } from '@angular/core';
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
import { DistribucionLotesModalComponent } from '@shared/modals/distribucion-lotes/distribucion-lotes-modal.component';
import { textoResumenLotesDetalle } from '@utils/lotes-venta.util';

interface LineaLocal {
  producto_id: number;
  nombre: string;
  cantidad: number;
  precio: number;
  descuento: number;
  notas: string;
  inventario_por_lotes?: boolean;
  lote_id?: number | null;
  lotes_asignados?: any[] | null;
}

@Component({
  standalone: false,
  selector: 'app-pedido-form',
  templateUrl: './pedido-form.component.html',
  styleUrls: ['./pedido-form.component.css']
})
export class PedidoFormComponent implements OnInit {
  modoEdicion = false;
  pedidoId: number | null = null;
  guardando = false;
  cargando = false;
  enviandoComanda = false;

  fecha = '';
  canal = '';
  referenciaExterna = '';
  clienteId: number | null = null;
  observaciones = '';
  clientes: any[] = [];

  lineas: LineaLocal[] = [];
  bodegas: any[] = [];
  idBodega: number | null = null;
  lineaLotesIndex: number | null = null;

  @ViewChild('lotesModal') lotesModal!: DistribucionLotesModalComponent;

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
        this.lineas = (p.detalles || []).map((d: any) => ({
          producto_id: d.producto_id,
          nombre: d.producto?.nombre || 'Producto #' + d.producto_id,
          cantidad: +d.cantidad,
          precio: +d.precio,
          descuento: +(d.descuento || 0),
          notas: d.notas || '',
          inventario_por_lotes: d.producto?.inventario_por_lotes,
          lote_id: d.lote_id ?? null,
          lotes_asignados: (d.lote_asignaciones || []).map((item: any) => ({
            lote_id: item.lote_id,
            numero_lote: item.lote?.numero_lote,
            cantidad: item.cantidad,
          })),
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

  requiereDistribucionLotes(linea: LineaLocal): boolean {
    return !!linea.inventario_por_lotes
      && this.apiService.isLotesActivo()
      && this.apiService.getLotesMetodologia() === 'Manual';
  }

  onProductoSelect(producto: any): void {
    const precio = parseFloat(
      producto.precio ?? producto.precio_publico ?? producto.precio_venta ?? 0
    );
    const linea: LineaLocal = {
      producto_id: producto.id,
      nombre: producto.nombre_mostrar || producto.nombre || producto.descripcion || 'Producto',
      cantidad: 1,
      precio: precio,
      descuento: 0,
      notas: '',
      inventario_por_lotes: producto.inventario_por_lotes,
      lote_id: null,
      lotes_asignados: null,
    };
    this.lineas.push(linea);

    if (this.requiereDistribucionLotes(linea)) {
      setTimeout(() => this.abrirDistribucionLotes(this.lineas.length - 1), 100);
    }
  }

  abrirDistribucionLotes(index: number): void {
    const idBodega = this.idBodega ?? this.apiService.auth_user().id_bodega;
    if (!idBodega) {
      this.alertService.warning('Bodega requerida', 'Seleccione la bodega de inventario del pedido.');
      return;
    }
    this.lineaLotesIndex = index;
    const linea = this.lineas[index];
    this.lotesModal.abrir({
      ...linea,
      id_producto: linea.producto_id,
      nombre_producto: linea.nombre,
    }, idBodega);
  }

  onLotesLineaConfirmados(detalle: any): void {
    if (this.lineaLotesIndex == null) {
      return;
    }
    const linea = this.lineas[this.lineaLotesIndex];
    linea.lotes_asignados = detalle.lotes_asignados;
    linea.lote_id = detalle.lote_id;
    linea.cantidad = detalle.cantidad;
    this.lineaLotesIndex = null;
  }

  textoLotesLinea(linea: LineaLocal): string {
    return textoResumenLotesDetalle(linea);
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

  enviarComanda(): void {
    if (!this.pedidoId) {
      this.alertService.warning('Guarde primero', 'Debe guardar el pedido antes de enviar comanda.');
      return;
    }
    this.enviandoComanda = true;
    this.restauranteService.enviarComandaPedido(this.pedidoId).subscribe({
      next: (res: any) => {
        this.enviandoComanda = false;
        this.alertService.success('Comanda enviada', '');
        const ids = (res?.comandas || []).map((c: any) => c?.id).filter(Boolean);
        ids.forEach((id: number, index: number) => {
          setTimeout(() => {
            this.restauranteService.imprimirComanda(id).subscribe({
              next: (html) => {
                const w = window.open('', '_blank', 'width=400,height=600');
                if (w) {
                  w.document.write(html);
                  w.document.close();
                  w.focus();
                }
              },
              error: (err) => this.alertService.error(err)
            });
          }, index * 400);
        });
      },
      error: (err) => {
        this.alertService.error(err);
        this.enviandoComanda = false;
      }
    });
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

    const faltanLotes = this.lineas.some((l) =>
      this.requiereDistribucionLotes(l) && !(l.lotes_asignados?.length || l.lote_id)
    );
    if (faltanLotes) {
      this.alertService.error('Debe distribuir los lotes de todos los productos con inventario por lotes.');
      return;
    }

    const detalles = this.lineas.map((l) => ({
      producto_id: l.producto_id,
      cantidad: l.cantidad,
      precio: l.precio,
      descuento: l.descuento || 0,
      notas: l.notas?.trim() || undefined,
      lote_id: l.lote_id || undefined,
      lotes_asignados: l.lotes_asignados || undefined,
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
