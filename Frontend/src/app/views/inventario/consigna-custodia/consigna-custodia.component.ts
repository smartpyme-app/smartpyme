import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { NgSelectModule } from '@ng-select/ng-select';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { FuncionalidadesService } from '@services/functionalities.service';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { CurrencyPipe } from '@pipes/currency-format.pipe';
import { TruncatePipe } from '@pipes/truncate.pipe';
import { LazyImageDirective } from '../../../directives/lazy-image.directive';

type TabCustodia = 'ventas' | 'devoluciones';

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
  ],
  templateUrl: './consigna-custodia.component.html',
})
export class ConsignaCustodiaComponent implements OnInit {
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
    id_cliente: null as number | null,
    page: 1,
  };

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private funcionalidadesService: FuncionalidadesService
  ) {}

  ngOnInit() {
    this.funcionalidadesService.verificarAcceso('modulo-consignaciones').subscribe({
      next: (acceso) => {
        this.moduloActivo = acceso;
        if (acceso) {
          this.loadClientes();
          this.loadAll();
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

  public cambiarTab(tab: TabCustodia) {
    if (this.tabActiva === tab) {
      return;
    }
    this.tabActiva = tab;
    this.filtros.page = 1;
    this.loadAll();
  }

  public loadAll() {
    if (this.tabActiva === 'ventas') {
      this.loadVentas();
    } else {
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

  public onFiltroCliente() {
    this.filtros.page = 1;
    this.loadAll();
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
    if (!this.filtros.id_cliente) {
      this.alertService.warning('Exportar', 'Seleccione un cliente antes de descargar el reporte.');
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
    return this.tabActiva === 'ventas'
      ? this.ventasConsigna?.total ?? 0
      : this.devolucionesConsigna?.total ?? 0;
  }

  public itemsPaginados(): any {
    return this.tabActiva === 'ventas' ? this.ventasConsigna : this.devolucionesConsigna;
  }
}
