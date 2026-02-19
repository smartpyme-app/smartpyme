import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';

@Component({
  selector: 'app-cuentas-pagar',
  templateUrl: './cuentas-pagar.component.html'
})
export class CuentasPagarComponent implements OnInit {

    public pagos: any = [];
    public loading = false;
    public downloading = false;
    public filtros: any = {};
    modalRef!: BsModalRef;

    public proveedores: any[] = [];
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
        this.apiService.getAll('proveedores/list').subscribe(
            (proveedores: any) => { this.proveedores = proveedores; },
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
            id_proveedor: '',
            id_sucursal: '',
            buscador: ''
        };
        this.filtrarPagos();
    }

    filtrarPagos() {
        this.loading = true;
        const params: any = {
            paginate: this.filtros.paginate,
            orden: this.filtros.orden,
            direccion: this.filtros.direccion
        };
        if (this.filtros.inicio) params.inicio = this.filtros.inicio;
        if (this.filtros.fin) params.fin = this.filtros.fin;
        if (this.filtros.id_proveedor) params.id_proveedor = this.filtros.id_proveedor;
        if (this.filtros.id_sucursal) params.id_sucursal = this.filtros.id_sucursal;
        if (this.filtros.buscador) params.buscador = this.filtros.buscador;

        this.apiService.getAll('cuentas-pagar', params).subscribe(
            (pagos) => {
                this.pagos = pagos;
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
        this.filtrarPagos();
    }

    setEstado(compra: any, estado: string) {
        compra.estado = estado;
        this.apiService.store('compra', compra).subscribe(
            () => {
                this.alertService.success('Compra actualizada', 'La compra fue actualizada exitosamente.');
                this.filtrarPagos();
            },
            (error) => { this.alertService.error(error); }
        );
    }

    setPagination(event: any) {
        this.loading = true;
        const url = this.pagos.path + '?page=' + event.page;
        const params: any = {
            paginate: this.filtros.paginate,
            orden: this.filtros.orden,
            direccion: this.filtros.direccion
        };
        if (this.filtros.inicio) params.inicio = this.filtros.inicio;
        if (this.filtros.fin) params.fin = this.filtros.fin;
        if (this.filtros.id_proveedor) params.id_proveedor = this.filtros.id_proveedor;
        if (this.filtros.id_sucursal) params.id_sucursal = this.filtros.id_sucursal;
        if (this.filtros.buscador) params.buscador = this.filtros.buscador;

        this.apiService.paginate(url, params).subscribe(
            (pagos) => {
                this.pagos = pagos;
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
        if (this.filtros.id_proveedor) params.id_proveedor = this.filtros.id_proveedor;
        if (this.filtros.id_sucursal) params.id_sucursal = this.filtros.id_sucursal;
        if (this.filtros.buscador) params.buscador = this.filtros.buscador;

        this.apiService.export('cuentas-pagar/exportar', params).subscribe(
            (data: Blob) => {
                const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'cuentas-por-pagar.xlsx';
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
        if (this.filtros.id_proveedor) params.id_proveedor = this.filtros.id_proveedor;
        if (this.filtros.id_sucursal) params.id_sucursal = this.filtros.id_sucursal;
        if (this.filtros.buscador) params.buscador = this.filtros.buscador;

        this.apiService.export('cuentas-pagar/exportar', params).subscribe({
            next: (data: Blob) => {
                const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `cuentas-por-pagar-corte-${this.fechaCorte}.xlsx`;
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
        this.filtros.id_proveedor = '';
        this.filtros.id_sucursal = '';
        this.filtros.buscador = '';
        this.filtrarPagos();
        if (this.modalRef) {
            this.modalRef.hide();
        }
    }

    getSaldo(compra: any): number {
        const total = parseFloat(compra?.total || 0);
        const abonos = parseFloat(compra?.abonos_sum_total || 0);
        const devoluciones = parseFloat(compra?.devoluciones_sum_total || 0);
        return Math.round((total - abonos - devoluciones) * 100) / 100;
    }

    getEstadoCuenta(compra: any): { vigente: boolean; dias: number; texto: string } {
        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0);

        let fechaVence: Date;
        if (compra.fecha_pago) {
            fechaVence = new Date(compra.fecha_pago);
        } else {
            const fechaDoc = new Date(compra.fecha);
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
