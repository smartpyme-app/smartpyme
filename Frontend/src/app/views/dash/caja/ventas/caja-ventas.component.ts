import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { FacturacionElectronicaService } from '@services/facturacion-electronica/facturacion-electronica.service';
import {
  mensajeErrorHttpFeCr,
  type FeCrErrorEmisionPayload,
} from '@services/facturacion-electronica/fe-cr-http-error.util';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseCrudComponent } from '@shared/base/base-crud.component';
import { ImportarExcelComponent } from '@shared/parts/importar-excel/importar-excel.component';
import { AlertsHaciendaComponent } from '@shared/parts/alerts-hacienda/alerts-hacienda.component';

@Component({
    selector: 'app-caja-ventas',
    templateUrl: './caja-ventas.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, ImportarExcelComponent, AlertsHaciendaComponent],
})

export class CajaVentasComponent extends BaseCrudComponent<any> implements OnInit {

    public ventas: any = {};
    public venta:any = {};
    public sending:boolean = false;

    public clientes:any = [];
    public usuario:any = {};
    public usuarios:any = [];
    public sucursales:any = [];
    public formaPagos:any = [];
    public documentos:any = [];
    public canales:any = [];
    public override filtros:any = {};
    public filtrado:boolean = false;

    constructor(
        protected override apiService: ApiService,
        private facturacionElectronica: FacturacionElectronicaService,
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService
    ){
        super(apiService, alertService, modalManager, {
            endpoint: 'venta',
            itemsProperty: 'ventas',
            itemProperty: 'venta',
            reloadAfterSave: false,
            reloadAfterDelete: false,
            messages: {
                created: 'La venta fue guardada exitosamente.',
                updated: 'La venta fue guardada exitosamente.',
                deleted: 'Venta eliminada exitosamente.',
                createTitle: 'Venta guardado',
                updateTitle: 'Venta guardado',
                deleteTitle: 'Venta eliminado',
                deleteConfirm: '¿Desea eliminar el Registro?'
            },
            afterSave: () => {
                this.filtrarVentas();
            },
            afterDelete: () => {
                // La eliminación manual ya se maneja en el método delete original
            }
        });
    }

