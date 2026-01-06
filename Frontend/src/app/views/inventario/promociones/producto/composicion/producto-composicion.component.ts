import { Component, OnInit, TemplateRef, Input, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';

import { AlertService } from '../../../../../services/alert.service';
import { ApiService } from '../../../../../services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { ModalManagerService } from '../../../../../services/modal-manager.service';
import { BaseModalComponent } from '../../../../../shared/base/base-modal.component';

@Component({
    selector: 'app-producto-composicion',
    templateUrl: './producto-composicion.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule],
    changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProductoComposicionComponent extends BaseModalComponent implements OnInit {

    @Input() producto: any = {};
	public composicion: any = {};
    public productos:any = [];
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
        this.composicion = compuesto;
        super.openModal(template, {class: 'modal-md'});
    }

    selectProducto(value:any){
        this.composicion.producto_id       = this.producto.id;
        this.composicion.nombre_compuesto  = value.nombre;
        this.composicion.compuesto_id      = value.id;
        this.composicion.medida            = value.medida;
        this.composicion.cantidad = 1;
        
        let detalle = this.producto.composiciones.find((x:any) => x.compuesto_id == this.composicion.compuesto_id);
        console.log(detalle);
        if(detalle){
            this.composicion = detalle;
        }

        this.productos.total = 0;
        document.getElementById('cantidad')!.focus();
    }

    onSubmit(){
       
        this.loading = true;
        this.cdr.markForCheck();
        this.apiService.store('producto/composicion', this.composicion).pipe(this.untilDestroyed()).subscribe(composicion => {
            if(!this.composicion.id) {
                this.composicion.id = composicion.id;
                this.producto.composiciones.unshift(this.composicion);
            }
            this.composicion = {};
            this.loading = false;
            this.cdr.markForCheck();
            this.closeModal();
        },error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck();});

    }

    deleteComposicion(composicion:any){
        if (confirm('¿Desea eliminar el Registro?')) {        
            this.apiService.delete('producto/composicion/', composicion.id).pipe(this.untilDestroyed()).subscribe(composicion => {
                for (var i = 0; i < this.producto.composiciones.length; ++i) {
                    if (this.producto.composiciones[i].id === composicion.id ){
                        this.producto.composiciones.splice(i, 1);
                    }
                }
                this.cdr.markForCheck();
            },error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck();});
        }
    }

}
