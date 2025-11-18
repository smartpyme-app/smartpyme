import { Component, OnInit, TemplateRef, Input, DestroyRef, inject } from '@angular/core';
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
    
})
export class ProductoComposicionComponent extends BaseModalComponent implements OnInit {

    @Input() producto: any = {};
	public composicion: any = {};
    public productos:any = [];
    public opcion: any = {};
    public buscador:string = '';

    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

    constructor(
        private apiService: ApiService, 
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService,
    	private route: ActivatedRoute, 
    	private router: Router
    ){
        super(modalManager, alertService);
    }

	ngOnInit() {}

    override openModal(template: TemplateRef<any>, compuesto:any) {
        this.apiService.getAll('productos/list')
          .pipe(this.untilDestroyed())
          .subscribe(productos => {
            this.productos = productos;
        }, error => {this.alertService.error(error);});
        
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
        this.apiService.store('producto/composicion', this.composicion)
          .pipe(this.untilDestroyed())
          .subscribe(composicion => {
            if(!this.composicion.id) {
                composicion.opciones = [];
                this.producto.composiciones.unshift(composicion);
            }
            this.composicion = {};
            this.saving = false;
            this.closeModal();
        },error => {this.alertService.error(error); this.saving = false;});

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
            },error => {this.alertService.error(error); this.loading = false;});
        }
    }

    // Opciones

        public openModalOpciones(template: TemplateRef<any>, composicion:any) {
            this.composicion = composicion;
            this.apiService.getAll('productos/list')
              .pipe(this.untilDestroyed())
              .subscribe(productos => {
                this.productos = productos;
            }, error => {this.alertService.error(error);});

            super.openModal(template, {class: 'modal-md'});
        }


        public agregarOpcion(){
            this.loading = true;
            this.opcion.id_composicion = this.composicion.id;
            this.apiService.store('producto/composicion/opcion', this.opcion)
              .pipe(this.untilDestroyed())
              .subscribe(opcion => {
                this.composicion.opciones.push(opcion);
                this.opcion = {};
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false; });
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
                }, error => {this.alertService.error(error); });
            }
        }


}
