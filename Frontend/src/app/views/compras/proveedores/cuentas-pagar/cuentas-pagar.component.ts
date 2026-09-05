import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { PipesModule } from '@pipes/pipes.module';
import { FormsModule } from '@angular/forms';
import { Router, RouterModule } from '@angular/router';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { ModalModule } from 'ngx-bootstrap/modal';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';
import { FinanzasReportesNavComponent } from '@views/finanzas/reportes/finanzas-reportes-nav.component';

@Component({
  selector: 'app-cuentas-pagar',
  templateUrl: './cuentas-pagar.component.html',
  standalone: true,
  imports: [CommonModule, PipesModule, FormsModule, RouterModule, TooltipModule, ModalModule, FinanzasReportesNavComponent]
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
    public fechaPagoAgricola = '';
    public formatoAgricola = 'csv';

    constructor(
        public apiService: ApiService,
        private alertService: AlertService,
        private modalService: BsModalService,
        private router: Router
    ) {}

    esFinanzasReportes(): boolean {
        return this.router.url.startsWith('/finanzas/reportes');
    }

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

    descargarArchivoBancoAgricola() {
        if (!this.fechaPagoAgricola) {
            return;
        }
        this.downloading = true;
        const params: any = {
            fecha_pago: this.fechaPagoAgricola,
            formato: this.formatoAgricola || 'csv'
        };
        if (this.filtros.id_proveedor) params.id_proveedor = this.filtros.id_proveedor;
        if (this.filtros.id_sucursal) params.id_sucursal = this.filtros.id_sucursal;
        if (this.filtros.buscador) params.buscador = this.filtros.buscador;

        this.apiService.getAll('cuentas-pagar/banco-agricola', params).subscribe({
            next: (res) => {
                this.descargarTexto(res.contenido, res.filename, res.mime);
                const omitidos = Array.isArray(res.omitidos) ? res.omitidos.length : 0;
                const mensaje = omitidos
                    ? `Se incluyeron ${res.incluidos} pagos. Se omitieron ${omitidos}: ${res.omitidos.map((o: any) => (o.proveedor || 'Proveedor') + (o.referencia ? ' (' + o.referencia + ')' : '') + ' — ' + o.motivo).join('; ')}`
                    : `Se incluyeron ${res.incluidos} pagos.`;
                if (omitidos) {
                    this.alertService.warning('Archivo generado con omitidos', mensaje);
                } else {
                    this.alertService.success('Archivo generado', mensaje);
                }
                this.downloading = false;
                if (this.modalRef) this.modalRef.hide();
            },
            error: (err) => {
                this.alertService.error(err);
                this.downloading = false;
            }
        });
    }

    private descargarTexto(contenido: string, filename: string, mime: string) {
        const blob = new Blob([contenido], { type: mime || 'text/plain; charset=UTF-8' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
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
