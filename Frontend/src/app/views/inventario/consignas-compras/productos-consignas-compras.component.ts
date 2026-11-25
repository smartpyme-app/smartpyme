import { Component, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { CurrencyPipe } from '@pipes/currency-format.pipe';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterModule } from '@angular/router';
import { BsModalRef, BsModalService } from 'ngx-bootstrap/modal';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { NgSelectModule } from '@ng-select/ng-select';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { FilterPipe } from '@pipes/filter.pipe';
import { TruncatePipe } from '@pipes/truncate.pipe';
import { LazyImageDirective } from '../../../directives/lazy-image.directive';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { ConsignaHistorialPanelComponent } from '../consigna-historial/consigna-historial-panel.component';

type TabCompras = 'compras' | 'devoluciones' | 'historial';

@Component({
  selector: 'app-productos-consignas-compras',
  templateUrl: './productos-consignas-compras.component.html',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    TooltipModule,
    PopoverModule,
    NgSelectModule,
    FilterPipe,
    TruncatePipe,
    CurrencyPipe,
    LazyImageDirective,
    PaginationComponent,
    ConsignaHistorialPanelComponent,
  ],
})
export class ProductosConsignasComprasComponent implements OnInit {
  @ViewChild(ConsignaHistorialPanelComponent) historialPanel?: ConsignaHistorialPanelComponent;

  public compras: any[] = [];
  public devolucionesCompra: any = { data: [] };
  public proveedores: any[] = [];
  public buscador = '';
  public loading = false;
  public downloading = false;
  public tabActiva: TabCompras = 'compras';
  public compra: any = {};
  public modalRef!: BsModalRef;
  public modalFiltroRef?: BsModalRef;

