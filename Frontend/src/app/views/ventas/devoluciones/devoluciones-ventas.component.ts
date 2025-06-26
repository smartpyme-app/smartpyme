import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { MHService } from '@services/MH.service';

import Swal from 'sweetalert2';

@Component({
    selector: 'app-devoluciones-ventas',
    templateUrl: './devoluciones-ventas.component.html'
})

export class DevolucionesVentasComponent implements OnInit {

    public ventas: any = [];
    public id_venta: any = null;
    public loading: boolean = false;
    public saving: boolean = false;
    public sending: boolean = false;
    public downloading: boolean = false;

    public clientes: any = [];
    public usuarios: any = [];
    public usuariosEmpresa: any = [];
    public ventasList: any = [];
    public sucursales: any = [];
    public venta: any = {};
    public filtros: any = {};
    public devolucionEditar: any = {};
    public documentos: any = [];
    public modalAbierto: boolean = false;
    public modalCerrandose: boolean = false;

    modalRef!: BsModalRef;

    constructor(public apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService, private mhService: MHService,
    ) { }

    ngOnInit() {
        this.loadAll();
        this.apiService.getAll('clientes/list').subscribe(clientes => {
            this.clientes = clientes;
        }, error => { this.alertService.error(error); });
    }

    public loadAll() {
        this.loading = true;
        // this.filtros.inicio = this.apiService.date();
        // this.filtros.fin = this.apiService.date();
        this.filtros.id_sucursal = '';
        this.filtros.estado = '';
        this.filtros.id_cliente = '';
        this.filtros.id_usuario = '';
        this.filtros.orden = 'fecha';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;
        this.filtrarVentas();
    }