    ngOnInit() {
        this.usuario = this.apiService.auth_user();
        this.loadAll();

        this.apiService.getAll('sucursales/list')
          .pipe(this.untilDestroyed())
          .subscribe(sucursales => { 
            this.sucursales = sucursales;
        }, error => {this.alertService.error(error); });
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

    public override loadAll() {
        this.filtros.id_sucursal = '';
        this.filtros.id_cliente = '';
        this.filtros.inicio = '';
        this.filtros.id_usuario = this.apiService.auth_user().id;
        this.filtros.id_canal = '';
        this.filtros.id_documento = '';
        this.filtros.forma_pago = '';
        this.filtros.estado = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'fecha';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 100;

        this.filtrarVentas();
    }

    protected aplicarFiltros(): void {
        this.filtrarVentas();
    }

    public filtrarVentas(){
        this.loading = true;
        this.apiService.getAll('ventas', this.filtros)
          .pipe(this.untilDestroyed())
          .subscribe(ventas => { 
            this.ventas = ventas;
            this.loading = false;
            if(this.modalRef){
                this.closeModal();
            }
        }, error => {this.alertService.error(error); });
    }

    public setEstado(venta:any, estado:any){
        if(estado == 'Pagada'){
            if(confirm('¿Confirma el pago de la venta?')){
                this.venta = venta;
                this.venta.estado = estado;
                this.onSubmit(this.venta, true); // isStatusChange = true
            }
        }
        if(estado == 'Anulada'){
            if(confirm('¿Confirma la anulación de la venta?')){
                this.venta = venta;
                this.venta.estado = estado;
                this.onSubmit(this.venta, true); // isStatusChange = true
            }
        }
    }

    // setPagination() ahora se hereda de BasePaginatedComponent

    public reemprimir(venta:any){
        window.open(this.apiService.baseUrl + '/api/reporte/facturacion/' + venta.id + '?token=' + this.apiService.auth_token(), 'Impresión', 'width=400');
    }

    // Editar

    public openModalEdit(template: TemplateRef<any>, venta:any) {
        this.venta = venta;
        
        this.apiService.getAll('documentos')
          .pipe(this.untilDestroyed())
          .subscribe(documentos => {
            this.documentos = documentos;
        }, error => {this.alertService.error(error);});

        this.apiService.getAll('formas-de-pago')
          .pipe(this.untilDestroyed())
          .subscribe(formaPagos => { 
            this.formaPagos = formaPagos;
        }, error => {this.alertService.error(error); });

        this.openModal(template);
    }
    
    public openFilter(template: TemplateRef<any>) {
        this.apiService.getAll('clientes/list')
          .pipe(this.untilDestroyed())
          .subscribe(clientes => { 
            this.clientes = clientes;
        }, error => {this.alertService.error(error); });

        this.apiService.getAll('formas-de-pago')
          .pipe(this.untilDestroyed())
          .subscribe(formaPagos => { 
            this.formaPagos = formaPagos;
        }, error => {this.alertService.error(error); });
        
        this.apiService.getAll('documentos')
          .pipe(this.untilDestroyed())
          .subscribe(documentos => { 
            this.documentos = documentos;
        }, error => {this.alertService.error(error); });

        this.apiService.getAll('canales')
          .pipe(this.untilDestroyed())
          .subscribe(canales => { 
            this.canales = canales;
        }, error => {this.alertService.error(error); });
        
        this.openModal(template);
    }

    public openDescargar(template: TemplateRef<any>) {
        this.openModal(template);
    }

    public descargarVentas(){
        this.apiService.export('ventas/exportar', this.filtros)
          .pipe(this.untilDestroyed())
          .subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'ventas.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
          }, (error) => {console.error('Error al exportar ventas:', error); }
        );
    }

    public descargarDetalles(){
        this.apiService.export('ventas-detalles/exportar', this.filtros)
          .pipe(this.untilDestroyed())
          .subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'ventas-detalles.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
          }, (error) => {console.error('Error al exportar ventas:', error); }
        );
    }

    public imprimir(venta:any){
        window.open(this.apiService.baseUrl + '/api/reporte/facturacion/' + venta.id + '?token=' + this.apiService.auth_token());
    }

    public linkWompi(venta:any){
        window.open(this.apiService.baseUrl + '/api/venta/wompi-link/' + venta.id + '?token=' + this.apiService.auth_token());
    }


    public openAbono(template: TemplateRef<any>, venta:any){
        this.venta = venta;
        this.openModal(template);
    }

    // DTE

    openDTE(template: TemplateRef<any>, venta:any){
        this.venta = venta;
        this.openModal(template);
        if(!this.venta.dte){
            this.emitirDTE();
        }
    }

    imprimirDTEPDF(venta: any, tipoDte?: string) {
        const t = tipoDte ?? venta.tipo_dte;
        window.open(this.apiService.baseUrl + '/api/reporte/dte/' + venta.id + '/' + t + '/' + '?token=' + this.apiService.auth_token(), 'hola', 'width=400');
    }

    imprimirDTEJSON(venta: any, tipoDte?: string) {
        const t = tipoDte ?? venta.tipo_dte;
        window.open(this.apiService.baseUrl + '/api/reporte/dte-json/' + venta.id + '/' + t + '/' + '?token=' + this.apiService.auth_token(), 'hola', 'width=400');
    }

    imprimirDTEXML(venta: any, tipoDte?: string) {
        const t = tipoDte ?? venta.tipo_dte;
        window.open(this.apiService.baseUrl + '/api/reporte/dte-xml/' + venta.id + '/' + t + '/' + '?token=' + this.apiService.auth_token(), 'hola', 'width=400');
    }

    emitirDTE(){
        this.saving = true;
        this.facturacionElectronica.emitirDTE(this.venta).then((venta) => {
            this.venta = venta;
            this.alertService.success('DTE emitido.', 'El documento ha sido emitido.');
            this.saving = false;
        }).catch((error: any) => {
            this.saving = false;
            if (error?.venta) {
                this.venta = error.venta;
            }
            let msg: string;
            let feCrIntento: FeCrErrorEmisionPayload | undefined;
            if (this.esPayloadErrorEmisionFeCr(error)) {
                msg = error.message;
                feCrIntento = error;
                console.warn('FE CR — JSON del comprobante intentado a emitir:', error.documento);
                if (error.xml_comprobante) {
                    console.warn('FE CR — XML del comprobante (sin firma):', error.xml_comprobante);
                }
            } else if (typeof error === 'string') {
                msg = error;
            } else {
                msg = this.esFeCostaRica()
                    ? mensajeErrorHttpFeCr(error)
                    : String(error?.message ?? error);
            }
            if (this.esFeCostaRica()) {
                this.venta = {
                    ...this.venta,
                    errores: msg,
                    ...(feCrIntento ? { fe_cr_intento_emision: feCrIntento } : {}),
                };
                this.alertService.info(
                    'Comprobante no emitido',
                    feCrIntento
                        ? 'Revise el mensaje y despliegue «XML del comprobante» o «JSON interno» si está disponible.'
                        : 'Revise el mensaje en el recuadro superior.'
                );
            } else {
                this.alertService.warning('Hubo un problema', msg);
            }
        });
    }

    enviarDTE(){
        this.sending = true;
        this.apiService.store('enviarDTE', this.venta)
          .pipe(this.untilDestroyed())
          .subscribe(dte => {
            this.alertService.success('DTE enviado.', 'El DTE fue enviado.');
            this.sending = false;
            setTimeout(()=>{
                if (this.modalRef) {
                    this.closeModal();
                }
            },5000);
        },error => {this.alertService.error(error); this.sending = false; });
    }

    emitirEnContingencia(venta:any){
        this.venta = venta;
        this.saving = true;
        this.facturacionElectronica.emitirDTEContingencia(this.venta).then((venta) => {
            this.venta = venta;
            this.alertService.success('DTE emitido.', 'El documento ha sido emitido.');
            this.saving = false;
        }).catch((error) => {
            this.saving = false;
            this.alertService.warning('Hubo un problema', error);
        });
    }

    esFeCostaRica(): boolean {
        return this.facturacionElectronica.isCostaRicaFe();
    }

    private esPayloadErrorEmisionFeCr(e: unknown): e is FeCrErrorEmisionPayload {
        return (
            typeof e === 'object' &&
            e !== null &&
            'message' in e &&
            'documento' in e &&
            typeof (e as FeCrErrorEmisionPayload).message === 'string'
        );
    }

    consultarFeCr(): void {
        this.saving = true;
        this.facturacionElectronica
            .consultarEstadoFeCrVenta(this.venta.id)
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (res: any) => {
                    if (res?.venta) {
                        this.venta = res.venta;
                    }
                    const ok = !!res?.detalle_estado?.success;
                    const messages = res?.detalle_estado?.messages;
                    if (res?.rechazado) {
                        this.alertService.warning(
                            'Comprobante rechazado en Hacienda',
                            typeof messages === 'string' && messages
                                ? messages
                                : 'Se quitó la clave en el sistema; corrija los datos y vuelva a emitir.'
                        );
                    } else if (ok) {
                        this.alertService.success('Estado en Hacienda', 'Comprobante aceptado.');
                    } else {
                        this.alertService.info(
                            'Estado en Hacienda',
                            typeof messages === 'string' && messages
                                ? messages
                                : 'Aún no consta como aceptado o está en proceso.'
                        );
                    }
                    this.saving = false;
                },
                error: (err) => {
                    this.saving = false;
                    this.alertService.error(err);
                },
            });
    }

    anularDTE(venta:any){
        this.venta = venta;
        if (this.facturacionElectronica.isCostaRicaFe() && venta.sello_mh) {
            if (
                window.confirm(
                    '¿Anular esta venta en el sistema?\n\nSi el comprobante fue aceptado por Hacienda, los ajustes tributarios deben hacerse según las reglas de Costa Rica.'
                )
            ) {
                this.saving = true;
                const v = { ...venta, estado: 'Anulada' };
                this.apiService
                    .store('venta', v)
                    .pipe(this.untilDestroyed())
                    .subscribe({
                        next: () => {
                            this.saving = false;
                            this.alertService.success('Venta anulada', 'Registro actualizado.');
                            this.loadAll();
                            this.modalRef?.hide();
                        },
                        error: (e) => {
                            this.saving = false;
                            this.alertService.error(e);
                        },
                    });
            }
            return;
        }
        if(venta.sello_mh){
            if (confirm('¿Confirma anular la venta y el DTE?')) {
                this.venta = venta;
                this.saving = true;
                this.apiService.store('generarDTEAnulado', this.venta)
                  .pipe(this.untilDestroyed())
                  .subscribe(dte => {
                    // this.alertService.success('DTE generado.');
                    this.venta.dte_invalidacion = dte;
                    this.facturacionElectronica.firmarDTE(dte)
                      .pipe(this.untilDestroyed())
                      .subscribe(dteFirmado => {
                        this.venta.dte_invalidacion.firmaElectronica = dteFirmado.body;
                        
                        if(dteFirmado.status == 'ERROR'){
                            this.alertService.warning('Hubo un problema', dteFirmado.body.mensaje);
                        }
                        
                        this.facturacionElectronica.anularDTE(this.venta, dteFirmado.body)
                          .pipe(this.untilDestroyed())
                          .subscribe(dte => {
                            if ((dte.estado == 'PROCESADO') && dte.selloRecibido) {
                                this.venta.dte_invalidacion.sello = dte.selloRecibido;
                                this.venta.sello_mh = dte.selloRecibido;
                                this.venta.estado = 'Anulada';
                                this.apiService.store('venta', this.venta)
                                  .pipe(this.untilDestroyed())
                                  .subscribe(data => {
                                    // this.alertService.success('Venta guardada.');
                                },error => {this.alertService.error(error); this.saving = false; });
                            }

                            this.alertService.success('DTE anulado.', 'El DTE fue anulado exitosamente.');
                        },error => {
                            if(error.error.descripcionMsg){
                                this.alertService.warning('Hubo un problema', error.error.descripcionMsg);
                            }
                            if(error.error.observaciones.length > 0){
                                this.alertService.warning('Hubo un problema', error.error.observaciones);
                            }
                            this.saving = false;
                        });

                    },error => {this.alertService.error(error);this.saving = false; });

                },error => {this.alertService.error(error);this.saving = false; });
            }
        }
        else{
            if (confirm('¿Confirma anular la venta?')){
                this.venta.estado = 'Anulada';
                this.onSubmit();
            }
        }
    }
}
