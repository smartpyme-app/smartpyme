import { Component, OnInit, TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { MHService } from '@services/MH.service';

@Component({
  selector: 'app-gastos',
  templateUrl: './gastos.component.html'
})

export class GastosComponent implements OnInit {

    public gastos:any = [];
    public gasto:any = {};
    public loading:boolean = false;
    public saving:boolean = false;
    public sending:boolean = false;
    public downloading:boolean = false;

    public clientes:any = [];
    public usuarios:any = [];
    public proyectos:any = [];
    public sucursales:any = [];
    public proveedores:any = [];
    public areas:any = [];
    public filtros:any = {};

    modalRef!: BsModalRef;

    constructor(public apiService: ApiService, public mhService: MHService, private alertService: AlertService,
                private modalService: BsModalService, private router: Router, private route: ActivatedRoute
    ){}

    ngOnInit() {
        this.route.queryParams.subscribe(params => {
            this.filtros = {
                buscador: params['buscador'] || '',
                id_proyecto: +params['id_proyecto'] || '',
                id_documento: +params['id_documento'] || '',
                id_proveedor: +params['id_proveedor'] || '',
                id_sucursal: +params['id_sucursal'] || '',
                id_usuario: +params['id_usuario'] || '',
                forma_pago: params['forma_pago'] || '',
                tipo: params['tipo'] || '',
                dte: params['dte'] || '',
                estado: params['estado'] || '',
                id_area_empresa: +params['id_area_empresa'] || '',
                inicio: params['inicio'] || '',
                fin: params['fin'] || '',
                num_identificacion: params['num_identificacion'] || '',
                orden: params['orden'] || 'id',
                direccion: params['direccion'] || 'desc',
                paginate: +params['paginate'] || 10,
                page: +params['page'] || 1,
            };

            this.filtrarGastos();
        });

        this.apiService.getAll('proveedores/list').subscribe(proveedores => { 
            this.proveedores = proveedores;
        }, error => {this.alertService.error(error); });

        this.apiService.getAll('area-empresa/list').subscribe(areas => { 
            this.areas = areas;
        }, error => {this.alertService.error(error); });
    }

    public loadAll() {
        this.filtros.id_sucursal = '';
        this.filtros.id_proveedor = '';
        this.filtros.id_usuario = '';
        this.filtros.id_proyecto = '';
        this.filtros.forma_pago = '';
        this.filtros.dte = '';
        this.filtros.estado = '';
        this.filtros.tipo = '';
        this.filtros.id_area_empresa = '';
        this.filtros.buscador = '';
        this.filtros.inicio = '';
        this.filtros.fin = '';
        this.filtros.num_identificacion = '';
        this.filtros.orden = 'fecha';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;
        this.filtros.page = 1;

        this.loading = true;
        this.filtrarGastos();
    }

    public filtrarGastos(){
        this.router.navigate([], {
            relativeTo: this.route,
            queryParams: this.filtros,
            queryParamsHandling: 'merge',
        });

        this.loading = true;

        if(!this.filtros.id_proveedor){
            this.filtros.id_proveedor = '';
        }

        if(!this.filtros.id_usuario){
            this.filtros.id_usuario = '';
        }
        
        this.apiService.getAll('gastos', this.filtros).subscribe(gastos => { 
            this.gastos = gastos;
            this.loading = false;
            if(this.modalRef){
                this.modalRef.hide();
            }
        }, error => {this.alertService.error(error); });
    }

    public setOrden(columna: string) {
        if (this.filtros.orden === columna) {
          this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
        } else {
          this.filtros.orden = columna;
          this.filtros.direccion = 'asc';
        }

        this.filtrarGastos();
    }


    public setEstado(gasto:any){
        this.gasto = gasto;
        this.onSubmit();
    }
    
    public onSubmit(){
        this.apiService.store('gasto', this.gasto).subscribe(gasto => { 
            this.gasto = gasto;
            this.alertService.success('Gasto guardado', 'El gasto fue cambiado a ' + this.gasto.estado.toLowerCase() + ' exitosamente.');
        }, error => {this.alertService.error(error); });
    }

    public setRecurrencia(gasto:any){
        this.gasto = gasto;
        this.gasto.recurrente = true;
        
        this.apiService.store('gasto', this.gasto).subscribe(gasto => {
            this.gasto = {};
            this.alertService.success('Gasto guardado', 'El gasto se marco como recurrente exitosamente.');
        },error => {this.alertService.error(error); this.saving = false; });

    }


    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('gasto/', id) .subscribe(data => {
                for (let i = 0; i < this.gastos['data'].length; i++) { 
                    if (this.gastos['data'][i].id == data.id )
                        this.gastos['data'].splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }

    }

    public setPagination(event:any):void{
        this.filtros.page = event.page;
        this.filtrarGastos();
    }


    public descargar(){
        this.downloading = true;
        this.apiService.export('gastos/exportar', this.filtros).subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'gastos.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloading = false;
          }, (error) => { this.alertService.error(error); this.downloading = false; }
        );
    }

    public openFilter(template: TemplateRef<any>) {
        if(!this.sucursales.length){
            this.apiService.getAll('sucursales/list').subscribe(sucursales => { 
                this.sucursales = sucursales;
            }, error => {this.alertService.error(error); });
        }

        if(!this.usuarios.length){
            this.apiService.getAll('usuarios/list').subscribe(usuarios => { 
                this.usuarios = usuarios;
            }, error => {this.alertService.error(error); });
        }

        if(!this.proyectos.length && this.apiService.auth_user().empresa.modulo_proyectos){
            this.apiService.getAll('proyectos/list').subscribe(proyectos => { 
                this.proyectos = proyectos;
            }, error => {this.alertService.error(error); });
        }

        this.modalRef = this.modalService.show(template);
    }

    openDTE(template: TemplateRef<any>, gasto:any){
        this.gasto = gasto;
        this.modalRef = this.modalService.show(template);
        this.alertService.modal = true;
        if(!this.gasto.dte){
            this.emitirDTE();
        }
    }

    imprimirDTEPDF(gasto:any){
        window.open(this.apiService.baseUrl + '/api/reporte/dte/' + gasto.id + '/14/' + '?tipo=gasto&token=' + this.apiService.auth_token(), 'hola', 'width=400');
    }

    imprimirDTEJSON(gasto:any){
        window.open(this.apiService.baseUrl + '/api/reporte/dte-json/' + gasto.id + '/14/' + '?tipo=gasto&token=' + this.apiService.auth_token(), 'hola', 'width=400');
    }

    emitirDTE(){
        this.saving = true;
        this.mhService.emitirDTESujetoExcluidoGasto(this.gasto).then((gasto) => {
            this.gasto = gasto;
            this.alertService.success('DTE emitido.', 'El documento ha sido emitido.');
            this.saving = false;
        }).catch((error) => {
            this.saving = false;
            this.alertService.warning('Hubo un problema', error);
        });
    }


    enviarDTE(){
        this.sending = true;
        this.gasto.tipo = 'gasto';
        this.apiService.store('enviarDTE', this.gasto).subscribe(dte => {
            this.alertService.success('DTE enviado.', 'El DTE fue enviado.');
            this.sending = false;
            setTimeout(()=>{
                this.modalRef?.hide();
            },5000);
        },error => {this.alertService.error(error); this.sending = false; });
    }

    anularDTE(gasto:any){
        this.gasto = gasto;
        if(gasto.dte){
            if (confirm('¿Confirma anular la gasto y el DTE?')) {
                this.gasto = gasto;
                this.saving = true;
                this.apiService.store('generarDTEAnuladoSujetoExcluidoGasto', this.gasto).subscribe(dte => {
                    // this.alertService.success('DTE generado.');
                    this.gasto.dte_invalidacion = dte;
                    this.mhService.firmarDTE(dte).subscribe(dteFirmado => {
                        this.gasto.dte_invalidacion.firmaElectronica = dteFirmado.body;
                        // this.alertService.success('DTE firmado.');
                        
                        this.mhService.anularDTE(this.gasto, dteFirmado.body).subscribe(dte => {
                            if ((dte.estado == 'PROCESADO') && dte.selloRecibido) {
                                this.gasto.dte_invalidacion.sello = dte.selloRecibido;
                                this.gasto.estado = 'Anulada';
                                this.apiService.store('gasto', this.gasto).subscribe(data => {
                                    // this.alertService.success('Compra guardada.');
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
            if (confirm('¿Confirma anular la gasto?')){
                gasto.estado = 'Anulada';
                this.onSubmit();
            }
        }
    }

    generarPartidaContable(gasto:any){
        this.apiService.store('contabilidad/partida/gasto', gasto).subscribe(gasto => {
            this.alertService.success('Partida generada.', 'La partida contable fue generada exitosamente.');
        },error => {this.alertService.error(error);});
    }


}
