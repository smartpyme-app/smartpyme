import { Component, OnInit, EventEmitter, Input, Output, TemplateRef, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';

import { ApiService } from '../../../services/api.service';
import { AlertService } from '../../../services/alert.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { ModalManagerService } from '../../../services/modal-manager.service';
import { BaseModalComponent } from '../../base/base-modal.component';
import { LazyImageDirective } from '../../../directives/lazy-image.directive';

@Component({
    selector: 'app-busqueda-producto',
    templateUrl: './busqueda-producto.component.html',
    standalone: true,
    imports: [CommonModule, FormsModule, RouterModule, LazyImageDirective]
})
export class BusquedaProductoComponent extends BaseModalComponent implements OnInit {

    public detalle: any = {};
    public productos: any = [];
    public override loading = false;
    public buscador:any = '';
    @Output() productoSelect = new EventEmitter();

    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);
    
    constructor( 
        public apiService: ApiService,
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService
    ) {
        super(modalManager, alertService);
    }

    ngOnInit() {
    }

    override openModal(template: TemplateRef<any>) {
        this.loading = true;
        this.apiService.getAll('productos/list')
            .pipe(this.untilDestroyed())
            .subscribe(productos => {
            this.productos = productos;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
        super.openModal(template, { class: 'modal-lg', backdrop: 'static' });
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
        this.closeModal();
    }

}
