import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { MHService } from '@services/MH.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BasePaginatedModalComponent, PaginatedResponse } from '@shared/base/base-paginated-modal.component';

@Component({
    selector: 'app-caja-ventas',
    templateUrl: './caja-ventas.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    
})

export class CajaVentasComponent extends BasePaginatedModalComponent implements OnInit {

    public ventas: PaginatedResponse<any> = {} as PaginatedResponse;
    public venta:any = {};
    public override saving:boolean = false;
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
        public mhService: MHService,
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService
    ){
        super(apiService, alertService, modalManager);
    }

    protected getPaginatedData(): PaginatedResponse | null {
        return this.ventas;
    }

    protected setPaginatedData(data: PaginatedResponse): void {
        this.ventas = data;
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

    public loadAll() {
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
                this.onSubmit();
            }
        }
        if(estado == 'Anulada'){
            if(confirm('¿Confirma la anulación de la venta?')){
                this.venta = venta;
                this.venta.estado = estado;
                this.onSubmit();
            }
        }

    }
    

    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('venta/', id)
              .pipe(this.untilDestroyed())
              .subscribe(data => {
                for (let i = 0; i < this.ventas['data'].length; i++) { 
                    if (this.ventas['data'][i].id == data.id )
                        this.ventas['data'].splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
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

    public onSubmit() {
        this.saving = true;            
        this.apiService.store('venta', this.venta)
          .pipe(this.untilDestroyed())
          .subscribe(venta => {
            this.venta = {};
            this.saving = false;
            if(this.modalRef){
                this.closeModal();
            }
            this.alertService.success('Venta guardado', 'La venta fue guardada exitosamente.');
        },error => {this.alertService.error(error); this.saving = false; });

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

    imprimirDTEPDF(venta:any){
        window.open(this.apiService.baseUrl + '/api/reporte/dte/' + venta.id  + '/' + venta.tipo_dte + '/' + '?token=' + this.apiService.auth_token(), 'hola', 'width=400');
    }

    imprimirDTEJSON(venta:any){
        window.open(this.apiService.baseUrl + '/api/reporte/dte-json/' + venta.id + '/' + venta.tipo_dte + '/' + '?token=' + this.apiService.auth_token(), 'hola', 'width=400');
    }

    emitirDTE(){
        this.saving = true;
        this.mhService.emitirDTE(this.venta).then((venta) => {
            this.venta = venta;
            this.alertService.success('DTE emitido.', 'El documento ha sido emitido.');
            this.saving = false;
        }).catch((error) => {
            this.saving = false;
            this.alertService.warning('Hubo un problema', error);
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
        this.mhService.emitirDTEContingencia(this.venta).then((venta) => {
            this.venta = venta;
            this.alertService.success('DTE emitido.', 'El documento ha sido emitido.');
            this.saving = false;
        }).catch((error) => {
            this.saving = false;
            this.alertService.warning('Hubo un problema', error);
        });
    }

    anularDTE(venta:any){
        this.venta = venta;
        if(venta.sello_mh){
            if (confirm('¿Confirma anular la venta y el DTE?')) {
                this.venta = venta;
                this.saving = true;
                this.apiService.store('generarDTEAnulado', this.venta)
                  .pipe(this.untilDestroyed())
                  .subscribe(dte => {
                    // this.alertService.success('DTE generado.');
                    this.venta.dte_invalidacion = dte;
                    this.mhService.firmarDTE(dte)
                      .pipe(this.untilDestroyed())
                      .subscribe(dteFirmado => {
                        this.venta.dte_invalidacion.firmaElectronica = dteFirmado.body;
                        
                        if(dteFirmado.status == 'ERROR'){
                            this.alertService.warning('Hubo un problema', dteFirmado.body.mensaje);
                        }
                        
                        this.mhService.anularDTE(this.venta, dteFirmado.body)
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
