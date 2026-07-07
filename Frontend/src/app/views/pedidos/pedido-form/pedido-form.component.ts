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
  id_paquete?: number | null;
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

  mostrarModalBoxful = false;
  pedidoRecienCreado: any = null;
  generandoGuiaBoxful = false;
  tieneBoxful = false;

  fecha = '';
  canal = '';
  referenciaExterna = '';
  observaciones = '';
  clientes: any[] = [];
  canales: any[] = [];

  lineas: LineaLocal[] = [];
  bodegas: any[] = [];
  idBodega: number | null = null;
  lineaLotesIndex: number | null = null;

  @ViewChild('lotesModal') lotesModal!: DistribucionLotesModalComponent;

  venta: any = {
    id_cliente: null,
    id_sucursal: null,
    id_vendedor: null,
    detalles: []
  };

  ventaPaquetes: any = {
    id_cliente: '',
    id_sucursal: null,
    id_vendedor: null,
    detalles: []
  };

  paqueteData: any = { peso: 1, alto: 10, ancho: 10, largo: 10, es_fragil: false, id: null };

  private _clienteId: number | null = null;

  get clienteId(): number | null {
    return this._clienteId;
  }

  set clienteId(value: number | null) {
    this._clienteId = value;
    this.venta.id_cliente = value;
    this.ventaPaquetes.id_cliente = value ?? '';
  }

  get usuario(): any {
    return this.apiService.auth_user();
  }

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private restauranteService: RestauranteService,
    private apiService: ApiService,
    private alertService: AlertService
  ) { }

  ngOnInit(): void {
    this.venta.id_sucursal = this.apiService.auth_user()?.id_sucursal;
    this.ventaPaquetes.id_sucursal = this.apiService.auth_user()?.id_sucursal;
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

    this.apiService.getAll('canales/list').subscribe({
      next: (c: any) => {
        this.canales = Array.isArray(c) ? c : [];
      },
      error: () => {
        this.canales = [];
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

    if (this.usuario?.empresa?.modulo_paquetes) {
      this.apiService.getAll('boxful/status').subscribe({
        next: (res: any) => {
          this.tieneBoxful = res && res.connected;
          console.log(this.tieneBoxful);
        },
        error: () => {
          this.tieneBoxful = false;
        }
      });
    }

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
        this.observaciones = p.observaciones || '';
        this.clienteId = p.cliente_id ?? null;
        this.lineas = (p.detalles || []).map((d: any) => ({
          producto_id: d.producto_id,
          id_paquete: d.id_paquete || null,
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
    // ponytail: cosmetic guard – backend is source of truth (validarPaquetesDisponibles)
    if (producto.id_paquete) {
      const shipment = producto.paquete?.boxful_shipment || producto.paquete?.boxfulShipment;
      if (shipment?.shipment_number) {
        this.alertService.warning('Paquete no disponible', 'Este paquete ya tiene un envío Boxful generado.');
        return;
      }
      if (this.lineas.some(l => l.id_paquete === producto.id_paquete)) {
        this.alertService.warning('Paquete duplicado', 'Este paquete ya fue agregado al pedido.');
        return;
      }
    }

    if (producto.id_paquete && producto.id_cliente && producto.id_cliente !== this.clienteId) {
      this.apiService.read('cliente/', producto.id_cliente).subscribe({
        next: (cliente) => this.onSelectCliente(cliente),
        error: () => { }
      });
    }

    const precio = parseFloat(
      producto.precio ?? producto.precio_publico ?? producto.precio_venta ?? 0
    );
    let notas = producto.notas ?? '';
    let nombre = producto.nombre_mostrar || producto.nombre || producto.descripcion || 'Producto';
    if (producto.id_paquete && producto.descripcion) {
      notas = producto.descripcion;
      nombre = `${nombre} (${producto.descripcion})`;
    }
    const linea: LineaLocal = {
      producto_id: producto.id ?? producto.id_producto,
      id_paquete: producto.id_paquete || null,
      nombre: nombre,
      cantidad: producto.cantidad ?? 1,
      precio: precio,
      descuento: producto.descuento ?? 0,
      notas: notas,
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

  onSelectCliente(cliente: any): void {
    if (cliente?.id) {
      this.clienteId = cliente.id;
    }
  }

  esCanalBoxful(): boolean {
    return this.canal === 'Boxful' && !!this.usuario?.empresa?.modulo_paquetes && this.tieneBoxful;
  }

  onBoxfulGuiaGenerada(guia: any): void {
    this.generandoGuiaBoxful = true;
    const numGuia = guia.shipmentNumber || guia.data?.shipmentNumber || guia.id || guia.data?.id || '';
    const labelUrl = guia.labelUrl || guia.data?.labelUrl || '';
    const trackingUrl = guia.trackingUrl || guia.data?.trackingUrl || '';

    if (!numGuia) {
      this.generandoGuiaBoxful = false;
      this.alertService.error('Boxful no devolvió número de guía. El pedido no se actualizó.');
      return;
    }

    const textToAdd = `Envío Boxful #${numGuia}. Guía PDF: ${labelUrl}. Rastreo: ${trackingUrl}`;
    const obsActual = this.observaciones
      ? `${this.observaciones} | ${textToAdd}`
      : textToAdd;

    const updatePayload = {
      observaciones: obsActual
    };

    this.restauranteService.actualizarPedido(this.pedidoId!, updatePayload).subscribe({
      next: () => {
        this.generandoGuiaBoxful = false;
        this.mostrarModalBoxful = false;
        this.alertService.success('Guía vinculada y pedido confirmado', `Envío Boxful #${numGuia} vinculado al pedido.`);
        this.router.navigate(['/pedidos']);
      },
      error: (err) => {
        this.generandoGuiaBoxful = false;
        this.alertService.error(err);
        this.router.navigate(['/pedidos']);
      }
    });
  }

  cerrarModalBoxful(): void {
    this.mostrarModalBoxful = false;
    this.alertService.info('Guía no generada', 'Puede generar la guía después desde el listado de pedidos.');
    this.router.navigate(['/pedidos']);
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
      id_paquete: l.id_paquete || undefined,
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
      next: (pedidoGuardado: any) => {
        this.guardando = false;

        if (this.esCanalBoxful() && this.clienteId) {
          this.pedidoRecienCreado = pedidoGuardado;
          this.pedidoId = pedidoGuardado.id;
          // ponytail: one parcel per order line – Boxful prices per-box
          const detalles = pedidoGuardado.detalles || [];
          const parcels = detalles.length > 0
            ? detalles.map((d: any) => {
              const bp = d.paquete?.boxful_shipment?.parcels?.[0] || d.paquete?.boxfulShipment?.parcels?.[0];
              return {
                id: bp?.id || null,
                peso: bp?.peso ?? 1,
                alto: bp?.alto ?? 11,
                ancho: bp?.ancho ?? 43,
                largo: bp?.largo ?? 47.5,
                es_fragil: bp?.es_fragil ?? false,
                contenido: bp?.contenido ?? '',
                valor: parseFloat(bp?.valor_declarado || d.total || d.precio || 50)
              };
            })
            : [{ peso: 1, alto: 11, ancho: 43, largo: 47.5, es_fragil: false, contenido: '', valor: 50 }];
          this.paqueteData = { id: this.primerPaqueteIdDeDetalles(detalles), parcels };
          this.mostrarModalBoxful = true;
          this.alertService.info('Pedido guardado', 'Ahora genere la guía de envío.');
        } else {
          this.alertService.success('Pedido guardado', '');
          this.router.navigate(['/pedidos']);
        }
      },
      error: (err) => {
        this.guardando = false;
        this.alertService.error(err);
      }
    });
  }

  private primerPaqueteIdDeDetalles(detalles: any[]): number | null {
    for (const d of detalles) {
      if (d?.id_paquete) {
        return d.id_paquete;
      }
      if (d?.paquete?.id) {
        return d.paquete.id;
      }
    }
    return null;
  }
}
