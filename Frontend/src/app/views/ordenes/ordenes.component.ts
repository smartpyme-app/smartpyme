import { Component, OnInit, TemplateRef } from '@angular/core';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { ApiService } from '../../services/api.service';
import { AlertService } from '../../services/alert.service';

@Component({
  selector: 'app-ordenes',
  templateUrl: './ordenes.component.html',
})
export class OrdenesComponent implements OnInit {

    public ordenes:any;
    public buscador:any = '';
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
        if (this.apiService.auth_user().tipo == 'Administrador') {
            this.apiService.getAll('ordenes').subscribe(ordenes => { 
                this.ordenes = ordenes;
                this.loading = false; this.filtrado = false;
            }, error => {this.alertService.error(error); this.loading = false;});
        }else{
            this.apiService.getAll('dash/vendedor/ordenes').subscribe(ordenes => { 
                this.ordenes = ordenes;
                this.loading = false; this.filtrado = false;
            }, error => {this.alertService.error(error); this.loading = false;});
        }
    }

    public search(){
        if(this.buscador && this.buscador.length > 2) {
            this.loading = true;
            this.apiService.read('dash/vendedor/ordenes/buscar/', this.buscador).subscribe(ordenes => { 
                this.ordenes = ordenes;
                this.loading = false; this.filtrado = true;
            }, error => {this.alertService.error(error); this.loading = false;});
        }else{
            this.loadAll();
        }
    }

    public delete(comanda:any){
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('comanda/', comanda.id) .subscribe(data => {
                for (let i = 0; i < this.ordenes.data.length; i++) { 
                    if (this.ordenes.data[i].id == data.id )
                        this.ordenes.data.splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }
    }

    public setEstado(comanda:any, estado:any):void{
        this.loading = true;
        comanda.estado = estado;
        this.apiService.store('comanda', comanda).subscribe(data => {
            this.loading = false;
            comanda = data;
            this.alertService.success('Guardado');
        },error => {this.alertService.error(error); this.loading = false; });
    }

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.ordenes.path + '?page='+ event.page).subscribe(ordenes => { 
            this.ordenes = ordenes;
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
            this.filtro.tipo_servicio = '';
            this.filtro.estado = '';
            this.filtro.metodo_pago = '';
            this.filtro.tipo_documento = '';
        }
        if(!this.usuarios.data){
            this.apiService.getAll('usuarios/filtrar/tipo/Mesero').subscribe(usuarios => { 
                this.usuarios = usuarios.data;
            }, error => {this.alertService.error(error); });
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
        this.apiService.store('ordenes/filtrar', this.filtro).subscribe(ordenes => { 
            this.ordenes = ordenes;
            this.loading = false; this.filtrado = true;
            this.modalRef!.hide();
        }, error => {this.alertService.error(error); this.loading = false;});

    }


}
