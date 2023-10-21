import { Component, OnInit, TemplateRef } from '@angular/core';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';

@Component({
  selector: 'app-fletes',
  templateUrl: './fletes.component.html',
})
export class FletesComponent implements OnInit {

    public fletes:any;
    public buscador:any = '';
    public loading:boolean = false;

    public filtro:any = {};
    public filtrado:boolean = false;
    public motoristas:any = [];
    public clientes:any = [];
    public sucursales:any = [];
    
    modalRef?: BsModalRef;

    constructor( public apiService:ApiService, private alertService:AlertService, private modalService: BsModalService ){}

    ngOnInit() {
        this.loadAll();
    }

    public loadAll() {
        this.loading = true;
        this.apiService.getAll('fletes').subscribe(fletes => { 
            this.fletes = fletes;
            this.loading = false; this.filtrado = false;
        }, error => {this.alertService.error(error); this.loading = false;});

    }

    public search(){
        if(this.buscador && this.buscador.length > 2) {
            this.loading = true;
            this.apiService.read('fletes/buscar/', this.buscador).subscribe(fletes => { 
                this.fletes = fletes;
                this.loading = false; this.filtrado = true;
            }, error => {this.alertService.error(error); this.loading = false;});
        }else{
            this.loadAll();
        }
    }

    public delete(flete:any){
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('flete/', flete.id) .subscribe(data => {
                for (let i = 0; i < this.fletes.data.length; i++) { 
                    if (this.fletes.data[i].id == data.id )
                        this.fletes.data.splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }
    }

    public setEstado(flete:any, estado:any):void{
        this.loading = true;
        flete.estado = estado;
        this.apiService.store('flete', flete).subscribe(data => {
            this.loading = false;
            flete = data;
            this.alertService.success('Guardado');
        },error => {this.alertService.error(error); this.loading = false; });
    }

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.fletes.path + '?page='+ event.page).subscribe(fletes => { 
            this.fletes = fletes;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }


    // Filtros
    openFilter(template: TemplateRef<any>) {     

        if(!this.filtrado) {
            this.filtro.inicio = this.apiService.date();
            this.filtro.fin = this.apiService.date();
            this.filtro.sucursal_id = '';
            this.filtro.motorista_id = '';
            this.filtro.cliente_id = '';
            this.filtro.tipo = '';
            this.filtro.estado = '';
            this.filtro.metodo_pago = '';
            this.filtro.tipo_documento = '';
        }
        if(!this.motoristas.data){
            this.apiService.getAll('motoristas/list').subscribe(motoristas => { 
                this.motoristas = motoristas;
            }, error => {this.alertService.error(error); });
        }
        if(!this.clientes.data){
            this.apiService.getAll('clientes/list').subscribe(clientes => { 
                this.clientes = clientes;
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
        this.apiService.store('fletes/filtrar', this.filtro).subscribe(fletes => { 
            this.fletes = fletes;
            this.loading = false; this.filtrado = true;
            this.modalRef!.hide();
        }, error => {this.alertService.error(error); this.loading = false;});

    }

    public imprimirOrdenDeCarga(flete:any){
        window.open(this.apiService.baseUrl + '/api/flete/orden-de-carga/' + flete.id + '?token=' + this.apiService.auth_token(), 'hola', 'width=800');
    }

    public imprimirCartaDePorte(flete:any){
        window.open(this.apiService.baseUrl + '/api/flete/carta-de-porte/' + flete.id + '?token=' + this.apiService.auth_token(), 'hola', 'width=800');
    }

    public imprimirManifiestoDeCarga(flete:any){
        window.open(this.apiService.baseUrl + '/api/flete/manifiesto-de-carga/' + flete.id + '?token=' + this.apiService.auth_token(), 'hola', 'width=800');
    }

}
