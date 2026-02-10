import { Component, OnInit, TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { MHService } from '@services/MH.service';

declare var $:any;

@Component({
  selector: 'app-compras',
  templateUrl: './compras.component.html'
})

export class ComprasComponent implements OnInit {

    public compras:any = [];
    public compra:any = {};
    public formaPagos:any = [];
    public documentos:any = [];
    public proveedores:any = [];
    public usuarios:any = [];
    public proyectos:any = [];
    public sucursales:any = [];
    public buscador:any = '';
    public loading:boolean = false;
    public saving:boolean = false;
    public sending:boolean = false;
    public downloadingDetalles:boolean = false;
    public downloadingCompras:boolean = false;
    public modalRefAcumulado!: BsModalRef;
    public modalRefRentabilidad!: BsModalRef;
    public filtrosRentabilidad:any = {
        inicio: '',
        fin: '',
        sucursales: [],
        categorias: [],
        marcas: [],
    };
    public numeros_ids:any = [];
    public downloadingRentabilidad:boolean = false;
    

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
                dte: params['dte'] || '',
                estado: params['estado'] || '',
                inicio: params['inicio'] || '',
                fin: params['fin'] || '',
                num_identificacion: params['num_identificacion'] || '',
                orden: params['orden'] || 'id',
                direccion: params['direccion'] || 'desc',
                paginate: +params['paginate'] || 10,
                page: +params['page'] || 1,
            };

            this.filtrarCompras();
        });

        this.getNumsIds();
        this.apiService.getAll('proveedores/list').subscribe(proveedores => { 
            this.proveedores = proveedores;
        }, error => {this.alertService.error(error); });
    }

    public loadAll() {
        this.filtros.id_sucursal = '';
        this.filtros.id_proveedor = '';
        this.filtros.id_usuario = '';
        this.filtros.id_usuario = '';
        this.filtros.id_canal = '';
        this.filtros.id_documento = '';
        this.filtros.id_proyecto = '';
        this.filtros.forma_pago = '';
        this.filtros.dte = '';
        this.filtros.estado = '';
        this.filtros.buscador = '';
        this.filtros.inicio = '';
        this.filtros.fin = '';
        this.filtros.orden = 'fecha';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;
        this.filtros.page = 1;
        this.filtros.num_identificacion = '';

        this.filtrarCompras();
    }

    public filtrarCompras(){
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

        this.apiService.getAll('compras', this.filtros).subscribe(compras => { 
            this.compras = compras;
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

        this.filtrarCompras();
    }

    public setEstado(compra:any, estado:any){
        if(estado == 'Pagada'){
            if(confirm('¿Confirma el pago de la compra?')){
                this.compra = compra;
                this.compra.estado = estado;
                this.onSubmit();
            }
        }
        if(estado == 'Anulada'){
            if(confirm('¿Confirma la anulación de la compra?')){
                this.compra = compra;
                this.compra.estado = estado;
                this.onSubmit();
            }
        }

    }
    
    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('compra/', id) .subscribe(data => {
                for (let i = 0; i < this.compras['data'].length; i++) { 
                    if (this.compras['data'][i].id == data.id )
                        this.compras['data'].splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }

    }

    public openModalEdit(template: TemplateRef<any>, compra:any) {
        this.compra = compra;

        if(!this.proyectos.length && this.apiService.auth_user().empresa.modulo_proyectos){
            this.apiService.getAll('proyectos/list').subscribe(proyectos => { 
                this.proyectos = proyectos;
            }, error => {this.alertService.error(error); });
        }

        this.apiService.getAll('documentos/list').subscribe(documentos => {
            this.documentos = documentos;
        }, error => {this.alertService.error(error);});

        if(!this.formaPagos.length){
            this.apiService.getAll('formas-de-pago/list').subscribe(formaPagos => { 
                this.formaPagos = formaPagos;
            }, error => {this.alertService.error(error); });
        }

        if(!this.usuarios.length){
            this.apiService.getAll('usuarios/list').subscribe(usuarios => { 
                this.usuarios = usuarios;
            }, error => {this.alertService.error(error); });
        }

        this.modalRef = this.modalService.show(template);
    }


    public filtrar(filtro:any, txt:any){
        this.loading = true;
        this.apiService.read('compras/filtrar/' + filtro + '/', txt).subscribe(compras => { 
            this.compras = compras;
            this.loading = false;
        }, error => {this.alertService.error(error); });

    }

    public onSubmit() {
        this.saving = true;            
        this.apiService.store('compra', this.compra).subscribe(compra => {
            this.compra = {};
            this.saving = false;
            if(this.modalRef){
                this.modalRef.hide();
            }
            this.alertService.success('Venta guardado', 'La compra fue guardada exitosamente.');
        },error => {this.alertService.error(error); this.saving = false; });

        this.filtrarCompras();

    }

    public setRecurrencia(compra:any){
        this.compra = compra;
        this.compra.recurrente = true;
        
        this.apiService.store('compra', this.compra).subscribe(compra => {
            this.compra = {};
            this.alertService.success('Compra guardada', 'La compra se marco como recurrente exitosamente.');
        },error => {this.alertService.error(error); this.saving = false; });

    }

    public setPagination(event:any):void{
        this.loading = true;
        this.filtros.page = event.page;
        this.filtrarCompras();
    }

    public openDescargar(template: TemplateRef<any>) {
        this.modalRef = this.modalService.show(template);
    }

    public descargarCompras(){
        this.downloadingCompras = true; this.saving = true;
        this.apiService.export('compras/exportar', this.filtros).subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'compras.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloadingCompras = false; this.saving = false;
          }, (error) => {this.alertService.error(error); this.downloadingCompras = false; this.saving = false;}
        );
    }

    public descargarDetalles(){
        this.downloadingDetalles = true; this.saving = true;
        this.apiService.export('compras-detalles/exportar', this.filtros).subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'compras-detalles.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloadingDetalles = false; this.saving = false;
          }, (error) => {this.alertService.error(error); this.downloadingDetalles = false; this.saving = false; }
        );
    }

    public openAbono(template: TemplateRef<any>, compra:any){
        this.compra = compra;
        this.modalRef = this.modalService.show(template);
    }

    public openFilter(template: TemplateRef<any>) {

        this.apiService.getAll('documentos/list').subscribe(documentos => {
            this.documentos = documentos;
        }, error => {this.alertService.error(error);});

        if(!this.formaPagos.length){
            this.apiService.getAll('formas-de-pago/list').subscribe(formaPagos => { 
                this.formaPagos = formaPagos;
            }, error => {this.alertService.error(error); });
        }

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

    openDTE(template: TemplateRef<any>, compra:any){
        this.compra = compra;
        this.modalRef = this.modalService.show(template);
        this.alertService.modal = true;
        if(!this.compra.dte){
            this.emitirDTE();
        }
    }

    imprimirDTEPDF(compra:any){
        window.open(this.apiService.baseUrl + '/api/reporte/dte/' + compra.id + '/14/' + '?tipo=compra&token=' + this.apiService.auth_token(), 'hola', 'width=400');
    }

    imprimirDTEJSON(compra:any){
        window.open(this.apiService.baseUrl + '/api/reporte/dte-json/' + compra.id + '/14/' + '?tipo=compra&token=' + this.apiService.auth_token(), 'hola', 'width=400');
    }

    emitirDTE(){
        this.saving = true;
        this.mhService.emitirDTESujetoExcluidoCompra(this.compra).then((compra) => {
            this.compra = compra;
            this.alertService.success('DTE emitido.', 'El documento ha sido emitido.');
            this.saving = false;
            this.enviarDTE();
        }).catch((error) => {
            this.saving = false;
            this.alertService.warning('Hubo un problema', error);
        });
    }


    enviarDTE(){
        this.sending = true;
        this.compra.tipo = 'compra';
        this.apiService.store('enviarDTE', this.compra).subscribe(dte => {
            this.alertService.success('DTE enviado.', 'El DTE fue enviado.');
            this.sending = false;
            setTimeout(()=>{
                this.modalRef?.hide();
            },5000);
        },error => {this.alertService.error(error); this.sending = false; });
    }

    anularDTE(compra:any){
        this.compra = compra;
        if(compra.dte){
            if (confirm('¿Confirma anular la compra y el DTE?')) {
                this.compra = compra;
                this.saving = true;
                this.apiService.store('generarDTEAnuladoSujetoExcluidoCompra', this.compra).subscribe(dte => {
                    // this.alertService.success('DTE generado.');
                    this.compra.dte_invalidacion = dte;
                    this.mhService.firmarDTE(dte).subscribe(dteFirmado => {
                        this.compra.dte_invalidacion.firmaElectronica = dteFirmado.body;
                        // this.alertService.success('DTE firmado.');
                        
                        this.mhService.anularDTE(this.compra, dteFirmado.body).subscribe(dte => {
                            if ((dte.estado == 'PROCESADO') && dte.selloRecibido) {
                                this.compra.dte_invalidacion.sello = dte.selloRecibido;
                                this.compra.estado = 'Anulada';
                                this.apiService.store('compra', this.compra).subscribe(data => {
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
            if (confirm('¿Confirma anular la compra?')){
                compra.estado = 'Anulada';
                this.onSubmit();
            }
        }
    }

   

    public abrirModalFiltrosRentabilidad(template: TemplateRef<any>) {
        this.apiService.getAll('sucursales/list').subscribe(sucursales => { 
            this.sucursales = sucursales;
        }, error => {this.alertService.error(error); });

        this.modalRefRentabilidad = this.modalService.show(template, {
          class: 'modal-lg',
        });
      }

      
  public descargarReporteRentabilidad() {
    this.downloadingRentabilidad = true;
    this.saving = true;

    this.apiService.exportAcumulado('compras-rentabilidad/exportar', this.filtrosRentabilidad).subscribe(
      (data: Blob) => {
        const blob = new Blob([data], {
          type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'compras-rentabilidad.xlsx';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);

        // Cerrar ambos modales
        if (this.modalRefRentabilidad) {
          this.modalRefRentabilidad.hide();
        }
        if (this.modalRefRentabilidad) {
          this.modalRefRentabilidad.hide();
        }

        this.downloadingRentabilidad = false;
        this.saving = false;

     
        this.filtrosRentabilidad = {
          inicio: '',
          fin: '',
          sucursales: [],
          categorias: [],
          marcas: [],
        };
      },
      (error) => {
        this.alertService.error(error);
        this.downloadingRentabilidad = false;
        this.saving = false;
      }
    );
  }

  public isColumnEnabled(columnName: string): boolean {
    return this.apiService.auth_user().empresa?.custom_empresa?.columnas?.[columnName] || false;
  }

  getNumsIds(){
    this.apiService.getAll('compras/nums-ids').subscribe(numsIds => { 
        this.numeros_ids = numsIds;
    }, error => {this.alertService.error(error); });
  } 

  public imprimir(compra:any){
    window.open(this.apiService.baseUrl + '/api/compra/impresion/' + compra.id + '?token=' + this.apiService.auth_token());
  }

}
