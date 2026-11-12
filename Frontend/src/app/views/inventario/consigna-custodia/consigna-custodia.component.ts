import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { FuncionalidadesService } from '@services/functionalities.service';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { CurrencyPipe } from '@pipes/currency-format.pipe';
import { TruncatePipe } from '@pipes/truncate.pipe';
import { LazyImageDirective } from '../../../directives/lazy-image.directive';

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
    LazyImageDirective,
  ],
  templateUrl: './consigna-custodia.component.html',
})
export class ConsignaCustodiaComponent implements OnInit {
  public ventasConsigna: any = { data: [] };
  public loading = false;
  public downloading = false;
  public moduloActivo = false;
  public filtros: any = {
    buscador: '',
    paginate: 25,
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
          this.loadAll();
        }
      },
      error: () => {
        this.moduloActivo = false;
      },
    });
  }

  public loadAll() {
    this.loading = true;
    this.apiService.getAll('productos/consigna-custodia/ventas', this.filtros).subscribe({
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

  public setPagination(event: number) {
    this.filtros.page = event;
    this.loadAll();
  }

  public cambiarPaginacion() {
    this.filtros.page = 1;
    this.loadAll();
  }

  public descargar() {
    this.downloading = true;
    this.apiService.export('productos/consigna-custodia/ventas/exportar', this.filtros).subscribe({
      next: (data: Blob) => {
        const blob = new Blob([data], {
          type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'reporte-ventas-consigna.xlsx';
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

  public documentoVenta(venta: any): string {
    const doc = venta?.documento?.nombre ?? venta?.nombre_documento ?? 'Documento';
    const corr = venta?.correlativo ?? '';
    return corr ? `${doc} #${corr}` : doc;
  }
}
