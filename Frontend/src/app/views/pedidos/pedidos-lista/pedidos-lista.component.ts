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

@Component({
  standalone: false,
  selector: 'app-pedidos-lista',
  templateUrl: './pedidos-lista.component.html',
  styleUrls: ['./pedidos-lista.component.css']
})
export class PedidosListaComponent implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  private searchSubject$ = new Subject<void>();

  pedidos: any = {};
  loading = false;
  sucursales: any[] = [];
  modalRef!: BsModalRef;

  filtros: any = {};

  constructor(
    public apiService: ApiService,
    private restauranteService: RestauranteService,
    private alertService: AlertService,
    private router: Router,
    private route: ActivatedRoute,
    private modalService: BsModalService
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
}
