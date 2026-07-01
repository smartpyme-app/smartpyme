import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { MHService } from '@services/MH.service';
import {
  MAX_DIAS_EXPORT_DETALLES,
  MAX_DIAS_EXPORT_VENTAS,
  validarPeriodoExport,
  esErrorTimeoutExport,
  mensajeErrorTimeoutExport,
} from '../../../../helpers/export-period.helper';

@Component({
  selector: 'app-caja-ventas',
  templateUrl: './caja-ventas.component.html'
})

export class CajaVentasComponent implements OnInit {

    public ventas:any = [];
    public venta:any = {};
    public loading:boolean = false;
    public saving:boolean = false;
    public sending:boolean = false;

    public clientes:any = [];
    public usuario:any = {};
    public usuarios:any = [];
    public sucursales:any = [];
    public formaPagos:any = [];
    public documentos:any = [];
    public canales:any = [];
    public filtros:any = {};
    public filtrado:boolean = false;

    modalRef!: BsModalRef;

    constructor(public apiService: ApiService, public mhService: MHService, private alertService: AlertService,
                private modalService: BsModalService
    ){}

    ngOnInit() {
        this.usuario = this.apiService.auth_user();
        this.loadAll();

        this.apiService.getAll('sucursales/list').subscribe(sucursales => { 
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
        this.apiService.getAll('ventas', this.filtros).subscribe(ventas => { 
            this.ventas = ventas;
            this.loading = false;
            if(this.modalRef){
                this.modalRef.hide();
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
            this.apiService.delete('venta/', id) .subscribe(data => {
                for (let i = 0; i < this.ventas['data'].length; i++) { 
                    if (this.ventas['data'][i].id == data.id )
                        this.ventas['data'].splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }

    }

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.ventas.path + '?page='+ event.page, this.filtros).subscribe(ventas => { 
            this.ventas = ventas;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public reemprimir(venta:any){
        window.open(this.apiService.baseUrl + '/api/reporte/facturacion/' + venta.id + '?token=' + this.apiService.auth_token(), 'Impresión', 'width=400');
    }

    // Editar

    public openModalEdit(template: TemplateRef<any>, venta:any) {
        this.venta = venta;
        
        this.apiService.getAll('documentos').subscribe(documentos => {
            this.documentos = documentos;
        }, error => {this.alertService.error(error);});

        this.apiService.getAll('formas-de-pago').subscribe(formaPagos => { 
            this.formaPagos = formaPagos;
        }, error => {this.alertService.error(error); });

        this.modalRef = this.modalService.show(template);
    }
    
    public openFilter(template: TemplateRef<any>) {
        this.apiService.getAll('clientes/list').subscribe(clientes => { 
            this.clientes = clientes;
        }, error => {this.alertService.error(error); });

        this.apiService.getAll('formas-de-pago').subscribe(formaPagos => { 
            this.formaPagos = formaPagos;
        }, error => {this.alertService.error(error); });
        
        this.apiService.getAll('documentos').subscribe(documentos => { 
            this.documentos = documentos;
        }, error => {this.alertService.error(error); });

        this.apiService.getAll('canales').subscribe(canales => { 
            this.canales = canales;
        }, error => {this.alertService.error(error); });
        
        this.modalRef = this.modalService.show(template);
    }

    public openDescargar(template: TemplateRef<any>) {
        this.modalRef = this.modalService.show(template);
    }

    public descargarVentas(){
        const hoy = new Date().toISOString().split('T')[0];
        const filtrosExport = {
            ...this.filtros,
            inicio: this.filtros.inicio || hoy,
            fin: this.filtros.fin || hoy,
        };
        const check = validarPeriodoExport(filtrosExport.inicio, filtrosExport.fin, MAX_DIAS_EXPORT_VENTAS);
        if (!check.valid) {
            this.alertService.error(check.error);
            return;
        }
        this.apiService.export('ventas/exportar', filtrosExport).subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'ventas.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
          }, (error) => {
            if (esErrorTimeoutExport(error)) {
                this.alertService.error(mensajeErrorTimeoutExport(MAX_DIAS_EXPORT_VENTAS));
            } else {
                this.alertService.error(error);
            }
          }
        );
    }

    public descargarDetalles(){
        const hoy = new Date().toISOString().split('T')[0];
        const filtrosExport = {
            ...this.filtros,
            inicio: this.filtros.inicio || hoy,
            fin: this.filtros.fin || hoy,
        };
        const check = validarPeriodoExport(filtrosExport.inicio, filtrosExport.fin, MAX_DIAS_EXPORT_DETALLES);
        if (!check.valid) {
            this.alertService.error(check.error);
            return;
        }
        this.apiService.export('ventas-detalles/exportar', filtrosExport).subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'ventas-detalles.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
          }, (error) => {
            if (esErrorTimeoutExport(error)) {
                this.alertService.error(mensajeErrorTimeoutExport(MAX_DIAS_EXPORT_DETALLES));
            } else {
                this.alertService.error(error);
            }
          }
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
        this.apiService.store('venta', this.venta).subscribe(venta => {
            this.venta = {};
            this.saving = false;
            if(this.modalRef){
                this.modalRef.hide();
            }
            this.alertService.success('Venta guardado', 'La venta fue guardada exitosamente.');
        },error => {this.alertService.error(error); this.saving = false; });

    }

    public openAbono(template: TemplateRef<any>, venta:any){
        this.venta = venta;
        this.modalRef = this.modalService.show(template);
    }

    // DTE

    openDTE(template: TemplateRef<any>, venta:any){
        this.venta = venta;
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template);
        if(!this.venta.dte){
            this.emitirDTE();
        }
    }

    emitirDTE(){
        this.saving = true;
        this.mhService.emitirDTE(this.venta).then((venta: any) => {
            this.venta = venta;
            this.alertService.success('DTE emitido.', 'El documento ha sido emitido.');
            this.saving = false;
        }).catch((error: unknown) => {
            this.saving = false;
            this.alertService.warning('Hubo un problema', error);
        });
    }

    enviarDTE(){
        this.sending = true;
        this.apiService.store('enviarDTE', this.venta).subscribe(dte => {
            this.alertService.success('DTE enviado.', 'El DTE fue enviado.');
            this.sending = false;
            setTimeout(()=>{
                this.modalRef?.hide();
            },5000);
        },error => {this.alertService.error(error); this.sending = false; });
    }

    emitirEnContingencia(venta:any){
        this.venta = venta;
        this.saving = true;
        this.mhService.emitirDTEContingencia(this.venta).then((venta: any) => {
            this.venta = venta;
            this.alertService.success('DTE emitido.', 'El documento ha sido emitido.');
            this.saving = false;
        }).catch((error: unknown) => {
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
                this.apiService.store('generarDTEAnulado', this.venta).subscribe(dte => {
                    // this.alertService.success('DTE generado.');
                    this.venta.dte_invalidacion = dte;
                    this.mhService.firmarDTE(dte).subscribe((dteFirmado: any) => {
                        this.venta.dte_invalidacion.firmaElectronica = dteFirmado.body;
                        
                        if(dteFirmado.status == 'ERROR'){
                            this.alertService.warning('Hubo un problema', dteFirmado.body.mensaje);
                        }
                        
                        this.mhService.anularDTE(this.venta, dteFirmado.body).subscribe((dte: any) => {
                            if ((dte.estado == 'PROCESADO') && dte.selloRecibido) {
                                this.venta.dte_invalidacion.sello = dte.selloRecibido;
                                this.venta.sello_mh = dte.selloRecibido;
                                this.venta.estado = 'Anulada';
                                this.apiService.store('venta', this.venta).subscribe(data => {
                                    // this.alertService.success('Venta guardada.');
                                },error => {this.alertService.error(error); this.saving = false; });
                            }

                            this.alertService.success('DTE anulado.', 'El DTE fue anulado exitosamente.');
                        }, (error: unknown) => {
                            const err = error as { error?: { descripcionMsg?: string; observaciones?: unknown[] } };
                            if (err.error?.descripcionMsg) {
                                this.alertService.warning('Hubo un problema', err.error.descripcionMsg);
                            }
                            if (err.error?.observaciones && err.error.observaciones.length > 0) {
                                this.alertService.warning('Hubo un problema', err.error.observaciones);
                            }
                            this.saving = false;
                        });

                    }, (error: unknown) => {this.alertService.error(error);this.saving = false; });

                }, (error: unknown) => {this.alertService.error(error);this.saving = false; });
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
