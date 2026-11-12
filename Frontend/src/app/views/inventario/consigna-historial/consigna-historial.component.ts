import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { FuncionalidadesService } from '@services/functionalities.service';
import { CurrencyPipe } from '@pipes/currency-format.pipe';
import { LazyImageDirective } from '../../../directives/lazy-image.directive';

type AlcanceHistorial = 'ventas' | 'compras';

@Component({
  selector: 'app-consigna-historial',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule, NgSelectModule, TooltipModule, CurrencyPipe, LazyImageDirective],
  templateUrl: './consigna-historial.component.html',
})
export class ConsignaHistorialComponent implements OnInit {
  public moduloActivo = false;
  public loading = false;
  public downloading = false;
  public alcance: AlcanceHistorial = 'ventas';
  public clientes: any[] = [];
  public proveedores: any[] = [];
  public resumen: any[] = [];
  public movimientos: any[] = [];
  public incluirLote = false;
  public terceroNombre = '';

  public filtros = {
    id_cliente: null as number | null,
    id_proveedor: null as number | null,
    fecha_ini: '',
    fecha_fin: '',
  };

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private funcionalidadesService: FuncionalidadesService,
    private route: ActivatedRoute
  ) {}

  ngOnInit(): void {
    const alcance = this.route.snapshot.queryParamMap.get('alcance');
    if (alcance === 'compras') {
      this.alcance = 'compras';
    }
    const hace90 = new Date();
    hace90.setDate(hace90.getDate() - 90);
    this.filtros.fecha_ini = hace90.toISOString().slice(0, 10);
    this.filtros.fecha_fin = this.apiService.date();

    const idCliente = this.route.snapshot.queryParamMap.get('id_cliente');
    if (idCliente) {
      this.filtros.id_cliente = Number(idCliente);
    }
    const idProveedor = this.route.snapshot.queryParamMap.get('id_proveedor');
    if (idProveedor) {
      this.filtros.id_proveedor = Number(idProveedor);
    }

    this.funcionalidadesService.verificarAcceso('modulo-consignaciones').subscribe({
      next: (acceso) => {
        this.moduloActivo = acceso;
        if (acceso) {
          this.loadClientes();
          this.loadProveedores();
          if (this.terceroSeleccionado()) {
            this.cargar();
          }
        }
      },
      error: () => {
        this.moduloActivo = false;
      },
    });
  }

  public cambiarAlcance(alcance: AlcanceHistorial): void {
    this.alcance = alcance;
    this.resumen = [];
    this.movimientos = [];
    this.terceroNombre = '';
    if (this.terceroSeleccionado()) {
      this.cargar();
    }
  }

  private loadClientes(): void {
    this.apiService.getAll('clientes/list').subscribe({
      next: (rows) => {
        this.clientes = rows ?? [];
      },
      error: (err) => this.alertService.error(err),
    });
  }

  private loadProveedores(): void {
    this.apiService.getAll('proveedores/list').subscribe({
      next: (rows) => {
        this.proveedores = rows ?? [];
      },
      error: (err) => this.alertService.error(err),
    });
  }

  public terceroSeleccionado(): boolean {
    return this.alcance === 'ventas'
      ? !!this.filtros.id_cliente
      : !!this.filtros.id_proveedor;
  }

  public nombreTerceroSeleccionado(): string {
    if (this.alcance === 'ventas' && this.filtros.id_cliente) {
      const c = this.clientes.find((x) => x.id === this.filtros.id_cliente);
      return c?.nombre ?? '';
    }
    if (this.alcance === 'compras' && this.filtros.id_proveedor) {
      const p = this.proveedores.find((x) => x.id === this.filtros.id_proveedor);
      return p?.nombre ?? '';
    }
    return '';
  }

  public cargar(): void {
    if (!this.terceroSeleccionado()) {
      this.alertService.warning('Historial', 'Seleccione un cliente o proveedor.');
      return;
    }
    this.loading = true;
    const params = {
      ...this.filtros,
      alcance: this.alcance,
    };
    this.apiService.getAll('productos/consigna-historial', params).subscribe({
      next: (data) => {
        this.resumen = data?.resumen ?? [];
        this.movimientos = data?.movimientos ?? [];
        this.incluirLote = !!data?.incluir_lote;
        this.terceroNombre = data?.tercero?.nombre ?? this.nombreTerceroSeleccionado();
        this.loading = false;
      },
      error: (err) => {
        this.loading = false;
        this.alertService.error(err);
      },
    });
  }

  public exportar(): void {
    if (!this.terceroSeleccionado()) {
      this.alertService.warning(
        'Exportar',
        this.alcance === 'ventas'
          ? 'Seleccione un cliente para exportar.'
          : 'Seleccione un proveedor para exportar.'
      );
      return;
    }
    this.downloading = true;
    const params = {
      ...this.filtros,
      alcance: this.alcance,
    };
    this.apiService.export('productos/consigna-historial/exportar', params).subscribe({
      next: (blob) => {
        const a = document.createElement('a');
        const url = URL.createObjectURL(blob);
        a.href = url;
        a.download = this.alcance === 'compras' ? 'historial-consigna-compras.xlsx' : 'historial-consigna-ventas.xlsx';
        a.click();
        URL.revokeObjectURL(url);
        this.downloading = false;
      },
      error: (err) => {
        this.downloading = false;
        this.alertService.error(err);
      },
    });
  }

  public etiquetaEnviadas(): string {
    return this.alcance === 'compras' ? 'Recibidas' : 'Enviadas';
  }

  public etiquetaFacturadas(): string {
    return this.alcance === 'compras' ? 'Vendidas' : 'Facturadas';
  }
}