  public filtros: any = {
    buscador: '',
    paginate: 25,
    id_proveedor: null as number | null | '',
    page: 1,
  };

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private modalService: BsModalService,
    public modalManager: ModalManagerService,
    private route: ActivatedRoute
  ) {}

  ngOnInit() {
    const tab = this.route.snapshot.queryParamMap.get('tab');
    if (tab === 'devoluciones' || tab === 'historial') {
      this.tabActiva = tab;
    }
    this.loadProveedores();
    if (this.tabActiva === 'compras') {
      this.loadCompras();
    } else if (this.tabActiva === 'devoluciones') {
      this.loadDevoluciones();
    }
  }

  public cambiarTab(tab: TabCompras) {
    this.tabActiva = tab;
    this.filtros.page = 1;
    if (tab === 'compras') {
      this.loadCompras();
    } else if (tab === 'devoluciones') {
      this.loadDevoluciones();
    } else if (tab === 'historial') {
      if (!this.proveedores.length) {
        this.loadProveedores();
      }
      this.normalizarIdProveedorHistorial();
      queueMicrotask(() => {
        if (this.idProveedorHistorial()) {
          this.historialPanel?.cargar();
        }
      });
    }
  }

  public loadProveedores() {
    this.apiService.getAll('proveedores/list').subscribe({
      next: (rows) => {
        this.proveedores = rows ?? [];
      },
      error: (err) => this.alertService.error(err),
    });
  }

  public etiquetaProveedor(proveedor: any): string {
    if (proveedor?.nombre_comercial) {
      return proveedor.nombre_comercial;
    }
    if (proveedor?.nombre_empresa) {
      return proveedor.nombre_empresa;
    }
    return [proveedor?.nombre, proveedor?.apellido].filter(Boolean).join(' ');
  }

  public etiquetaProveedorSeleccionado(): string {
    const id = this.filtros.id_proveedor;
    if (id == null || id === '') {
      return '';
    }
    const proveedor = this.proveedores.find((p) => Number(p.id) === Number(id));
    return proveedor ? this.etiquetaProveedor(proveedor) : '';
  }

  public openFilter(template: TemplateRef<unknown>) {
    this.modalFiltroRef = this.modalManager.openModal(template);
  }

  public aplicarFiltrosModal() {
    this.filtros.page = 1;
    this.modalManager.closeModal(this.modalFiltroRef);
    if (this.tabActiva === 'historial') {
      this.normalizarIdProveedorHistorial();
      queueMicrotask(() => this.historialPanel?.cargar());
      return;
    }
    if (this.tabActiva === 'devoluciones') {
      this.loadDevoluciones();
    }
  }

  public onHistorialProveedorChange(): void {
    this.normalizarIdProveedorHistorial();
    queueMicrotask(() => this.historialPanel?.cargar());
  }

  private normalizarIdProveedorHistorial(): void {
    const id = this.filtros.id_proveedor;
    if (id != null && id !== '') {
      this.filtros.id_proveedor = Number(id);
    }
  }

  public loadCompras() {
    this.loading = true;
    this.apiService.getAll('productos/consignas-compras').subscribe({
      next: (compras) => {
        this.compras = compras ?? [];
        this.loading = false;
      },
      error: (error) => {
        this.alertService.error(error);
        this.loading = false;
      },
    });
  }

  public loadDevoluciones() {
    this.loading = true;
    const params = { ...this.filtros };
    if (!params.id_proveedor) {
      delete params.id_proveedor;
    }
    this.apiService.getAll('productos/consigna-compras/devoluciones', params).subscribe({
      next: (data) => {
        this.devolucionesCompra = data?.data ? data : { data: Array.isArray(data) ? data : [] };
        this.loading = false;
      },
      error: (err) => {
        this.alertService.error(err);
        this.loading = false;
      },
    });
  }

  public buscar() {
    if (this.tabActiva === 'devoluciones') {
      this.filtros.buscador = this.buscador;
      this.filtros.page = 1;
      this.loadDevoluciones();
    }
  }

  public setPagination(event: number) {
    this.filtros.page = event;
    this.loadDevoluciones();
  }

  public openDetalles(template: TemplateRef<any>, compra: any) {
    this.compra = compra;
    this.modalRef = this.modalService.show(template, { class: 'modal-lg', backdrop: 'static' });
  }

  public mostrarColumnasLote(): boolean {
    const empresa = this.apiService.auth_user()?.empresa;
    const cfg = empresa?.custom_empresa?.configuraciones;
    if (!cfg) {
      return false;
    }
    return !!cfg.consignaciones_ledger_activo && !!cfg.lotes_activo;
  }

  public idProveedorHistorial(): number | null {
    const id = this.filtros.id_proveedor;
    if (id == null || id === '') {
      return null;
    }
    return Number(id);
  }

  public descargar() {
    if (this.tabActiva === 'compras') {
      this.downloading = true;
      this.apiService.export('productos/consignas-compras/exportar', {}).subscribe({
        next: (data: Blob) => this.guardarBlob(data, 'consignas-compras.xlsx'),
        error: (error) => {
          this.alertService.error(error);
          this.downloading = false;
        },
      });
      return;
    }
    if (this.tabActiva === 'devoluciones') {
      if (!this.filtros.id_proveedor) {
        this.alertService.warning('Exportar', 'Seleccione un proveedor en Filtros antes de descargar.');
        return;
      }
      this.downloading = true;
      const params = { ...this.filtros };
      this.apiService.export('productos/consigna-compras/devoluciones/exportar', params).subscribe({
        next: (data: Blob) => this.guardarBlob(data, 'reporte-devoluciones-consigna-compra.xlsx'),
        error: (error) => {
          this.alertService.error(error);
          this.downloading = false;
        },
      });
    }
  }

  private guardarBlob(data: Blob, filename: string) {
    const blob = new Blob([data], {
      type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
    this.downloading = false;
  }

  public documentoDevolucion(dev: any): string {
    const tipo = dev?.tipo_documento ?? 'Devolución';
    const ref = dev?.referencia ?? '';
    return ref ? `${tipo} #${ref}` : tipo;
  }

  public compraOrigen(dev: any): string {
    const c = dev?.compra;
    if (!c) {
      return '—';
    }
    return `${c.tipo_documento ?? 'Compra'} #${c.referencia ?? c.id}`;
  }
}
