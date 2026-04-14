import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { ModalModule } from 'ngx-bootstrap/modal';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TruncatePipe } from '@pipes/truncate.pipe';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-cuentas-cobrar',
  templateUrl: './cuentas-cobrar.component.html',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterModule, TooltipModule, ModalModule, PopoverModule, TruncatePipe, PaginationComponent]
})
export class CuentasCobrarComponent implements OnInit {

    public cobros: any = [];
    public loading = false;
    public downloading = false;
    public filtros: any = {};
    modalRef!: BsModalRef;

    public clientes: any[] = [];
    public vendedores: any[] = [];
    public sucursales: any[] = [];
    public fechaCorte = '';

    constructor(
        public apiService: ApiService,
        private alertService: AlertService,
        private modalService: BsModalService
    ) {}

    ngOnInit() {
        this.loadAll();
        this.cargarListasFiltros();
    }

    cargarListasFiltros() {
        this.apiService.getAll('clientes/list').subscribe(
            (clientes: any) => { this.clientes = clientes; },
            (error) => { this.alertService.error(error); }
        );
        this.apiService.getAll('usuarios/list').subscribe(
            (usuarios: any) => { this.vendedores = usuarios; },
            (error) => { this.alertService.error(error); }
        );
        this.apiService.getAll('sucursales/list').subscribe(
            (sucursales: any) => { this.sucursales = sucursales; },
            (error) => { this.alertService.error(error); }
        );
    }

    loadAll() {
        this.filtros = {
            paginate: this.filtros?.paginate || 10,
            orden: this.filtros?.orden || 'fecha',
            direccion: this.filtros?.direccion || 'desc',
            inicio: '',
            fin: '',
            id_cliente: '',
            id_vendedor: '',
            id_sucursal: '',
            buscador: ''
        };
        this.filtrarCobros();
    }

    filtrarCobros() {
        this.loading = true;
        const params: any = {
            paginate: this.filtros.paginate,
            orden: this.filtros.orden,
            direccion: this.filtros.direccion
        };
        if (this.filtros.inicio) params.inicio = this.filtros.inicio;
        if (this.filtros.fin) params.fin = this.filtros.fin;
        if (this.filtros.id_cliente) params.id_cliente = this.filtros.id_cliente;
        if (this.filtros.id_vendedor) params.id_vendedor = this.filtros.id_vendedor;
        if (this.filtros.id_sucursal) params.id_sucursal = this.filtros.id_sucursal;
        if (this.filtros.buscador) params.buscador = this.filtros.buscador;

        this.apiService.getAll('cuentas-cobrar', params).subscribe(
            (cobros) => {
                this.cobros = cobros;
                this.loading = false;
            },
            (error) => {
                this.alertService.error(error);
                this.loading = false;
            }
        );
    }

    setOrden(columna: string) {
        if (this.filtros.orden === columna) {
            this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
        } else {
            this.filtros.orden = columna;
            this.filtros.direccion = 'asc';
        }
        this.filtrarCobros();
    }

    setEstado(venta: any, estado: string) {
        venta.estado = estado;
        this.apiService.store('venta', venta).subscribe(
            () => {
                this.alertService.success('Venta actualizada', 'La venta fue actualizada exitosamente.');
                this.filtrarCobros();
            },
            (error) => { this.alertService.error(error); }
        );
    }

    setPagination(event: any) {
        this.loading = true;
        const url = this.cobros.path + '?page=' + event.page;
        const params: any = {
            paginate: this.filtros.paginate,
            orden: this.filtros.orden,
            direccion: this.filtros.direccion
        };
        if (this.filtros.inicio) params.inicio = this.filtros.inicio;
        if (this.filtros.fin) params.fin = this.filtros.fin;
        if (this.filtros.id_cliente) params.id_cliente = this.filtros.id_cliente;
        if (this.filtros.id_vendedor) params.id_vendedor = this.filtros.id_vendedor;
        if (this.filtros.id_sucursal) params.id_sucursal = this.filtros.id_sucursal;
        if (this.filtros.buscador) params.buscador = this.filtros.buscador;

        this.apiService.paginate(url, params).subscribe(
            (cobros) => {
                this.cobros = cobros;
                this.loading = false;
            },
            (error) => {
                this.alertService.error(error);
                this.loading = false;
            }
        );
    }

