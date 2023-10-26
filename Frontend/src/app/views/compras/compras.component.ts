import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '../../services/alert.service';
import { ApiService } from '../../services/api.service';

declare var $:any;

@Component({
  selector: 'app-compras',
  templateUrl: './compras.component.html'
})

export class ComprasComponent implements OnInit {

    public compras:any = [];
    public buscador:any = '';
    public loading:boolean = false;

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
        this.loading = true;
        this.filtro.estado = '';
        this.filtro.id_proveedor = '';
        this.filtro.inicio = this.apiService.date();
        this.filtro.fin = this.apiService.date();

        this.apiService.getAll('compras').subscribe(compras => { 
            this.compras = compras;
            this.loading = false;this.filtrado = false;
        }, error => {this.alertService.error(error); });
    }

    public search(){
        if(this.buscador && this.buscador.length > 2) {
            this.loading = true;
            this.apiService.read('compras/buscar/', this.buscador).subscribe(compras => { 
                this.compras = compras;
                this.loading = false;this.filtrado = true;
            }, error => {this.alertService.error(error); this.loading = false;this.filtrado = false; });
        }
    }

    public setEstado(compra:any){
        this.apiService.store('compra', compra).subscribe(compra => { 
            this.alertService.success('Actualizado');
        }, error => {this.alertService.error(error); });
    }
    
    public descargar(){
        window.open(this.apiService.baseUrl + '/api/productos/export' + '?token=' + this.apiService.auth_token(), 'Impresión', 'width=400');
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


    public filtrar(filtro:any, txt:any){
        this.loading = true;
        this.apiService.read('compras/filtrar/' + filtro + '/', txt).subscribe(compras => { 
            this.compras = compras;
            this.loading = false;
        }, error => {this.alertService.error(error); });

    }

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.compras.path + '?page='+ event.page).subscribe(compras => { 
            this.compras = compras;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    onFiltrar(){
        this.loading = true;
        this.apiService.store('compras/filtrar', this.filtro).subscribe(compras => { 
            this.compras = compras;
            this.loading = false; this.filtrado = true;
        }, error => {this.alertService.error(error); this.loading = false;});

    }

}
