import { Component, OnInit, TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal/bs-modal-ref.service';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-bodega',
  templateUrl: './bodega.component.html',
})
export class BodegaComponent implements OnInit {

    public productos:any = [];
    public producto:any = {};
    public ajuste:any = {};
    public id:any;

    public filtro:any = {};
    public filtrado:boolean = false;
    public categorias:any =[];
    public buscador:any = '';
    public loading:boolean = false;

    modalRef!: BsModalRef;

    constructor( 
        public apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService, private route: ActivatedRoute,
        private router: Router,
    ) {
        this.router.routeReuseStrategy.shouldReuseRoute = function() {return false; };
    }

    ngOnInit() {
        this.id = +this.route.snapshot.paramMap.get('id')!;
        this.loadAll();
    }

    public loadAll() {
        this.loading = true;
        this.apiService.getAll('bodega/productos/' + this.id).subscribe(productos => { 
            this.productos = productos;
            this.loading = false;
        }, error => {this.alertService.error(error); });
    }

    openModal(template: TemplateRef<any>, producto:any) {
        this.producto = producto;
        this.modalRef = this.modalService.show(template, {class: 'modal-md'});
    }

    public onSubmit() {

        this.loading = true;
        this.apiService.store('inventario', this.producto).subscribe(producto => {
            this.loading = false;
            this.alertService.success("Bodega guardada", 'La bodega fue guardada exitosamente.');
            this.modalRef.hide();
        }, error => {this.alertService.error(error._body); this.loading = false; });


    }

    public search(){
        if(this.buscador && this.buscador.length > 2) {
            this.apiService.getAll('bodega/productos/buscar/' + this.id + '/' + this.buscador).subscribe(productos => { 
                this.productos = productos;
            }, error => {this.alertService.error(error); });
        }
    }

    imprimir(){
        window.open(this.apiService.baseUrl + '/api/reporte/bodegas/' + this.id + '/' + this.filtro.subcategorias_id + '?token=' + this.apiService.auth_token(), 'hola', 'width=400');
    }


    // Ajustes

        public openModalAjuste(template: TemplateRef<any>, bodega:any) {
            this.producto = bodega;
            this.ajuste.producto_id = bodega.producto_id;
            this.ajuste.bodega_id = bodega.bodega_id;
            this.ajuste.stock_inicial = bodega.stock;
            this.modalRef = this.modalService.show(template, {class: 'modal-sm'});
        }
        
        public onSubmitAjuste() {

            this.loading = true;
            this.ajuste.usuario_id = this.apiService.auth_user().id;
            this.apiService.store('ajuste', this.ajuste).subscribe(ajuste => {
                this.ajuste = {};
                this.producto.stock = ajuste.stock_final;
                this.loading = false;
                this.alertService.success("Bodega guardada", 'La bodega fue guardada exitosamente.');
                this.modalRef.hide();
            }, error => {this.alertService.error(error._body); this.loading = false; });


        }

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.productos.path + '?page='+ event.page).subscribe(productos => { 
            this.productos = productos;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    // Filtros
        public openFilter(template: TemplateRef<any>) {
            if(!this.filtro.categorias_id) {
                this.filtro.categorias_id = [];
            }
            if(!this.categorias.length){
                this.apiService.getAll('categorias').subscribe(categorias => { 
                    this.categorias = categorias;
                }, error => {this.alertService.error(error); });
            }
            this.modalRef = this.modalService.show(template);
        }

        public onFiltrar(){
            this.loading = true;
            if (this.filtro.categorias_id[0] == '') {
                this.filtro.categorias_id = null;
            }
            this.filtro.bodega_id = this.id;
            this.apiService.store('bodega/productos/filtrar', this.filtro).subscribe(productos => { 
                this.productos = productos;
                this.loading = false; this.filtrado = true;
                this.modalRef.hide();
            }, error => {this.alertService.error(error); this.loading = false;});

        }

}
