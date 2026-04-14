import { Component, OnInit, TemplateRef, Input, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';
import { NotificacionesContainerComponent } from '@shared/parts/notificaciones/notificaciones-container.component';
import { TooltipModule } from 'ngx-bootstrap/tooltip';

@Component({
    selector: 'app-producto-composicion',
    templateUrl: './producto-composicion.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, NotificacionesContainerComponent, TooltipModule],
    changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProductoComposicionComponent extends BaseModalComponent implements OnInit {

    @Input() producto: any = {};
	public composicion: any = {};
    public productos:any = [];
    public opcion: any = {};
    public buscador:string = '';

    // Producto seleccionado del buscador
    public productoSeleccionado: any = null;

    constructor(
        private apiService: ApiService,
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService,
    	private route: ActivatedRoute,
    	private router: Router,
        private cdr: ChangeDetectorRef
    ){
        super(modalManager, alertService);
    }

	ngOnInit() {}

    override openModal(template: TemplateRef<any>, compuesto:any) {
        this.apiService.getAll('productos/list')
          .pipe(this.untilDestroyed())
          .subscribe(productos => {
            this.productos = productos;
            this.cdr.markForCheck();
        }, error => {this.alertService.error(error); this.cdr.markForCheck();});
      // Limpiar selección
      this.productoSeleccionado = null;

        if(compuesto.id){
            this.composicion = compuesto;
            // Si ya tiene un producto compuesto seleccionado, cargarlo
            if (compuesto.id_compuesto) {
                this.apiService.read('productos/', compuesto.id_compuesto).subscribe(producto => {
                    this.productoSeleccionado = producto;
                });
            }
        }else{
            this.composicion = {
                id_producto: this.producto.id,
                id_compuesto: '',
                cantidad: ''
            };
        }

        super.openModal(template, {class: 'modal-md'});
    }

    productoSelect(producto: any) {
        this.productoSeleccionado = producto;
        this.composicion.id_compuesto = producto.id;
    }

    limpiarProducto() {
        this.productoSeleccionado = null;
        this.composicion.id_compuesto = '';
    }

    onSubmit(){
        if (!this.producto?.id) {
            this.alertService.error('Guarde el producto primero para poder agregar composiciones.');
            return;
        }

        if (!this.composicion.id_compuesto) {
            this.alertService.error('Debe seleccionar un producto');
            return;
        }

        if (!this.composicion.cantidad || this.composicion.cantidad <= 0) {
            this.alertService.error('Debe ingresar una cantidad válida');
            return;
        }

        this.saving = true;
        this.cdr.markForCheck();
        this.apiService.store('producto/composicion', this.composicion)
          .pipe(this.untilDestroyed())
          .subscribe(composicion => {
            if(!this.composicion.id) {
                composicion.opciones = [];
                this.producto.composiciones.unshift(composicion);
            } else {
                // Actualizar la composición existente en la lista
                const index = this.producto.composiciones.findIndex((c: any) => c.id === composicion.id);
                if (index !== -1) {
                    this.producto.composiciones[index] = composicion;
                }
            }
            this.composicion = {};
            this.productoSeleccionado = null;
            this.saving = false;
            this.cdr.markForCheck();
            this.closeModal();
        },error => {this.alertService.error(error); this.saving = false; this.cdr.markForCheck();});

    }

    delete(composicion:any){
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('producto/composicion/', composicion.id)
              .pipe(this.untilDestroyed())
              .subscribe(composicion => {
                for (var i = 0; i < this.producto.composiciones.length; ++i) {
                    if (this.producto.composiciones[i].id === composicion.id ){
                        this.producto.composiciones.splice(i, 1);
                    }
                }
                this.cdr.markForCheck();
            },error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck();});
        }
    }

    // Opciones

        public openModalOpciones(template: TemplateRef<any>, composicion:any) {
            this.composicion = composicion;
            // Limpiar selección para opciones
            this.productoSeleccionado = null;
            this.opcion = {};
            this.apiService.getAll('productos/list')
              .pipe(this.untilDestroyed())
              .subscribe(productos => {
                this.productos = productos;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.cdr.markForCheck();});

            super.openModal(template, {class: 'modal-md'});
        }

        productoSelectOpcion(producto: any) {
            this.opcion.id_producto = producto.id;
            this.agregarOpcion();
        }

        limpiarProductoOpcion() {
            this.productoSeleccionado = null;
            this.opcion.id_producto = '';
        }


        public agregarOpcion(){
            if (!this.opcion.id_producto) {
                this.alertService.error('Debe seleccionar un producto');
                return;
            }

            this.loading = true;
            this.cdr.markForCheck();
            this.opcion.id_composicion = this.composicion.id;
            this.apiService.store('producto/composicion/opcion', this.opcion)
              .pipe(this.untilDestroyed())
              .subscribe(opcion => {
                this.composicion.opciones.push(opcion);
                this.opcion = {};
                this.productoSeleccionado = null;
                this.loading = false;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck(); });
        }

        public deleteOpcion(opcion:any){
            if (confirm('¿Desea eliminar el Registro?')) {
                this.apiService.delete('producto/composicion/opcion/', opcion.id)
                  .pipe(this.untilDestroyed())
                  .subscribe(opcion => {
                    for (let i = 0; i < this.composicion.opciones.length; i++) {
                        if (this.composicion.opciones[i].id == opcion.id )
                            this.composicion.opciones.splice(i, 1);
                    }
                    this.cdr.markForCheck();
                }, error => {this.alertService.error(error); this.cdr.markForCheck(); });
            }
        }

}
