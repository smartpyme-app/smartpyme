import { Component, OnInit, TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal/bs-modal-ref.service';

import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';

@Component({
  selector: 'app-producto',
  templateUrl: './producto.component.html'
})
export class ProductoComponent implements OnInit {

	public producto: any = {};
	public categorias:any[] = [];
  public loading = false;

	constructor( 
	    private apiService: ApiService, private alertService: AlertService,
	    private route: ActivatedRoute, private router: Router,
	) {
		this.router.routeReuseStrategy.shouldReuseRoute = function() {return false; };
	}

	ngOnInit() {
	    
	    this.loadAll();
	}

	loadAll(){
		const id = +this.route.snapshot.paramMap.get('id')!;
		    
	    if(isNaN(id)){
	        this.producto = {};
	        this.producto.tipo = 'Producto';
	        this.producto.empresa_id = this.apiService.auth_user().empresa_id;
	    }
	    else{
	        // Optenemos el producto
	        this.loading = true;
	        this.apiService.read('producto/', id).subscribe(producto => {
	           this.producto = producto;
				this.producto.utilidad = (this.producto.precio - this.producto.costo).toFixed(2);
				this.producto.rentabilidad = ((this.producto.utilidad / this.producto.costo) * 100).toFixed(0);
	           	this.loading = false;
	        },error => {this.alertService.error(error);this.loading = false;});
	    }

	}

	

}
