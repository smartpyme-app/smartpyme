import { Component, OnInit, TemplateRef, Input, Output, EventEmitter } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';

@Component({
  selector: 'app-crear-ajuste',
  templateUrl: './crear-ajuste.component.html'
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

   constructor(private apiService: ApiService, private alertService: AlertService,  
    	private route: ActivatedRoute, private router: Router,
    	private modalService: BsModalService
    ){ }

	ngOnInit() {
	}

    calAjuste(){
        this.ajuste.ajuste = this.ajuste.stock_final - this.ajuste.stock_inicial;
    }
	

	openModalAjuste(template: TemplateRef<any>) {
	   this.ajuste = {};
	   this.ajuste.stock_inicial = this.inventario.stock;
	   this.modalRef = this.modalService.show(template, {class: 'modal-sm', backdrop: 'static'});
	}
	
	public onSubmitAjuste() {
        this.loading = true;
        this.ajuste.producto_id = this.producto.id;
        this.ajuste.bodega_id = this.inventario.bodega_id;
        this.ajuste.usuario_id = this.apiService.auth_user().id;
        this.apiService.store('ajuste', this.ajuste).subscribe(ajuste => {
            this.inventario.stock = ajuste.stock_final;
            this.modalRef.hide();
            this.setAjuste.emit({ajuste});
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false; });

	}

}
