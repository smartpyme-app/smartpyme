import { Component, OnInit, TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal/bs-modal-ref.service';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-producto',
  templateUrl: './producto.component.html'
})
export class ProductoComponent implements OnInit {

	public producto: any = {};
	public categorias:any[] = [];
  public loading = false;

	constructor( 
	    public apiService: ApiService, private alertService: AlertService,
	    private route: ActivatedRoute, private router: Router,
	) {	}

	ngOnInit() {
	    
		this.route.params.subscribe((params:any) => {
	      	if (params.id) {
		        this.loading = true;
		        this.apiService.read('producto/', params.id).subscribe(producto => {
		        this.producto = producto;
                this.producto.impuesto = this.apiService.auth_user().empresa.iva / 100;
                this.producto.precio_final = ((this.producto.precio * 1) + (this.producto.precio * this.producto.impuesto)).toFixed(2);
	            this.loading = false;
		    },error => {this.alertService.error(error);this.loading = false;});
	      	} else {
				this.producto = {};
				this.producto.tipo = 'Producto';
				this.producto.medida = 'Unidad';
				this.producto.id_empresa = this.apiService.auth_user().id_empresa;

				if (this.route.snapshot.queryParamMap.get('tipo')!) {
				    this.producto.tipo = this.route.snapshot.queryParamMap.get('tipo')!;
				    this.producto.precio = 0;
				}

	      	}
	    });
	}

	loadAll(){

	}

	

}