    openModal(template: TemplateRef<any>) {
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template);
    }

    descargarReporte() {
        this.downloading = true;
        const params: any = {
            orden: this.filtros.orden,
            direccion: this.filtros.direccion
        };
        if (this.filtros.inicio) params.inicio = this.filtros.inicio;
        if (this.filtros.fin) params.fin = this.filtros.fin;
        if (this.filtros.id_cliente) params.id_cliente = this.filtros.id_cliente;
        if (this.filtros.id_vendedor) params.id_vendedor = this.filtros.id_vendedor;
        if (this.filtros.id_sucursal) params.id_sucursal = this.filtros.id_sucursal;
        if (this.filtros.buscador) params.buscador = this.filtros.buscador;

        this.apiService.export('cuentas-cobrar/exportar', params).subscribe(
            (data: Blob) => {
                const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'cuentas-por-cobrar.xlsx';
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

    descargarReportePorFechaCorte() {
        if (!this.fechaCorte) return;
        this.downloading = true;
        const params: any = {
            orden: this.filtros.orden,
            direccion: this.filtros.direccion,
            fecha_corte: this.fechaCorte
        };
        if (this.filtros.id_cliente) params.id_cliente = this.filtros.id_cliente;
        if (this.filtros.id_vendedor) params.id_vendedor = this.filtros.id_vendedor;
        if (this.filtros.id_sucursal) params.id_sucursal = this.filtros.id_sucursal;
        if (this.filtros.buscador) params.buscador = this.filtros.buscador;

        this.apiService.export('cuentas-cobrar/exportar', params).subscribe({
            next: (data: Blob) => {
                const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `cuentas-por-cobrar-corte-${this.fechaCorte}.xlsx`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
                this.downloading = false;
                if (this.modalRef) this.modalRef.hide();
            },
            error: (err) => {
                this.alertService.error(err);
                this.downloading = false;
            }
        });
    }

    limpiarFiltros() {
        this.filtros.inicio = '';
        this.filtros.fin = '';
        this.filtros.id_cliente = '';
        this.filtros.id_vendedor = '';
        this.filtros.id_sucursal = '';
        this.filtros.buscador = '';
        this.filtrarCobros();
        if (this.modalRef) {
            this.modalRef.hide();
        }
    }

    montoTotalVenta(venta: any): number {
        const raw = venta?.total;
        if (raw === null || raw === undefined || raw === '') {
            return 0;
        }
        const n = typeof raw === 'number' ? raw : parseFloat(String(raw).replace(',', ''));
        return Number.isFinite(n) ? n : 0;
    }

    etiquetaCliente(venta: any): string {
        if (venta?.nombre_cliente) {
            return String(venta.nombre_cliente);
        }
        const c = venta?.cliente;
        if (c) {
            if (c.tipo === 'Empresa' && c.nombre_empresa) {
                return String(c.nombre_empresa);
            }
            const nombre = [c.nombre, c.apellido].filter(Boolean).join(' ').trim();
            if (nombre) {
                return nombre;
            }
        }
        return 'Consumidor Final';
    }

    getSaldo(venta: any): number {
        const total = this.montoTotalVenta(venta);
        const abonos = parseFloat(venta?.abonos_sum_total || 0);
        const devoluciones = parseFloat(venta?.devoluciones_sum_total || 0);
        return Math.round((total - abonos - devoluciones) * 100) / 100;
    }

    getEstadoCuenta(venta: any): { vigente: boolean; dias: number; texto: string } {
        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0);

        let fechaVence: Date;
        if (venta.fecha_pago) {
            fechaVence = new Date(venta.fecha_pago);
        } else {
            const fechaDoc = new Date(venta.fecha);
            fechaVence = new Date(fechaDoc);
            fechaVence.setDate(fechaVence.getDate() + 30);
        }
        fechaVence.setHours(0, 0, 0, 0);

        const diffMs = fechaVence.getTime() - hoy.getTime();
        const dias = Math.floor(diffMs / (1000 * 60 * 60 * 24));

        if (dias >= 0) {
            return { vigente: true, dias, texto: 'Vigente' };
        } else {
            return { vigente: false, dias, texto: 'Vencido' };
        }
    }
}
