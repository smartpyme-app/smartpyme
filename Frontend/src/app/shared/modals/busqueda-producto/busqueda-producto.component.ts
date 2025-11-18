import { Component, OnInit, EventEmitter, Input, Output, TemplateRef, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { BsModalService, BsModalRef} from 'ngx-bootstrap/modal';

import { ApiService } from '../../../services/api.service';
import { AlertService } from '../../../services/alert.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
    selector: 'app-busqueda-producto',
    templateUrl: './busqueda-producto.component.html',
    standalone: true,
    imports: [CommonModule, FormsModule, RouterModule]
})
export class BusquedaProductoComponent implements OnInit {

    public detalle: any = {};
    public productos: any = [];
    public loading = false;
    public buscador:any = '';
    @Output() productoSelect = new EventEmitter();

    modalRef?: BsModalRef;

    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

    constructor( 
        public apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService
    ) { }

    ngOnInit() {
    }

    openModal(template: TemplateRef<any>) {
        this.loading = true;
        this.apiService.getAll('productos/list')
            .pipe(this.untilDestroyed())
            .subscribe(productos => {
            this.productos = productos;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
        this.modalRef = this.modalService.show(template, { class: 'modal-lg', backdrop: 'static' });
    }


    public select(producto:any):void{
        this.detalle = producto;
        this.detalle.cantidad = 1;
        this.detalle.producto_id = producto.id;
        this.detalle.producto_nombre = producto.nombre;

        this.detalle.producto_id  = producto.id;
        this.detalle.producto_nombre     = producto.nombre;

        this.detalle.descuento    = 0;
        this.detalle.iva         = 0;

        // Descuento promoción si esta en fecha
        if (producto.promocion) {
            this.detalle.descuento = producto.precio - producto.promocion.precio;
        }

        console.log(this.detalle);

        document.getElementById('cantidad')?.focus();
    }

    agregarDetalle(){
        this.productoSelect.emit(this.detalle);
        this.productos.data = [];
        this.buscador = '';
        this.modalRef?.hide();
    }

}
