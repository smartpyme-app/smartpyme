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
    this.filtros.fecha_ini = '';
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

  public etiquetaCliente(cliente: any): string {
    const base =
      cliente?.tipo === 'Empresa' ? cliente?.nombre_empresa : cliente?.nombre_completo;
    const comercial = cliente?.nombre_comercial ? ` (${cliente.nombre_comercial})` : '';
    return (base ?? cliente?.nombre ?? '') + comercial;
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

  public nombreTerceroSeleccionado(): string {
    if (this.alcance === 'ventas' && this.filtros.id_cliente) {
      const c = this.clientes.find((x) => Number(x.id) === Number(this.filtros.id_cliente));
      return c ? this.etiquetaCliente(c) : '';
    }
    if (this.alcance === 'compras' && this.filtros.id_proveedor) {
      const p = this.proveedores.find((x) => Number(x.id) === Number(this.filtros.id_proveedor));
      return p ? this.etiquetaProveedor(p) : '';
    }
    return '';
  }

  public cargar(): void {
    if (!this.terceroSeleccionado()) {
      this.alertService.warning('Historial', 'Seleccione un cliente o proveedor.');
      return;
    }
    this.loading = true;
    const params: Record<string, unknown> = {
      alcance: this.alcance,
      id_cliente: this.filtros.id_cliente,
      id_proveedor: this.filtros.id_proveedor,
    };
    if (this.filtros.fecha_ini) {
      params['fecha_ini'] = this.filtros.fecha_ini;
    }
    if (this.filtros.fecha_fin) {
      params['fecha_fin'] = this.filtros.fecha_fin;
    }
    this.apiService.getAll('productos/consigna-historial', params).subscribe({
      next: (data) => {
        const payload = data?.resumen !== undefined ? data : (data?.data ?? data);
        this.resumen = Array.isArray(payload?.resumen) ? payload.resumen : [];
        this.movimientos = Array.isArray(payload?.movimientos) ? payload.movimientos : [];
        this.incluirLote = !!payload?.incluir_lote;
        this.terceroNombre = payload?.tercero?.nombre ?? this.nombreTerceroSeleccionado();
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
    this.apiService.export('productos/consigna-historial/exportar-pdf', params).subscribe({
      next: (blob) => {
        const nombre =
          this.alcance === 'compras' ? 'historial-consigna-compras.pdf' : 'historial-consigna-ventas.pdf';
        this.apiService.downloadFile(blob, nombre);
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
