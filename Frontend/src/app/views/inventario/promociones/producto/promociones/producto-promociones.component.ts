import { Component, OnInit, TemplateRef, Input, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';

import { AlertService } from '../../../../../services/alert.service';
import { ApiService } from '../../../../../services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { ModalManagerService } from '../../../../../services/modal-manager.service';
import { BaseModalComponent } from '../../../../../shared/base/base-modal.component';
import * as moment from 'moment';

declare var $:any;
@Component({
    selector: 'app-producto-promociones',
    templateUrl: './producto-promociones.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule],
    
})
export class ProductoPromocionesComponent extends BaseModalComponent implements OnInit {

    @Input() producto: any = {};
	public promocion: any = {};

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

	ngOnInit() {
        // this.loadAll();
    }

    // Promociones
    override openModal(template: TemplateRef<any>, promocion:any) {
        this.promocion = promocion;
        if (!this.promocion.id) {
            this.promocion.inicio = moment(new Date()).format('YYYY-MM-DDTHH:mm');
            this.promocion.fin = moment(new Date()).format('YYYY-MM-DDTHH:mm');
        }else{
            this.promocion.inicio = moment(this.promocion.inicio).format('YYYY-MM-DDTHH:mm');
            this.promocion.fin = moment(this.promocion.fin).format('YYYY-MM-DDTHH:mm');
        }
        super.openModal(template, {class: 'modal-sm'});
    }

    onSubmit(){
        this.promocion.producto_id = this.producto.id;
        console.log(this.promocion);
        this.loading = true;
        this.apiService.store('producto/promocion', this.promocion).pipe(this.untilDestroyed()).subscribe(promocion => {
            if(!this.promocion.id) {
                this.promocion.id = promocion.id;
                this.producto.promociones.unshift(this.promocion);
            }
            this.promocion = {};
            this.loading = false;
            this.closeModal();
        },error => {this.alertService.error(error); this.loading = false;});
    }

    deletePromocion(promocion:any){
        if (confirm('¿Desea eliminar el Registro?')) {        
            this.apiService.delete('producto/promocion/', promocion.id).pipe(this.untilDestroyed()).subscribe(promocion => {
                for (var i = 0; i < this.producto.promociones.length; ++i) {
                    if (this.producto.promociones[i].id === promocion.id ){
                        this.producto.promociones.splice(i, 1);
                    }
                }
            },error => {this.alertService.error(error); this.loading = false;});
        }
    }


}
