import { Component, OnInit, TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { NgSelectModule } from '@ng-select/ng-select';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { FacturacionElectronicaService } from '@services/facturacion-electronica/facturacion-electronica.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { TruncatePipe } from '@pipes/truncate.pipe';
import { BaseCrudComponent } from '@shared/base/base-crud.component';
import { LazyImageDirective } from '../../../directives/lazy-image.directive';
import Swal from 'sweetalert2';

@Component({
    selector: 'app-devoluciones-ventas',
    templateUrl: './devoluciones-ventas.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, PaginationComponent, TruncatePipe, PopoverModule, TooltipModule, LazyImageDirective],
    changeDetection: ChangeDetectionStrategy.OnPush
})

export class DevolucionesVentasComponent extends BaseCrudComponent<any> implements OnInit {

    public ventas:any = {};
    public id_venta: any = null;
    public sending: boolean = false;
    public downloading: boolean = false;
    public clientes: any = [];
    public usuarios: any = [];
    public usuariosEmpresa: any = [];
    public ventasList: any = [];
    public sucursales: any = [];
    public venta: any = {};
    public devolucionEditar: any = {};
    public documentos: any = [];
    public modalAbierto: boolean = false;
    public modalCerrandose: boolean = false;

    constructor(
        apiService: ApiService, 
        alertService: AlertService,
        modalManager: ModalManagerService,
        private facturacionElectronica: FacturacionElectronicaService,
        private cdr: ChangeDetectorRef
    ) {
        super(apiService, alertService, modalManager, {
            endpoint: 'devolucion/venta',
            itemsProperty: 'ventas',
            itemProperty: 'venta',
            reloadAfterSave: false,
            reloadAfterDelete: false,
            messages: {
                created: 'La venta fue actualizada exitosamente.',
                updated: 'La venta fue actualizada exitosamente.',
                createTitle: 'Venta actualizada',
                updateTitle: 'Venta actualizada'
            }
        });
    }

    protected aplicarFiltros(): void {
        this.filtrarVentas();
    }

    ngOnInit() {
        this.loadAll();
        this.apiService.getAll('clientes/list')
            .pipe(this.untilDestroyed())
            .subscribe(clientes => {
                this.clientes = clientes;
                this.cdr.markForCheck();
            }, error => { this.alertService.error(error); this.cdr.markForCheck(); });
    }

    public override loadAll() {
        this.loading = true;
        this.cdr.markForCheck();
        this.filtros.id_sucursal = '';
        this.filtros.estado = '';
        this.filtros.id_cliente = '';
        this.filtros.id_usuario = '';
        this.filtros.id_documento = '';
        this.filtros.orden = 'fecha';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;
        this.filtrarVentas();
    }

    public filtrarVentas() {
        this.loading = true;
        this.cdr.markForCheck();
        if (this.filtros.id_cliente == null) {
            this.filtros.id_cliente = '';
        }
        this.apiService.getAll('devoluciones/ventas', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe(ventas => {
                this.ventas = ventas;
                this.loading = false;
                if (this.modalRef) {
                    this.closeModal();
                }
                this.cdr.markForCheck();
            }, error => { this.alertService.error(error); this.loading = false; this.cdr.markForCheck(); });
    }

    public setEstado(venta: any, enable: string) {
        Swal.fire({
            title: '¿Estás seguro?',
            text: '¡No podrás revertir esto!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, anularlo',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                this.venta = venta;
                this.venta.enable = enable;
                this.onSubmit();
            }
        });
    }

    public override delete(item: any | number): void {
        const itemToDelete = typeof item === 'number' ? item : (item as any).id;
        
        if (!confirm('¿Desea eliminar el Registro?')) {
            return;
        }

        this.loading = true;
        this.apiService.delete('devolucion/venta/', itemToDelete)
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (deletedItem: any) => {
                    const index = this.ventas.data?.findIndex((v: any) => v.id === deletedItem.id);
                    if (index !== -1 && index >= 0) {
                        this.ventas.data.splice(index, 1);
                    }
                    this.alertService.success('Registro eliminado', 'El registro fue eliminado exitosamente.');
                    this.loading = false;
                },
                error: (error: any) => {
                    this.alertService.error(error);
                    this.loading = false;
                }
            });
    }

    public setOrden(columna: string) {
        if (this.filtros.orden === columna) {
            this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
        } else {
            this.filtros.orden = columna;
            this.filtros.direccion = 'asc';
        }
        this.cdr.markForCheck();
        this.filtrarVentas();
    }

    openFilter(template: TemplateRef<any>) {
        this.apiService.getAll('clientes/list')
            .pipe(this.untilDestroyed())
            .subscribe(clientes => {
                this.clientes = clientes;
                this.cdr.markForCheck();
            }, error => { this.alertService.error(error); this.cdr.markForCheck(); });

        this.apiService.getAll('usuarios/list')
            .pipe(this.untilDestroyed())
            .subscribe(usuarios => {
                this.usuarios = usuarios;
                this.cdr.markForCheck();
            }, error => { this.alertService.error(error); this.cdr.markForCheck(); });

        if (!this.documentos.length) {
            this.apiService.getAll('documentos/list-nombre')
                .pipe(this.untilDestroyed())
                .subscribe(
                    (documentos) => {
                        this.documentos = documentos;
                        this.cdr.markForCheck();
                    },
                    (error) => {
                        this.alertService.error(error);
                        this.cdr.markForCheck();
                    }
                );
        }

        super.openModal(template);
    }

    override openModal(template: TemplateRef<any>) {
        this.id_venta = null;
        this.loading = true;
        this.apiService.getAll('ventas/sin-devolucion')
            .pipe(this.untilDestroyed())
            .subscribe(ventas => {
                this.ventasList = ventas;
                this.loading = false;
                this.cdr.markForCheck();
            }, error => { this.alertService.error(error); this.loading = false; this.cdr.markForCheck(); });
        super.openModal(template);
    }

    public imprimir(venta: any) {
        window.open(this.apiService.baseUrl + '/api/devolucion/facturacion/impresion/' + venta.id + '?token=' + this.apiService.auth_token());
    }

    public descargar() {
        this.downloading = true;
        this.apiService.export('devoluciones/ventas/exportar', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe((data: Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'devoluciones-ventas.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloading = false;
            this.cdr.markForCheck();
        }, (error) => { this.alertService.error(error); this.downloading = false; this.cdr.markForCheck(); }
        );
    }

    // DTE

    openDTE(template: TemplateRef<any>, venta: any) {
        this.venta = venta;
        this.openModal(template);
        if (!this.venta.dte) {
            this.emitirDTE();
        }
    }

    imprimirDTEPDF(venta: any) {
        window.open(this.apiService.baseUrl + '/api/reporte/dte/' + venta.id + '/05/' + '?token=' + this.apiService.auth_token(), 'hola', 'width=400');
    }

    imprimirDTEJSON(venta: any) {
        window.open(this.apiService.baseUrl + '/api/reporte/dte-json/' + venta.id + '/05/' + '?token=' + this.apiService.auth_token(), 'hola', 'width=400');
    }

    emitirDTE() {
        this.saving = true;
        this.facturacionElectronica.emitirDTENotaCredito(this.venta).then((doc) => {
            this.venta = doc;
            this.alertService.success('DTE emitido.', 'El documento ha sido emitido.');
            this.saving = false;
            if (this.facturacionElectronica.requiereFlujoEnviarDteSeparado()) {
                this.enviarDTE(this.venta);
            } else {
                this.cdr.markForCheck();
            }
        }).catch((error: any) => {
            this.saving = false;
            if (error?.devolucion) {
                this.venta = error.devolucion;
            }
            const msg = typeof error === 'string' ? error : error?.message ?? 'Hubo un problema';
            this.alertService.warning('Comprobante electrónico', msg);
            this.cdr.markForCheck();
        });
    }

    enviarDTE(venta: any) {
        this.sending = true;
        this.apiService.store('enviarDTE', venta)
            .pipe(this.untilDestroyed())
            .subscribe({
                next: () => {
                    this.alertService.success('DTE enviado.', 'El DTE fue enviado.');
                    this.sending = false;
                    setTimeout(() => {
                        this.closeModal();
                    }, 5000);
                    this.cdr.markForCheck();
                },
                error: (error) => {
                    this.alertService.error(error);
                    this.sending = false;
                    this.cdr.markForCheck();
                }
            });
    }

    anularDTE(venta: any) {
        this.venta = venta;
        if (venta.dte) {
            if (confirm('¿Confirma anular la devolución y el DTE?')) {
                this.venta = venta;
                this.saving = true;
                this.apiService.store('generarDTEAnulado', this.venta)
                    .pipe(this.untilDestroyed())
                    .subscribe({
                        next: (dte) => {
                            this.venta.dte_invalidacion = dte;
                            this.facturacionElectronica.firmarDTE(dte)
                                .pipe(this.untilDestroyed())
                                .subscribe({
                                    next: (dteFirmado) => {
                                        this.venta.dte_invalidacion.firmaElectronica = dteFirmado.body;

                                        if (dteFirmado.status == 'ERROR') {
                                            this.alertService.warning('Hubo un problema', dteFirmado.body.mensaje);
                                        }

                                        this.facturacionElectronica.anularDTE(this.venta, dteFirmado.body)
                                            .pipe(this.untilDestroyed())
                                            .subscribe({
                                                next: (dte) => {
                                                    if ((dte.estado == 'PROCESADO') && dte.selloRecibido) {
                                                        this.venta.dte_invalidacion.sello = dte.selloRecibido;
                                                        this.venta.sello_mh = dte.selloRecibido;
                                                        this.venta.enable = false;
                                                        this.apiService.store('devolucion/venta', this.venta)
                                                            .pipe(this.untilDestroyed())
                                                            .subscribe({
                                                                next: () => {
                                                                    // this.alertService.success('Venta guardada.');
                                                                },
                                                                error: (error) => {
                                                                    this.alertService.error(error);
                                                                    this.saving = false;
                                                                }
                                                            });
                                                    }

                                                    this.alertService.success('DTE anulado.', 'El DTE fue anulado exitosamente.');
                                                },
                                                error: (error) => {
                                                    if (error.error.descripcionMsg) {
                                                        this.alertService.warning('Hubo un problema', error.error.descripcionMsg);
                                                    }
                                                    if (error.error.observaciones?.length > 0) {
                                                        this.alertService.warning('Hubo un problema', error.error.observaciones);
                                                    }
                                                    this.saving = false;
                                                }
                                            });
                                    },
                                    error: (error) => {
                                        this.alertService.error(error);
                                        this.saving = false;
                                    }
                                });
                        },
                        error: (error) => {
                            this.alertService.error(error);
                            this.saving = false;
                        }
                    });
            }
        }
        else {
            this.setEstado(venta, '0');
        }
    }

    editarDevolucion(template: TemplateRef<any>, venta: any) {
        const ventaActualizada = this.ventas.data?.find((v: any) => v.id === venta.id);
        
        if (!ventaActualizada) {
            console.error('No se encontró la venta actualizada');
            return;
        }
        
        this.devolucionEditar = {
            id: ventaActualizada.id,
            fecha: ventaActualizada.fecha,
            id_documento: ventaActualizada.id_documento,
            correlativo: ventaActualizada.correlativo,
            id_usuario: ventaActualizada.id_usuario,
            observaciones: ventaActualizada.observaciones
        };
    
        if (this.documentos.length === 0) {
            this.apiService.getAll('documentos/list-nombre')
                .pipe(this.untilDestroyed())
                .subscribe(documentos => {
                    this.documentos = documentos;
                }, error => { this.alertService.error(error); });
        }
        if (this.usuarios.length === 0) {
            this.apiService.getAll('usuarios/list-edit-devolucion')
                .pipe(this.untilDestroyed())
                .subscribe(usuarios => { 
                    this.usuarios = usuarios; 
                }, error => {this.alertService.error(error); });
        }
    
        super.openModal(template);
    }

    public actualizarDevolucion() {
        this.saving = true;
        this.cdr.markForCheck();

        this.apiService.store('devolucion/venta/actualizar', this.devolucionEditar)
            .pipe(this.untilDestroyed())
            .subscribe({
                next: () => {
                    this.closeModal();
                    this.saving = false;
                    this.filtrarVentas();
                    setTimeout(() => {
                        this.devolucionEditar = {};
                        this.alertService.success('Devolución actualizada', 'La devolución fue actualizada exitosamente.');
                        this.cdr.markForCheck();
                    }, 200);
                },
                error: (error) => {
                    this.alertService.error(error);
                    this.saving = false;
                    this.cdr.markForCheck();
                }
            });
    }

}
