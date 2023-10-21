import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';

@Component({
  selector: 'app-vendedor-productos',
  templateUrl: './vendedor-productos.component.html',
})
export class VendedorProductosComponent implements OnInit {

    public productos:any = [];
    public buscador:any = '';
    public loading:boolean = false;
    
    public filtro:any = {};
    public producto:any = {};
    public sucursales:any = [];
    public filtrado:boolean = false;
    public categorias:any = [];
    modalRef!: BsModalRef;

    constructor(public apiService: ApiService, private alertService: AlertService,
                private modalService: BsModalService
    ){}

    ngOnInit() {
        this.loadAll();
    }

    public loadAll() {
        this.loading = true;
        this.apiService.getAll('dash/vendedor/productos').subscribe(productos => { 
            this.productos = productos;
            this.apiService.getAll('sucursales').subscribe(sucursales => { 
                this.sucursales = sucursales;
                this.checkSucursales();
            }, error => {this.alertService.error(error); this.loading = false;});
            this.loading = false; this.filtrado = false;
        }, error => {this.alertService.error(error); this.loading = false;});

    }

    public search(){
        if(this.buscador && this.buscador.length > 2) {
            this.loading = true;
            this.apiService.read('dash/vendedor/productos/buscar/', this.buscador).subscribe(productos => { 
                this.productos = productos;
                this.loading = false; this.filtrado = true;
                this.checkSucursales();
            }, error => {this.alertService.error(error); this.loading = false;});
        }else{
            this.loadAll();
        }
    }

    // sucursales

        public checkSucursales(){

            for(let i = 0; i < this.productos.data.length; i++){            
                var producto = this.productos.data[i];
                producto.lista_sucursales = JSON.parse(JSON.stringify(this.sucursales));

                for(let j = 0; j < producto.sucursales.length; j++){
                    var producto_sucursal = producto.sucursales[j];
                    
                    for(let k = 0; k < producto.lista_sucursales.length; k++){
                        var lista_sucursal = producto.lista_sucursales[k];

                        if (lista_sucursal.id == producto_sucursal.sucursal_id) {
                            lista_sucursal.agregado = true;
                        }

                    }

                }

            }

        }



    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.productos.path + '?page='+ event.page).subscribe(productos => { 
            this.productos = productos;
            this.checkSucursales();
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    // Filtros
    openModal(template: TemplateRef<any>, producto:any) {
        this.producto = producto;
        this.modalRef = this.modalService.show(template);
    }

    openFilter(template: TemplateRef<any>) {
        if(!this.filtrado) {
            this.filtro.sucursal_id = '';
            this.filtro.categoria_id = '';
        }


        if(!this.categorias.lenght){
            this.apiService.getAll('categorias').subscribe(categorias => { 
                this.categorias = categorias;
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
        this.apiService.store('productos/filtrar', this.filtro).subscribe(productos => { 
            this.productos = productos;
            this.checkSucursales();
            this.loading = false; this.filtrado = true;
            this.modalRef.hide();
        }, error => {this.alertService.error(error); this.loading = false;});

    }

}
