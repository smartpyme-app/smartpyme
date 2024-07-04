import { Component, OnInit, Input, TemplateRef } from '@angular/core';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-admin-facturaciones',
  templateUrl: './admin-facturaciones.component.html'
})

export class AdminFacturacionesComponent implements OnInit{

    public transacciones:any= [];
    public transaccion:any = {};
    public usuario:any = {};
    public sucursales:any = [];
    public filtros:any = {};
    public loading:boolean = false;
    public usuarios:any = [];
    public formaPagos:any = [];
    // para el boton del modal
    public saving:boolean = false;

    modalRef!: BsModalRef;

    constructor(public apiService: ApiService, private alertService: AlertService,private modalService: BsModalService){};

    ngOnInit() {
        this.usuario = this.apiService.auth_user();
        this.loadAll();

        this.apiService.getAll('sucursales/list').subscribe(sucursales => { 
            this.sucursales = sucursales;
        }, error => {this.alertService.error(error); });
    }

    public loadAll() {
        this.filtros.id_sucursal = '';
        this.filtros.id_cliente = '';
        this.filtros.id_usuario = '';
        this.filtros.id_vendedor = '';
        this.filtros.id_canal = '';
        this.filtros.id_documento = '';
        this.filtros.forma_pago = '';
        this.filtros.estado = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'fecha';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;

        this.filtrarTransacciones();
    }

    public filtrarTransacciones(){

        this.loading = true;
        this.apiService.getAll('transacciones', this.filtros).subscribe(transacciones => { 
            this.transacciones = transacciones;
            this.loading = false;
            if(this.modalRef){
                this.modalRef.hide();
            }
        }, error => {this.alertService.error(error); this.loading = false;});

    }

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.transacciones.path + '?page='+ event.page, this.filtros).subscribe(transacciones => { 
            this.transacciones = transacciones;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }
    
    public imprimir(transaccion:any){
        window.open(this.apiService.baseUrl + '/api/reporte/facturacion/' + transaccion.id + '?token=' + this.apiService.auth_token());
    }

    public openModalEdit(template: TemplateRef<any>, transaccion:any) {
        this.transaccion = transaccion;
        
        // if(!this.documentos.length){
        //     this.apiService.getAll('documentos/list').subscribe(documentos => {
        //         this.documentos = documentos;
        //         this.documentos = this.documentos.filter((x:any) => x.id_sucursal == this.venta.id_sucursal);
        //     }, error => {this.alertService.error(error);});
        // }

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

        // if(!this.canales.length){
        //     this.apiService.getAll('canales/list').subscribe(canales => { 
        //         this.canales = canales;
        //     }, error => {this.alertService.error(error); });
        // }

        this.modalRef = this.modalService.show(template);
    }
    public onSubmit() {
        this.saving = true;            
        this.apiService.store('transaccion', this.transaccion).subscribe(venta => {
            this.transaccion = {};
            this.saving = false;
            if(this.modalRef){
                this.modalRef.hide();
            }
            this.alertService.success('Venta guardada', 'La venta fue guardada exitosamente.');
        },error => {this.alertService.error(error); this.saving = false; });

    }



}

