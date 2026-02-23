import { Component, OnInit, TemplateRef, Input, Output, EventEmitter } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-crear-ajuste-lote',
  templateUrl: './crear-ajuste-lote.component.html'
})
export class CrearAjusteLoteComponent implements OnInit {

	@Input() producto: any = {};
	@Input() lote: any = {};
	@Output() setAjuste = new EventEmitter();
	public ajuste: any = {};

 	public loading = false;

	modalRef!: BsModalRef;

   constructor(private apiService: ApiService, private alertService: AlertService,  
    	private route: ActivatedRoute, private router: Router,
    	private modalService: BsModalService
    ){ }

	ngOnInit() {
	}

	openModalAjuste(template: TemplateRef<any>) {
	   this.ajuste = {};
	   this.ajuste.stock_actual = this.lote.stock;
	   this.modalRef = this.modalService.show(template, {class: 'modal-md', backdrop: 'static'});
	}

    public calAjuste(){
        this.ajuste.ajuste = parseFloat(this.ajuste.stock_real) - parseFloat(this.ajuste.stock_actual);
    }
	
	public onSubmit() {
        this.loading = true;
        this.ajuste.id_producto = this.producto.id;
        this.ajuste.id_bodega = this.lote.id_bodega;
        this.ajuste.lote_id = this.lote.id;
        this.ajuste.id_empresa = this.apiService.auth_user().id_empresa;
        this.ajuste.id_usuario = this.apiService.auth_user().id;

        this.apiService.store('ajuste-lote', this.ajuste).subscribe(ajuste => {
            this.lote.stock = ajuste.stock_real;
            // Si es el primer ajuste y el stock_inicial es 0, actualizarlo
            if (this.lote.stock_inicial == 0 && ajuste.stock_real > 0) {
                this.lote.stock_inicial = ajuste.stock_real;
            }
            this.modalRef.hide();
            this.setAjuste.emit({
                ...ajuste,
                lote_id: this.lote.id,
                stock_inicial: this.lote.stock_inicial
            });
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false; });

	}

}
