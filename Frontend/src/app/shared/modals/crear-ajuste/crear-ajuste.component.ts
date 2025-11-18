import { Component, OnInit, TemplateRef, Input, Output, EventEmitter, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
    selector: 'app-crear-ajuste',
    templateUrl: './crear-ajuste.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    
})
export class CrearAjusteComponent implements OnInit {

	@Input() producto: any = {};
	@Input() inventario: any = {};
	@Output() setAjuste = new EventEmitter();
	public ajuste: any = {};
	public productos:any = [];
	public lugares:any [] = [];

 	public loading = false;

	modalRef!: BsModalRef;

	private destroyRef = inject(DestroyRef);
	private untilDestroyed = subscriptionHelper(this.destroyRef);

   constructor(private apiService: ApiService, private alertService: AlertService,  
    	private route: ActivatedRoute, private router: Router,
    	private modalService: BsModalService
    ){ }

	ngOnInit() {
	}

	

	openModalAjuste(template: TemplateRef<any>) {
	   this.ajuste = {};
	   this.ajuste.stock_actual = this.inventario.stock;
	   this.ajuste.id_bodega = this.inventario.id_bodega;
	   this.modalRef = this.modalService.show(template, {class: 'modal-md', backdrop: 'static'});
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

        this.apiService.store('ajuste', this.ajuste)
            .pipe(this.untilDestroyed())
            .subscribe(ajuste => {
            this.inventario.stock = ajuste.stock_real;
            this.modalRef.hide();
            this.setAjuste.emit(ajuste);
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false; });

	}

}
