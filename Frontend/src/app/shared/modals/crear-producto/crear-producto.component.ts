import { Component, OnInit, TemplateRef, Input, Output, EventEmitter } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal/bs-modal-ref.service';

import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';

@Component({
  selector: 'app-crear-producto',
  templateUrl: './crear-producto.component.html'
})
export class CrearProductoComponent implements OnInit {

    public producto: any = {};
    @Output() update = new EventEmitter();
    public categoria:any = {};
    public subcategorias:any = [];
    public categorias:any[] = [];
    public bodegas:any[] = [];
    public loading = false;

    modalRef?: BsModalRef;

    constructor( 
        private apiService: ApiService, private alertService: AlertService,
        private route: ActivatedRoute, private router: Router, private modalService: BsModalService
    ) {
        this.router.routeReuseStrategy.shouldReuseRoute = function() {return false; };
    }

    ngOnInit() {
        
        this.producto.empresa_id = this.apiService.auth_user().empresa_id;

        this.apiService.getAll('categorias').subscribe(categorias => {
            this.categorias = categorias;
        }, error => {this.alertService.error(error);});
        this.apiService.getAll('bodegas').subscribe(bodegas => {
            this.bodegas = bodegas;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false; });

    }

    openModal(template: TemplateRef<any>) {
        this.producto = {};
        this.modalRef = this.modalService.show(template, { class: 'modal-lg', backdrop: 'static' });
    }

    public onSelectCategoria(categoria_id:any){
        this.categoria = this.categorias.find(item => item.id == categoria_id);
        this.subcategorias = this.categoria.subcategorias;
    }

    public setCategoria(categoria:any){
        this.categorias.push(categoria);
        this.producto.categoria_id = categoria.id;
    }

    public setSubCategoria(subcategoria:any){
        this.subcategorias.push(subcategoria);
        this.producto.subcategoria_id = subcategoria.id;
    }

    public onSubmit() {
        this.loading = true;
        this.producto.tipo = 'Producto';
        this.producto.empresa_id = this.apiService.auth_user().empresa_id;
        this.apiService.store('compra/guardar-producto', this.producto).subscribe(producto => {
            this.update.emit(producto);
            this.modalRef?.hide();
            this.alertService.success("Producto guardado");
        },error => {this.alertService.error(error); this.loading = false; });
    }

    public barcode(){
        var ventana = window.open(this.apiService.baseUrl + "/api/producto/barcode/" + this.producto.id + "?token=" + this.apiService.auth_token(), "_new", "toolbar=yes, scrollbars=yes, resizable=yes, left=100, width=900, height=900");
    }

    

}
