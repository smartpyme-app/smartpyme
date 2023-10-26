import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-productos-consignas',
  templateUrl: './productos-consignas.component.html',
})
export class ProductosConsignasComponent implements OnInit {

    public productos:any = [];
    public buscador:any = '';
    public loading:boolean = false;
    
    public filtro:any = {};
    public producto:any = {};
    public sucursales:any = [];
    public categorias:any = [];

    modalRef!: BsModalRef;

    constructor(public apiService: ApiService, private alertService: AlertService,
                private modalService: BsModalService
    ){}

    ngOnInit() {
        this.loadAll();

        this.apiService.getAll('categorias').subscribe(categorias => {
            this.categorias = categorias;
        }, error => {this.alertService.error(error);});

        this.apiService.getAll('sucursales').subscribe(sucursales => { 
            this.sucursales = sucursales;
        }, error => {this.alertService.error(error); });
    }

    public loadAll() {
        this.filtro.categoria = '';
        this.loading = true;
        this.apiService.getAll('productos/consignas').subscribe(productos => { 
            this.productos = productos;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});

    }

    public search(){
        if(this.buscador && this.buscador.length > 2) {
            this.loading = true;
            this.apiService.read('productos/buscar/', this.buscador).subscribe(productos => { 
                this.productos = productos;
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false;});
        }else{
            this.loadAll();
        }
    }

    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('producto/', id) .subscribe(data => {
                for (let i = 0; i < this.productos['data'].length; i++) { 
                    if (this.productos['data'][i].id == data.id )
                        this.productos['data'].splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }

    }

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.productos.path + '?page='+ event.page).subscribe(productos => { 
            this.productos = productos;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public onFiltrar(){
        this.loading = true;
        this.apiService.store('productos/filtrar', this.filtro).subscribe(productos => { 
            this.productos = productos;
            this.loading = false;
            this.modalRef.hide();
        }, error => {this.alertService.error(error); this.loading = false;});

    }

    public openModal(template: TemplateRef<any>, producto:any) {
        this.producto = producto;
        this.modalRef = this.modalService.show(template, {class: 'modal-lg', backdrop: 'static'});
    }

    public onSubmit() {
        this.loading = true;
        this.apiService.store('producto', this.producto).subscribe(producto=> {
            this.producto = {};
            this.alertService.success("Datos guardados");
            this.loading = false;
            this.modalRef.hide();
        },error => {this.alertService.error(error); this.loading = false; });
    }

    public descargar(){
        window.open(this.apiService.baseUrl + '/api/productos/export' + '?token=' + this.apiService.auth_token(), 'Impresión', 'width=400');
    }

}
