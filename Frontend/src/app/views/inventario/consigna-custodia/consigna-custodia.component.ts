import { Component, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { ConsignaHistorialPanelComponent } from '../consigna-historial/consigna-historial-panel.component';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterModule } from '@angular/router';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { NgSelectModule } from '@ng-select/ng-select';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { FuncionalidadesService } from '@services/functionalities.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { CurrencyPipe } from '@pipes/currency-format.pipe';
import { TruncatePipe } from '@pipes/truncate.pipe';
import { LazyImageDirective } from '../../../directives/lazy-image.directive';
type TabCustodia = 'ventas' | 'devoluciones' | 'historial';

@Component({
  selector: 'app-consigna-custodia',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    PaginationComponent,
    CurrencyPipe,
    TruncatePipe,
    PopoverModule,
    TooltipModule,
    NgSelectModule,
    LazyImageDirective,
    ConsignaHistorialPanelComponent,
  ],
  templateUrl: './consigna-custodia.component.html',
})
export class ConsignaCustodiaComponent implements OnInit {
  @ViewChild('mfilter') mfilter?: TemplateRef<unknown>;
  @ViewChild(ConsignaHistorialPanelComponent) historialPanel?: ConsignaHistorialPanelComponent;

  public ventasConsigna: any = { data: [] };
  public devolucionesConsigna: any = { data: [] };
  public clientes: any[] = [];
  public loading = false;
  public downloading = false;
  public moduloActivo = false;
  public tabActiva: TabCustodia = 'ventas';
  public filtros: any = {
    buscador: '',
    paginate: 25,
    id_cliente: null as number | null | '',
    page: 1,
  };

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private funcionalidadesService: FuncionalidadesService,
    public modalManager: ModalManagerService,
    private route: ActivatedRoute
  ) {}

  ngOnInit() {
    const tab = this.route.snapshot.queryParamMap.get('tab');
    if (tab === 'devoluciones' || tab === 'historial') {
      this.tabActiva = tab;
    }
    this.funcionalidadesService.verificarAcceso('modulo-consignaciones').subscribe({
      next: (acceso) => {
        this.moduloActivo = acceso;
        if (acceso) {
          this.loadClientes();
          if (this.tabActiva !== 'historial') {
            this.loadAll();
          }
        }
      },
      error: () => {
        this.moduloActivo = false;
      },
    });
  }

  public loadClientes() {
    this.apiService.getAll('clientes/list').subscribe({
      next: (clientes) => {
        this.clientes = clientes ?? [];
      },
      error: (err) => this.alertService.error(err),
    });
  }

  public etiquetaCliente(cliente: any): string {
    const base =
      cliente?.tipo === 'Empresa' ? cliente?.nombre_empresa : cliente?.nombre_completo;
    const comercial = cliente?.nombre_comercial ? ` (${cliente.nombre_comercial})` : '';
    return (base ?? '') + comercial;
  }

  public etiquetaClienteSeleccionado(): string {
    const id = this.filtros.id_cliente;
    if (id == null || id === '') {
      return '';
    }
    const cliente = this.clientes.find((c) => Number(c.id) === Number(id));
    return cliente ? this.etiquetaCliente(cliente) : '';
  }

  public modalRef?: import('ngx-bootstrap/modal').BsModalRef;

  public openFilter(template: TemplateRef<unknown>) {
    this.modalRef = this.modalManager.openModal(template);
  }

  public aplicarFiltrosModal() {
    this.filtros.page = 1;
    this.modalManager.closeModal(this.modalRef);
    if (this.tabActiva === 'historial') {
      this.normalizarIdClienteHistorial();
      queueMicrotask(() => this.historialPanel?.cargar());
      return;
    }
    this.loadAll();
  }

  public onHistorialClienteChange(): void {
    this.normalizarIdClienteHistorial();
    queueMicrotask(() => this.historialPanel?.cargar());
  }

  private normalizarIdClienteHistorial(): void {
    const id = this.filtros.id_cliente;
    if (id != null && id !== '') {
      this.filtros.id_cliente = Number(id);
    }
  }

  public clienteHistorialPorId(): any | null {
    const id = this.filtros.id_cliente;
    if (id == null || id === '') {
      return null;
    }
    return this.clientes.find((c) => Number(c.id) === Number(id)) ?? null;
  }

  public cambiarTab(tab: TabCustodia) {
    if (this.tabActiva === tab) {
      return;
    }
    this.tabActiva = tab;
    this.filtros.page = 1;
    if (tab === 'historial') {
      if (!this.clientes.length) {
        this.loadClientes();
      }
      this.normalizarIdClienteHistorial();
      queueMicrotask(() => {
        if (this.idClienteHistorial()) {
          this.historialPanel?.cargar();
        }
      });
      return;
    }
    this.loadAll();
  }

  public loadAll() {
    if (this.tabActiva === 'ventas') {
      this.loadVentas();
    } else if (this.tabActiva === 'devoluciones') {
      this.loadDevoluciones();
    }
  }

  public loadVentas() {
    this.loading = true;
    this.apiService.getAll('productos/consigna-custodia/ventas', this.parametrosLista()).subscribe({
      next: (data) => {
        this.ventasConsigna = data?.data ? data : { data: Array.isArray(data) ? data : [] };
        this.loading = false;
      },
      error: (err) => {
        this.alertService.error(err);
        this.loading = false;
      },
    });
  }

  public loadDevoluciones() {
    this.loading = true;
    this.apiService.getAll('productos/consigna-custodia/devoluciones', this.parametrosLista()).subscribe({
      next: (data) => {
        this.devolucionesConsigna = data?.data ? data : { data: Array.isArray(data) ? data : [] };
        this.loading = false;
      },
      error: (err) => {
        this.alertService.error(err);
        this.loading = false;
      },
    });
  }

  private parametrosLista(): Record<string, unknown> {
    const params: Record<string, unknown> = { ...this.filtros };
    if (params['id_cliente'] == null || params['id_cliente'] === '') {
      delete params['id_cliente'];
    }
    return params;
  }

  public buscar() {
    this.filtros.page = 1;
    this.loadAll();
  }

  public setPagination(event: number) {
    this.filtros.page = event;
    this.loadAll();
  }

  public cambiarPaginacion() {
    this.filtros.page = 1;
    this.loadAll();
  }

  public descargar() {
    if (this.tabActiva === 'historial') {
      return;
    }
    if (!this.filtros.id_cliente) {
      this.alertService.warning('Exportar', 'Seleccione un cliente en Filtros antes de descargar.');
      return;
    }
    const endpoint =
      this.tabActiva === 'ventas'
        ? 'productos/consigna-custodia/ventas/exportar'
        : 'productos/consigna-custodia/devoluciones/exportar';
    const filename =
      this.tabActiva === 'ventas' ? 'reporte-ventas-consigna.xlsx' : 'reporte-devoluciones-consigna.xlsx';

    this.downloading = true;
    this.apiService.export(endpoint, this.parametrosLista()).subscribe({
      next: (data: Blob) => {
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
      },
      error: (err) => {
        this.alertService.error(err);
        this.downloading = false;
      },
    });
  }

  public idClienteHistorial(): number | null {
    const id = this.filtros.id_cliente;
    if (id == null || id === '') {
      return null;
    }
    return Number(id);
  }

  public nombreClienteVenta(venta: any): string {
    const c = venta?.cliente;
    if (c) {
      const partes = [c.nombre, c.apellido, c.nombre_comercial || c.nombre_empresa].filter(
        (x) => x && String(x).trim() !== ''
      );
      if (partes.length) {
        return partes.join(' ');
      }
    }
    return venta?.nombre_cliente ?? '—';
  }

  public nombreClienteDevolucion(devolucion: any): string {
    const c = devolucion?.cliente;
    if (c) {
      const partes = [c.nombre, c.apellido, c.nombre_comercial || c.nombre_empresa].filter(
        (x) => x && String(x).trim() !== ''
      );
      if (partes.length) {
        return partes.join(' ');
      }
    }
    return devolucion?.nombre_cliente ?? '—';
  }

  public documentoVenta(venta: any): string {
    const doc = venta?.documento?.nombre ?? venta?.nombre_documento ?? 'Documento';
    const corr = venta?.correlativo ?? '';
    return corr ? `${doc} #${corr}` : doc;
  }

  public documentoDevolucion(devolucion: any): string {
    const doc = devolucion?.documento?.nombre ?? devolucion?.nombre_documento ?? 'Devolución';
    const corr = devolucion?.correlativo ?? '';
    return corr ? `${doc} #${corr}` : doc;
  }

  public ventaOrigenDevolucion(devolucion: any): string {
    const venta = devolucion?.venta;
    if (!venta) {
      return '—';
    }
    const doc = venta.documento?.nombre ?? venta.nombre_documento ?? 'Documento';
    return venta.correlativo ? `${doc} #${venta.correlativo}` : doc;
  }

  public etiquetaPaginacion(): number {
    if (this.tabActiva === 'ventas') {
      return this.ventasConsigna?.total ?? 0;
    }
    if (this.tabActiva === 'devoluciones') {
      return this.devolucionesConsigna?.total ?? 0;
    }
    return 0;
  }
}
