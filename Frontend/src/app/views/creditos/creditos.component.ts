import { Component, OnInit, TemplateRef } from '@angular/core';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { ApiService } from '../../services/api.service';
import { AlertService } from '../../services/alert.service';

@Component({
  selector: 'app-creditos',
  templateUrl: './creditos.component.html',
})
export class CreditosComponent implements OnInit {

    public creditos:any;
    public credito:any = {};
    public loading:boolean = false;

    public filtro:any = {};
    public filtrado:boolean = false;
    public usuarios:any = [];
    public sucursales:any = [];
    
    modalRef?: BsModalRef;

    constructor( public apiService:ApiService, private alertService:AlertService, private modalService: BsModalService ){}

    ngOnInit() {
        this.loadAll();
    }

    public loadAll() {
        this.loading = true;
        this.apiService.getAll('creditos').subscribe(creditos => { 
            this.creditos = creditos;
            this.loading = false; this.filtrado = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public search(buscador:any){
        if(buscador && buscador.length > 2) {
            this.loading = true;
            this.apiService.read('creditos/buscar/', buscador).subscribe(creditos => { 
                this.creditos = creditos;
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false;});
        }else{
            this.loadAll();
        }
    }

    public setCliente(cliente:any){
        this.credito.cliente_id = cliente.id;
        console.log(this.credito);
    }

    openModal(template: TemplateRef<any>, credito:any) {
        this.credito = credito;
        if (!this.credito.id) {
            this.credito.fecha = this.apiService.date();
            this.credito.periodo_de_gracia = 0;
            this.credito.tipo_cuota = 'Sobre Saldos';
            this.credito.cliente = {};
        }
        this.modalRef = this.modalService.show(template);
    }

    public onSubmit(){
        this.loading = true;
        this.credito.usuario_id = this.apiService.auth_user().id;
        this.credito.empresa_id = this.apiService.auth_user().empresa_id;
        this.apiService.store('credito', this.credito).subscribe(credito => { 
            this.loadAll();
            this.modalRef!.hide();
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }


    public delete(orden:any){
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('orden/', orden.id) .subscribe(data => {
                for (let i = 0; i < this.creditos.data.length; i++) { 
                    if (this.creditos.data[i].id == data.id )
                        this.creditos.data.splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }
    }

    public setEstado(orden:any, estado:any):void{
        this.loading = true;
        orden.estado = estado;
        this.apiService.store('orden', orden).subscribe(data => {
            this.loading = false;
            orden = data;
            this.alertService.success('Guardado');
        },error => {this.alertService.error(error); this.loading = false; });
    }

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.creditos.path + '?page='+ event.page).subscribe(creditos => { 
            this.creditos = creditos;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }


    // Filtros
    openFilter(template: TemplateRef<any>) {     

        if(!this.filtrado) {
            this.filtro.inicio = this.apiService.date();
            this.filtro.fin = this.apiService.date();
            this.filtro.sucursal_id = '';
            this.filtro.usuario_id = '';
            this.filtro.estado = '';
            this.filtro.metodo_pago = '';
            this.filtro.tipo_documento = '';
        }
        if(!this.sucursales.data){
            this.apiService.getAll('sucursales').subscribe(sucursales => { 
                this.sucursales = sucursales;
            }, error => {this.alertService.error(error); });
        }
        this.modalRef = this.modalService.show(template);
    }

    onFiltrar(){
        this.loading = true;
        this.apiService.store('creditos/filtrar', this.filtro).subscribe(creditos => { 
            this.creditos = creditos;
            this.loading = false; this.filtrado = true;
            this.modalRef!.hide();
        }, error => {this.alertService.error(error); this.loading = false;});

    }


}
