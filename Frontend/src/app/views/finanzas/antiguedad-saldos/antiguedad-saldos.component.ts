import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { CurrencyPipe } from '@pipes/currency-format.pipe';
import { PipesModule } from '@pipes/pipes.module';
import { FormsModule } from '@angular/forms';
import { RouterModule, ActivatedRoute } from '@angular/router';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { ModalModule, BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { FinanzasReportesNavComponent } from '@views/finanzas/reportes/finanzas-reportes-nav.component';

@Component({
  selector: 'app-antiguedad-saldos',
  templateUrl: './antiguedad-saldos.component.html',
  standalone: true,
  imports: [CommonModule, PipesModule, FormsModule, RouterModule, TooltipModule, ModalModule, CurrencyPipe, FinanzasReportesNavComponent]
})
export class AntiguedadSaldosComponent implements OnInit {
  public reporte: any = null;
  public loading = false;
  public downloading = false;
  public filtros: any = {};
  public bucketFlags: Record<string, boolean> = {
    '0_30': true,
    '31_60': true,
    '61_90': true,
    '91_mas': true
  };
  public bucketLabels: Record<string, string> = {
    '0_30': '0-30',
    '31_60': '31-60',
    '61_90': '61-90',
    '91_mas': '91+'
  };
  public bucketKeys = ['0_30', '31_60', '61_90', '91_mas'];

  public clientes: any[] = [];
  public proveedores: any[] = [];
  public vendedores: any[] = [];
  public sucursales: any[] = [];
  public empresas: any[] = [];

  modalRef!: BsModalRef;

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private modalService: BsModalService,
    private route: ActivatedRoute
  ) {}

  ngOnInit() {
    const hoy = new Date();
    const yyyy = hoy.getFullYear();
    const mm = String(hoy.getMonth() + 1).padStart(2, '0');
    const dd = String(hoy.getDate()).padStart(2, '0');
    this.filtros = {
      tipo: 'cxc',
      fecha_corte: `${yyyy}-${mm}-${dd}`,
      id_empresa: '',
      id_sucursal: '',
      id_cliente: '',
      id_proveedor: '',
      id_vendedor: ''
    };
    this.cargarListasFiltros();
    this.route.data.subscribe((data) => {
      const tipo = data['tipo'] === 'cxp' ? 'cxp' : 'cxc';
      if (this.filtros.tipo !== tipo) {
        this.filtros.id_cliente = '';
        this.filtros.id_proveedor = '';
        this.filtros.id_vendedor = '';
      }
      this.filtros.tipo = tipo;
      this.cargar();
    });
  }

  cargarListasFiltros() {
    this.apiService.getAll('clientes/list').subscribe(
      (data: any) => { this.clientes = data; },
      (error) => this.alertService.error(error)
    );
    this.apiService.getAll('proveedores/list').subscribe(
      (data: any) => { this.proveedores = data; },
      (error) => this.alertService.error(error)
    );
    this.apiService.getAll('usuarios/list').subscribe(
      (data: any) => { this.vendedores = data; },
      (error) => this.alertService.error(error)
    );
    this.apiService.getAll('sucursales/list').subscribe(
      (data: any) => { this.sucursales = data; },
      (error) => this.alertService.error(error)
    );
    this.apiService.getAll('empresas/list').subscribe(
      (data: any) => { this.empresas = Array.isArray(data) ? data : (data?.data || []); },
      () => { this.empresas = []; }
    );
  }

  bucketsActivos(): string[] {
    return this.bucketKeys.filter((k) => this.bucketFlags[k]);
  }

  params(): any {
    const p: any = {
      tipo: this.filtros.tipo,
      fecha_corte: this.filtros.fecha_corte
    };
    if (this.filtros.id_empresa) p.id_empresa = this.filtros.id_empresa;
    if (this.filtros.id_sucursal) p.id_sucursal = this.filtros.id_sucursal;
    if (this.filtros.tipo === 'cxc') {
      if (this.filtros.id_cliente) p.id_cliente = this.filtros.id_cliente;
      if (this.filtros.id_vendedor) p.id_vendedor = this.filtros.id_vendedor;
    } else if (this.filtros.id_proveedor) {
      p.id_proveedor = this.filtros.id_proveedor;
    }
    const buckets = this.bucketsActivos();
    if (buckets.length && buckets.length < this.bucketKeys.length) {
      p.buckets = buckets;
    }
    return p;
  }

  cargar() {
    this.loading = true;
    this.apiService.getAll('finanzas/antiguedad-saldos', this.params()).subscribe(
      (data) => {
        this.reporte = data;
        this.loading = false;
      },
      (error) => {
        this.alertService.error(error);
        this.loading = false;
      }
    );
  }

  verIndividual(idEntidad: number) {
    if (this.filtros.tipo === 'cxc') {
      this.filtros.id_cliente = idEntidad;
      this.filtros.id_proveedor = '';
    } else {
      this.filtros.id_proveedor = idEntidad;
      this.filtros.id_cliente = '';
    }
    this.cargar();
  }

  volverGlobal() {
    this.filtros.id_cliente = '';
    this.filtros.id_proveedor = '';
    this.cargar();
  }

  openModal(template: TemplateRef<any>) {
    this.alertService.modal = true;
    this.modalRef = this.modalService.show(template);
  }

  aplicarFiltros() {
    if (this.modalRef) this.modalRef.hide();
    this.cargar();
  }

  limpiarFiltros() {
    this.filtros.id_empresa = '';
    this.filtros.id_sucursal = '';
    this.filtros.id_cliente = '';
    this.filtros.id_proveedor = '';
    this.filtros.id_vendedor = '';
    this.bucketKeys.forEach((k) => { this.bucketFlags[k] = true; });
    if (this.modalRef) this.modalRef.hide();
    this.cargar();
  }

  descargarExcel() {
    this.downloading = true;
    this.apiService.export('finanzas/antiguedad-saldos/excel', this.params()).subscribe(
      (data: Blob) => {
        const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `antiguedad-saldos-${this.filtros.tipo}-${this.filtros.fecha_corte}.xlsx`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        this.downloading = false;
      },
      (error) => {
        this.alertService.error(error);
        this.downloading = false;
      }
    );
  }

  abrirPdf() {
    const p = this.params();
    const params = new URLSearchParams();
    Object.keys(p).forEach((k) => {
      const v = p[k];
      if (Array.isArray(v)) {
        params.set(k, v.join(','));
      } else if (v !== undefined && v !== null && v !== '') {
        params.set(k, String(v));
      }
    });
    params.set('token', this.apiService.auth_token());
    const url = `${this.apiService.baseUrl}/api/finanzas/antiguedad-saldos/pdf?${params.toString()}`;
    window.open(url, '_blank', 'width=1000,height=700');
  }

  estadoCuentaCliente(idCliente: number) {
    const url = `${this.apiService.baseUrl}/api/cliente/estado-de-cuenta/${idCliente}?token=${this.apiService.auth_token()}`;
    window.open(url, '_blank', 'width=900,height=700');
  }

  esIndividual(): boolean {
    return this.reporte?.modo === 'individual';
  }

  bucketsVisibles(): string[] {
    return this.reporte?.buckets_activos || this.bucketKeys;
  }
}
