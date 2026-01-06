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

@Component({
    selector: 'app-producto-composicion',
    templateUrl: './producto-composicion.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule],
    changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProductoComposicionComponent extends BaseModalComponent implements OnInit {

    @Input() producto: any = {};
	public composicion: any = {};
    public productos:any = [];
    public opcion: any = {};
    public buscador:string = '';

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
        
        if(compuesto.id){
            this.composicion = compuesto;
        }else{
            this.composicion.id_producto = this.producto.id;
            this.composicion.id_compuesto = '';
        }
        
        super.openModal(template, {class: 'modal-md'});
    }

    onSubmit(){
       
        this.saving = true;
        this.cdr.markForCheck();
        this.apiService.store('producto/composicion', this.composicion)
          .pipe(this.untilDestroyed())
          .subscribe(composicion => {
            if(!this.composicion.id) {
                composicion.opciones = [];
                this.producto.composiciones.unshift(composicion);
            }
            this.composicion = {};
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
            this.apiService.getAll('productos/list')
              .pipe(this.untilDestroyed())
              .subscribe(productos => {
                this.productos = productos;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.cdr.markForCheck();});

            super.openModal(template, {class: 'modal-md'});
        }

        public agregarOpcion(){
            this.loading = true;
            this.cdr.markForCheck();
            this.opcion.id_composicion = this.composicion.id;
            this.apiService.store('producto/composicion/opcion', this.opcion)
              .pipe(this.untilDestroyed())
              .subscribe(opcion => {
                this.composicion.opciones.push(opcion);
                this.opcion = {};
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