    public filtrarVentas() {
        this.loading = true;
        if (this.filtros.id_cliente == null) {
            this.filtros.id_cliente = '';
        }
        this.apiService.getAll('devoluciones/ventas', this.filtros).subscribe(ventas => {
            this.ventas = ventas;
            this.loading = false;
            if (this.modalRef) {
                this.modalRef.hide();
            }
        }, error => { this.alertService.error(error); });
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
            } else if (result.dismiss === Swal.DismissReason.cancel) {
                // Swal.fire('Cancelado', 'Tu archivo está seguro :)', 'info');
            }
        });

    }

    public onSubmit() {
        this.apiService.store('devolucion/venta', this.venta).subscribe(venta => {
            this.alertService.success('Venta actualizada', 'La venta fue actualizada exitosamente.');
        }, error => { this.alertService.error(error); });
    }

    public delete(id: number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('devolucion/venta/', id).subscribe(data => {
                for (let i = 0; i < this.ventas['data'].length; i++) {
                    if (this.ventas['data'][i].id == data.id)
                        this.ventas['data'].splice(i, 1);
                }
            }, error => { this.alertService.error(error); });

        }

    }

    public setOrden(columna: string) {
        if (this.filtros.orden === columna) {
            this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
        } else {
            this.filtros.orden = columna;
            this.filtros.direccion = 'asc';
        }

        this.filtrarVentas();
    }

    public setPagination(event: any): void {
        this.loading = true;
        this.apiService.paginate(this.ventas.path + '?page=' + event.page, this.filtros).subscribe(ventas => {
            this.ventas = ventas;
            this.loading = false;
        }, error => { this.alertService.error(error); this.loading = false; });
    }

    // Filtros

    openFilter(template: TemplateRef<any>) {

        this.apiService.getAll('clientes/list').subscribe(clientes => {
            this.clientes = clientes;
        }, error => { this.alertService.error(error); });


        this.apiService.getAll('usuarios/list').subscribe(usuarios => {
            this.usuarios = usuarios;
        }, error => { this.alertService.error(error); });

        this.modalRef = this.modalService.show(template);
    }

    openModal(template: TemplateRef<any>) {
        this.id_venta = null;
        this.loading = true;
        this.apiService.getAll('ventas/sin-devolucion').subscribe(ventas => {
            this.ventasList = ventas;
            this.loading = false;
        }, error => { this.alertService.error(error); });
        this.modalRef = this.modalService.show(template);
    }

    public imprimir(venta: any) {
        window.open(this.apiService.baseUrl + '/api/devolucion/facturacion/impresion/' + venta.id + '?token=' + this.apiService.auth_token());
    }

    public descargar() {
        this.downloading = true;
        this.apiService.export('devoluciones/ventas/exportar', this.filtros).subscribe((data: Blob) => {
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
        }, (error) => { this.alertService.error(error); this.downloading = false; }
        );
    }

    // DTE

    openDTE(template: TemplateRef<any>, venta: any) {
        this.venta = venta;
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template);
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
        this.mhService.emitirDTENotaCredito(this.venta).then((venta) => {
            this.venta = venta;
            this.alertService.success('DTE emitido.', 'El documento ha sido emitido.');
            this.saving = false;
            this.enviarDTE(this.venta);
        }).catch((error) => {
            this.saving = false;
            this.alertService.warning('Hubo un problema', error);
        });
    }

    enviarDTE(venta: any) {
        this.sending = true;
        this.apiService.store('enviarDTENotaCredito', venta).subscribe(dte => {
            this.alertService.success('DTE enviado.', 'El DTE fue enviado.');
            this.sending = false;
            setTimeout(() => {
                this.modalRef?.hide();
            }, 5000);
        }, error => { this.alertService.error(error); this.sending = false; });
    }

    anularDTE(venta: any) {
        this.venta = venta;
        if (venta.dte) {
            if (confirm('¿Confirma anular la devolución y el DTE?')) {
                this.venta = venta;
                this.saving = true;
                this.apiService.store('generarDTEAnulado', this.venta).subscribe(dte => {
                    // this.alertService.success('DTE generado.');
                    this.venta.dte_invalidacion = dte;
                    this.mhService.firmarDTE(dte).subscribe(dteFirmado => {
                        this.venta.dte_invalidacion.firmaElectronica = dteFirmado.body;

                        if (dteFirmado.status == 'ERROR') {
                            this.alertService.warning('Hubo un problema', dteFirmado.body.mensaje);
                        }

                        this.mhService.anularDTE(this.venta, dteFirmado.body).subscribe(dte => {
                            if ((dte.estado == 'PROCESADO') && dte.selloRecibido) {
                                this.venta.dte_invalidacion.sello = dte.selloRecibido;
                                this.venta.sello_mh = dte.selloRecibido;
                                this.venta.enable = false;
                                this.apiService.store('devolucion/venta', this.venta).subscribe(data => {
                                    // this.alertService.success('Venta guardada.');
                                }, error => { this.alertService.error(error); this.saving = false; });
                            }

                            this.alertService.success('DTE anulado.', 'El DTE fue anulado exitosamente.');
                        }, error => {
                            if (error.error.descripcionMsg) {
                                this.alertService.warning('Hubo un problema', error.error.descripcionMsg);
                            }
                            if (error.error.observaciones.length > 0) {
                                this.alertService.warning('Hubo un problema', error.error.observaciones);
                            }
                            this.saving = false;
                        });

                    }, error => { this.alertService.error(error); this.saving = false; });

                }, error => { this.alertService.error(error); this.saving = false; });
            }
        }
        else {
            this.setEstado(venta, '0');
        }
    }

    editarDevolucion(template: TemplateRef<any>, venta: any) {
    
        const ventaActualizada = this.ventas.data.find((v: any) => v.id === venta.id);
        
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
            this.apiService.getAll('documentos/list').subscribe(documentos => { 
                this.documentos = documentos;
            }, error => { this.alertService.error(error); });
        }
        if (this.usuarios.length === 0) {
            this.apiService.getAll('usuarios/list-edit-devolucion').subscribe(usuarios => { 
                this.usuarios = usuarios; 
            }, error => {this.alertService.error(error); });
        }
    
        this.modalRef = this.modalService.show(template);
    }

    public actualizarDevolucion() {
        this.saving = true;

        this.apiService.store('devolucion/venta/actualizar', this.devolucionEditar).subscribe(devolucion => {
            
            this.modalRef?.hide()
            this.saving = false;
            
            this.filtrarVentas();
            
            setTimeout(() => {
                this.devolucionEditar = {};
                this.alertService.success('Devolución actualizada', 'La devolución fue actualizada exitosamente.');
            }, 200);

        }, error => {
            this.alertService.error(error);
            this.saving = false;
        });
    }

}
