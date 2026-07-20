import {
  Component,
  OnInit,
  OnDestroy,
  TemplateRef
} from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { Subject } from 'rxjs';
import { debounceTime, takeUntil } from 'rxjs/operators';

import { ApiService } from '@services/api.service';
import { RestauranteService, PedidoCanal } from '@services/restaurante.service';
import { AlertService } from '@services/alert.service';
import { BoxfulApiService } from '@services/boxful/boxful-api.service';

@Component({
  selector: 'app-pedidos-lista',
  templateUrl: './pedidos-lista.component.html',
  styleUrls: ['./pedidos-lista.component.css']
})
export class PedidosListaComponent implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private searchSubject$ = new Subject<void>();

  /** ponytail: wizard BoxFul oficial vive en Ventas; reactivar con true si hace falta dual */
  readonly boxfulWizardFromPedidosEnabled = false;

  pedidos: any = {};
  loading = false;
  sucursales: any[] = [];
  modalRef!: BsModalRef;

  mostrarModalBoxful = false;
  pedidoId: number | null = null;
  clienteId: number | null = null;
  pedidoRecienCreado: any = null;
  paqueteData: any = { peso: 1, alto: 10, ancho: 10, largo: 10, es_fragil: false, id: null };
  tieneBoxful = false;

  mostrarDetallesEnvio = false;
  selectedShipmentId = '';
  cargandoTrackingBoxful = false;
  trackingInfo: any = null;

  filtros: any = {};

  constructor(
    public apiService: ApiService,
    private restauranteService: RestauranteService,
    private alertService: AlertService,
    private router: Router,
    private route: ActivatedRoute,
    private modalService: BsModalService,
    private boxfulApiService: BoxfulApiService
  ) {}

  ngOnInit(): void {
    this.searchSubject$
      .pipe(debounceTime(400), takeUntil(this.destroy$))
      .subscribe(() => {
        this.filtros.page = 1;
        this.navegarConFiltros();
      });

    this.apiService
      .getAll('sucursales/list')
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (s) => {
          this.sucursales = s;
        },
        error: () => {
          this.sucursales = [];
        }
      });

    this.apiService
      .getAll('boxful/status')
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (res: any) => {
          this.tieneBoxful = res && res.connected;
        },
        error: () => {
          this.tieneBoxful = false;
        }
      });

    this.route.queryParams
      .pipe(takeUntil(this.destroy$))
      .subscribe((params) => {
        this.aplicarParamsALosFiltros(params);
        this.cargarLista();
      });
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  private aplicarParamsALosFiltros(params: Record<string, string>): void {
    const paginateRaw = params['paginate'];
    const pageRaw = params['page'];
    this.filtros = {
      buscador: params['buscador'] || '',
      estado: params['estado'] || '',
      inicio: params['inicio'] || '',
      fin: params['fin'] || '',
      canal: params['canal'] || '',
      id_sucursal:
        params['id_sucursal'] != null && params['id_sucursal'] !== ''
          ? +params['id_sucursal']
          : '',
      orden: params['orden'] || 'fecha',
      direccion: params['direccion'] || 'desc',
      paginate:
        paginateRaw != null && paginateRaw !== '' ? +paginateRaw : 10,
      page: pageRaw != null && pageRaw !== '' ? +pageRaw : 1
    };

    const u = this.apiService.auth_user();
    if (u.tipo !== 'Administrador' && u.tipo !== 'Super Admin') {
      if (
        params['id_sucursal'] == null ||
        params['id_sucursal'] === ''
      ) {
        this.filtros.id_sucursal = u.id_sucursal;
      }
    }
  }

  private buildQueryParams(): Record<string, string | number> {
    const qp: Record<string, string | number> = {
      paginate: this.filtros.paginate,
      page: this.filtros.page,
      orden: this.filtros.orden,
      direccion: this.filtros.direccion
    };
    if (this.filtros.buscador) {
      qp['buscador'] = this.filtrarBuscador(this.filtros.buscador);
    }
    if (this.filtros.estado) {
      qp['estado'] = this.filtros.estado;
    }
    if (this.filtros.inicio) {
      qp['inicio'] = this.filtros.inicio;
    }
    if (this.filtros.fin) {
      qp['fin'] = this.filtros.fin;
    }
    if (this.filtros.canal) {
      qp['canal'] = this.filtros.canal;
    }
    if (
      this.filtros.id_sucursal !== '' &&
      this.filtros.id_sucursal != null
    ) {
      qp['id_sucursal'] = this.filtros.id_sucursal;
    }
    return qp;
  }

  private filtrarBuscador(v: string): string {
    return String(v).trim();
  }

  private navegarConFiltros(): void {
    this.router.navigate([], {
      relativeTo: this.route,
      queryParams: this.buildQueryParams()
    });
  }

  onBuscadorInput(): void {
    this.searchSubject$.next();
  }

  filtrarPedidos(): void {
    if (this.filtrarBuscador(this.filtros.buscador || '')) {
      this.filtros.page = 1;
    }
    this.navegarConFiltros();
  }

  limpiarFiltros(): void {
    const u = this.apiService.auth_user();
    this.filtros.buscador = '';
    this.filtros.estado = '';
    this.filtros.inicio = '';
    this.filtros.fin = '';
    this.filtros.canal = '';
    this.filtros.paginate = 10;
    this.filtros.page = 1;
    this.filtros.orden = 'fecha';
    this.filtros.direccion = 'desc';
    if (u.tipo === 'Administrador' || u.tipo === 'Super Admin') {
      this.filtros.id_sucursal = '';
    } else {
      this.filtros.id_sucursal = u.id_sucursal;
    }
    this.navegarConFiltros();
  }

  cargarLista(): void {
    const p: Record<string, string | number> = {
      page: this.filtros.page,
      paginate: this.filtros.paginate,
      orden: this.filtros.orden,
      direccion: this.filtros.direccion
    };
    const b = this.filtrarBuscador(this.filtros.buscador || '');
    if (b) {
      p['buscador'] = b;
    }
    if (this.filtros.estado) {
      p['estado'] = this.filtros.estado;
    }
    if (this.filtros.inicio) {
      p['fecha_desde'] = this.filtros.inicio;
    }
    if (this.filtros.fin) {
      p['fecha_hasta'] = this.filtros.fin;
    }
    if (this.filtros.canal) {
      p['canal'] = this.filtros.canal;
    }
    if (
      this.filtros.id_sucursal !== '' &&
      this.filtros.id_sucursal != null
    ) {
      p['id_sucursal'] = this.filtros.id_sucursal;
    }

    this.loading = true;
    this.restauranteService.getPedidos(p).subscribe({
      next: (res) => {
        this.pedidos = res;
        this.loading = false;
        if (this.modalRef) {
          this.modalRef.hide();
        }
      },
      error: (err) => {
        this.alertService.error(err);
        this.loading = false;
      }
    });
  }

  setPagination(event: { page: number }): void {
    this.filtros.page = event.page;
    this.navegarConFiltros();
  }

  setOrden(col: string): void {
    if (this.filtros.orden === col) {
      this.filtros.direccion =
        this.filtros.direccion === 'asc' ? 'desc' : 'asc';
    } else {
      this.filtros.orden = col;
      this.filtros.direccion = 'desc';
    }
    this.filtros.page = 1;
    this.navegarConFiltros();
  }

  openFilter(template: TemplateRef<any>): void {
    this.modalRef = this.modalService.show(template);
  }

  nuevo(): void {
    this.router.navigate(['/pedidos/nuevo']);
  }

  editar(p: PedidoCanal): void {
    if (p.estado !== 'borrador') {
      return;
    }
    this.router.navigate(['/pedidos/editar', p.id]);
  }

  eliminar(p: PedidoCanal): void {
    if (p.estado !== 'borrador') {
      return;
    }
    if (!confirm('¿Eliminar este pedido?')) {
      return;
    }
    this.restauranteService.eliminarPedido(p.id).subscribe({
      next: () => {
        this.alertService.success('Pedido eliminado', '');
        this.cargarLista();
      },
      error: (err) => this.alertService.error(err)
    });
  }

  confirmar(p: PedidoCanal): void {
    if (!confirm('¿Confirmar pedido? Pasará a pendiente de facturar y ya no podrá editarse.')) {
      return;
    }
    this.restauranteService.confirmarPedidoCanal(p.id).subscribe({
      next: () => {
        this.alertService.success('Pedido confirmado', '');
        this.cargarLista();
      },
      error: (err) => this.alertService.error(err)
    });
  }

  anular(p: PedidoCanal): void {
    if (!confirm('¿Anular este pedido?')) {
      return;
    }
    this.restauranteService.anularPedidoCanal(p.id).subscribe({
      next: () => {
        this.alertService.success('Pedido anulado', '');
        this.cargarLista();
      },
      error: (err) => this.alertService.error(err)
    });
  }

  imprimir(p: PedidoCanal): void {
    this.restauranteService.imprimirPedidoCanal(p.id).subscribe({
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
  }

  enviarComanda(p: PedidoCanal): void {
    if (!['borrador', 'pendiente_facturar'].includes(p.estado)) {
      return;
    }
    this.restauranteService.enviarComandaPedido(p.id).subscribe({
      next: (res: any) => {
        this.alertService.success('Comanda enviada', 'Se generó la comanda para cocina/barra.');
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
      error: (err) => this.alertService.error(err)
    });
  }

  /** Abre facturación con líneas del pedido (misma idea que pre-cuenta mesa). */
  irAFacturar(p: PedidoCanal): void {
    if (p.estado !== 'pendiente_facturar') {
      return;
    }
    this.restauranteService.prepararFacturaPedidoCanal(p.id).subscribe({
      next: (data: any) => {
        const state = {
          pedidoCanalId: data.pedido_id,
          pedidoCanalData: {
            cliente_id: data.cliente_id,
            id_sucursal: data.id_sucursal,
            id_bodega: data.id_bodega,
            fecha: data.fecha,
            canal: data.canal,
            referencia_externa: data.referencia_externa,
            observaciones: data.observaciones,
            detalles: data.detalles
          }
        };
        this.router.navigate(['/venta/crear'], {
          queryParams: { pedido_canal: data.pedido_id },
          state
        });
      },
      error: (err) => this.alertService.error(err)
    });
  }

  generarGuiaBoxful(pedido: any): void {
    this.alertService.info('Cargando detalles del pedido...', '');
    this.restauranteService.getPedido(pedido.id).subscribe({
      next: (fullPedido: any) => {
        this.pedidoId = fullPedido.id;
        this.clienteId = fullPedido.cliente_id;
        this.pedidoRecienCreado = fullPedido;
        // ponytail: one parcel per order line – Boxful prices per-box
        const detalles = fullPedido.detalles || [];
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
      },
      error: (err) => {
        console.error('Error al cargar detalles del pedido:', err);
        this.alertService.error(err);
      }
    });
  }

  onBoxfulGuiaGenerada(guia: any): void {
    const numGuia = guia.shipmentNumber || guia.data?.shipmentNumber || guia.id || guia.data?.id || '';
    const labelUrl = guia.labelUrl || guia.data?.labelUrl || '';
    const trackingUrl = guia.trackingUrl || guia.data?.trackingUrl || '';

    if (!numGuia) {
      this.alertService.error('Boxful no devolvió número de guía. El pedido no se actualizó.');
      return;
    }

    const textToAdd = `Envío Boxful #${numGuia}. Guía PDF: ${labelUrl}. Rastreo: ${trackingUrl}`;
    const obsActual = this.pedidoRecienCreado?.observaciones
      ? `${this.pedidoRecienCreado.observaciones} | ${textToAdd}`
      : textToAdd;

    const updatePayload = {
      observaciones: obsActual
    };

    this.restauranteService.actualizarPedido(this.pedidoId!, updatePayload).subscribe({
      next: () => {
        // Mantener modal abierto para el paso 3 de confirmación del wizard
        this.alertService.success('Guía vinculada y pedido confirmado', `Envío Boxful #${numGuia} vinculado al pedido.`);
        this.cargarLista();
      },
      error: (err) => {
        this.alertService.error(err);
        this.cargarLista();
      }
    });
  }

  cerrarModalBoxful(): void {
    this.mostrarModalBoxful = false;
    this.pedidoId = null;
    this.clienteId = null;
    this.pedidoRecienCreado = null;
  }

  verDetallesEnvio(boxfulShipmentId: string): void {
    this.selectedShipmentId = boxfulShipmentId;
    this.mostrarDetallesEnvio = true;
  }

  onCerrarDetallesEnvioBoxful(): void {
    this.mostrarDetallesEnvio = false;
    this.selectedShipmentId = '';
    this.cargarLista();
  }

  verTrackingPaquete(shipmentNumber: string, template: TemplateRef<any>): void {
    this.trackingInfo = null;
    this.cargandoTrackingBoxful = true;
    this.modalRef = this.modalService.show(template, { class: 'modal-md' });

    this.boxfulApiService.getTracking(shipmentNumber).subscribe({
      next: (res) => {
        this.trackingInfo = res;
        this.cargandoTrackingBoxful = false;
      },
      error: (err) => {
        this.alertService.error(err);
        this.cargandoTrackingBoxful = false;
        this.modalRef.hide();
      }
    });
  }


  getTrackingCheckpoints(): any[] {
    if (!this.trackingInfo) {
      return [];
    }

    if (Array.isArray(this.trackingInfo)) {
      return this.trackingInfo;
    }

    const tracking = this.trackingInfo.tracking || this.trackingInfo.data;
    if (tracking) {
      if (Array.isArray(tracking)) {
        return tracking;
      }
      if (Array.isArray(tracking.statusHistory)) {
        return tracking.statusHistory;
      }
      if (Array.isArray(tracking.checkpoints)) {
        return tracking.checkpoints;
      }
    }

    if (Array.isArray(this.trackingInfo.statusHistory)) {
      return this.trackingInfo.statusHistory;
    }
    if (Array.isArray(this.trackingInfo.checkpoints)) {
      return this.trackingInfo.checkpoints;
    }

    return [];
  }

  getTrackingShipment(): any {
    if (!this.trackingInfo) {
      return null;
    }
    return this.trackingInfo.shipment || this.trackingInfo.shipmentData || this.trackingInfo;
  }

  getTrackingSteps(): any[] {
    const s = this.getTrackingShipment();
    if (!s) {
      return [];
    }

    const currentStatus = s.status !== undefined ? s.status : -1;
    const history = s.statusHistory || [];
    const createdAt = s.createdAt;

    const findDateInHistory = (keywords: string[], statusVal: number): any => {
      let found = history.find((h: any) => h.status === statusVal);
      if (found && found.createdAt) {
        return found.createdAt;
      }

      found = history.find((h: any) => {
        const desc = (h.statusDescription || h.status || '').toLowerCase();
        return keywords.some(k => desc.includes(k));
      });
      if (found && found.createdAt) {
        return found.createdAt;
      }

      return null;
    };

    return [
      {
        key: 'creado',
        label: 'Creado',
        completed: currentStatus >= -1,
        date: createdAt
      },
      {
        key: 'registrado',
        label: 'Registrado',
        completed: currentStatus >= 1,
        date: findDateInHistory(['registrado', 'registrada'], 1)
      },
      {
        key: 'recolectado',
        label: 'Recolectado',
        completed: currentStatus >= 2,
        date: findDateInHistory(['recolectado', 'recolectada', 'pickup'], 2)
      },
      {
        key: 'ruta',
        label: 'Ruta a destino',
        completed: currentStatus >= 3,
        date: findDateInHistory(['ruta', 'transito', 'camino', 'transit'], 3)
      },
      {
        key: 'entregado',
        label: 'Entregado',
        completed: currentStatus >= 4,
        date: findDateInHistory(['entregado', 'entregada', 'delivered'], 4)
      }
    ];
  }

  etiquetaEstado(estado: string): string {
    const m: Record<string, string> = {
      borrador: 'Borrador',
      pendiente_facturar: 'Pendiente de facturar',
      facturado: 'Facturado',
      anulado: 'Anulado'
    };
    return m[estado] || estado;
  }

  claseEstado(estado: string): string {
    if (estado === 'borrador') {
      return 'bg-secondary';
    }
    if (estado === 'pendiente_facturar') {
      return 'bg-warning text-dark';
    }
    if (estado === 'facturado') {
      return 'bg-success';
    }
    if (estado === 'anulado') {
      return 'bg-danger';
    }
    return 'bg-light text-dark';
  }

  nombreCliente(p: PedidoCanal): string {
    const c = p.cliente;
    if (!c) {
      return '—';
    }
    return (c as { nombre_empresa?: string; nombre_completo?: string })
      .nombre_empresa ||
      (c as { nombre_completo?: string }).nombre_completo ||
      '—';
  }

  tieneBoxfulShipmentValido(p: any): boolean {
    return !!p?.boxful_shipment?.shipment_number;
  }

  tieneGuiaBoxfulEnObservaciones(p: any): boolean {
    const obs = (p?.observaciones || '').toLowerCase();
    const match = obs.match(/envío boxful #([^.\s|]+)/);
    return !!(match && match[1]);
  }

  /** Borrador sin guía Boxful ya creada → se puede editar/confirmar/eliminar. */
  puedeEditarPedido(p: any): boolean {
    if (p.estado !== 'borrador') return false;
    if (p.canal === 'Boxful' && this.tieneBoxfulShipmentValido(p)) return false;
    return true;
  }

  puedeGenerarGuiaBoxful(p: any): boolean {
    if (!this.boxfulWizardFromPedidosEnabled) {
      return false;
    }
    return p.canal === 'Boxful'
      && !!p.cliente_id
      && p.estado === 'borrador'
      && this.tieneBoxful
      && !this.tieneGuiaBoxfulEnObservaciones(p)
      && !this.tieneBoxfulShipmentValido(p);
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
