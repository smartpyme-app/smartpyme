import { Component, OnInit, TemplateRef, Input, Output, EventEmitter } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '../../base/base-modal.component';

@Component({
    selector: 'app-crear-ajuste',
    templateUrl: './crear-ajuste.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    
})
export class CrearAjusteComponent extends BaseModalComponent implements OnInit {

	@Input() producto: any = {};
	@Input() inventario: any = {};
	@Output() setAjuste = new EventEmitter();
	public ajuste: any = {};
	public productos:any = [];
	public lugares:any [] = [];

 	public override loading = false;

   constructor(
        private apiService: ApiService,
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService,
    	private route: ActivatedRoute,
        private router: Router
    ) {
        super(modalManager, alertService);
    }

	ngOnInit() {
	}

	

	openModalAjuste(template: TemplateRef<any>) {
	   this.ajuste = {};
	   this.ajuste.stock_actual = this.inventario.stock;
	   this.ajuste.id_bodega = this.inventario.id_bodega;
	   this.openModal(template, {class: 'modal-md', backdrop: 'static'});
	}

    public calAjuste(){
        this.ajuste.ajuste = parseFloat(this.ajuste.stock_real) - parseFloat(this.ajuste.stock_actual);
    }
	
	public onSubmit() {
        this.loading = true;
        this.ajuste.id_producto = this.producto.id;
        this.ajuste.id_bodega = this.inventario.id_bodega;
        this.ajuste.id_empresa = this.apiService.auth_user().id_empresa;
        this.ajuste.id_usuario = this.apiService.auth_user().id;

        this.apiService.store('ajuste', this.ajuste).subscribe(ajuste => {
            this.inventario.stock = ajuste.stock_real;
            this.closeModal();
            this.setAjuste.emit(ajuste);
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false; });

	}

}
