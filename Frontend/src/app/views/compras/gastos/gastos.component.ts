import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';


@Component({
  selector: 'app-gastos',
  templateUrl: './gastos.component.html'
})

export class GastosComponent implements OnInit {

    public gastos:any = [];
    public buscador:any = '';
    public loading:boolean = false;

    public clientes:any = [];
    public usuarios:any = [];
    public sucursales:any = [];
    public proveedores:any = [];
    public filtro:any = {};
    public filtrado:boolean = false;

    modalRef!: BsModalRef;

    constructor(public apiService: ApiService, private alertService: AlertService,
                private modalService: BsModalService
    ){}

    ngOnInit() {
        this.loadAll();

        this.apiService.getAll('proveedores/list').subscribe(proveedores => { 
            this.proveedores = proveedores;
        }, error => {this.alertService.error(error); });
    }

    public loadAll() {
        this.filtro.estado = '';
        this.filtro.id_proveedor = '';
        this.filtro.inicio = this.apiService.date();
        this.filtro.fin = this.apiService.date();
        
        this.loading = true;
        this.apiService.getAll('gastos').subscribe(gastos => { 
            this.gastos = gastos;
            this.loading = false;this.filtrado = false;
        }, error => {this.alertService.error(error); });
    }

    public search(){
        if(this.buscador && this.buscador.length > 1) {
            this.loading = true;
            this.apiService.read('gastos/buscar/', this.buscador).subscribe(gastos => { 
                this.gastos = gastos;
                this.loading = false;this.filtrado = true;
            }, error => {this.alertService.error(error); this.loading = false;this.filtrado = false; });
        }
    }

    public setEstado(gasto:any){
        this.apiService.store('gasto', gasto).subscribe(gasto => { 
            this.alertService.success('Actualizado');
        }, error => {this.alertService.error(error); });
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
        this.loading = true;
        this.apiService.paginate(this.gastos.path + '?page='+ event.page).subscribe(gastos => { 
            this.gastos = gastos;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }


    onFiltrar(){
        this.loading = true;

        this.apiService.store('gastos/filtrar', this.filtro).subscribe(gastos => { 
            this.gastos = gastos;
            this.loading = false; this.filtrado = true;
            this.modalRef.hide();
        }, error => {this.alertService.error(error); this.loading = false;});

    }

    public descargar(){
        window.open(this.apiService.baseUrl + '/api/productos/export' + '?token=' + this.apiService.auth_token(), 'Impresión', 'width=400');
    }


}
