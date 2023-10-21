import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';

@Component({
  selector: 'app-requisiciones-compras',
  templateUrl: './requisiciones-compras.component.html'
})

export class RequisicionesComprasComponent implements OnInit {

    public buscador:any = '';
    public loading:boolean = false;

    public filtro:any = {};

    public productos:any = [];
    public categorias:any = [];
    public proveedores:any = [];
    modalRef!: BsModalRef;

    constructor(public apiService: ApiService, private alertService: AlertService,
                private modalService: BsModalService
    ){}

    ngOnInit() {
        this.loadAll();
    }

    public loadAll() {
        this.loading = true;
        this.apiService.getAll('traslados/bodega').subscribe(productos => {
           this.productos = productos;
           this.loading = false;
        }, error => {this.alertService.error(error._body);this.loading = false;});
    }

    eliminarProducto(producto:any){
        for (var i = 0; i < this.productos.length; ++i) {
            if (this.productos[i].producto_id === producto.producto_id ){
                this.productos.splice(i, 1);
            }
        }
    }

    openFilter(template: TemplateRef<any>) {
        this.filtro.proveedor_id = '';
        this.filtro.categoria_id = '';

        if(!this.categorias.length){
            this.apiService.getAll('categorias').subscribe(categorias => { 
                this.categorias = categorias;
            }, error => {this.alertService.error(error); });
        }

        if(!this.proveedores.length){
            this.apiService.getAll('proveedores/list').subscribe(proveedores => { 
                this.proveedores = proveedores;
            }, error => {this.alertService.error(error); });
        }

        this.modalRef = this.modalService.show(template);
    }

    onFiltrar(){
        this.loading = true;
        this.apiService.store('requisiciones/bodega/filtrar', this.filtro).subscribe(productos => { 
            this.productos = productos;
            this.loading = false;
            this.modalRef.hide();
        }, error => {this.alertService.error(error); this.loading = false;});

    }

}
