import { Component, OnInit, TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal/bs-modal-ref.service';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-materia-prima',
  templateUrl: './materia-prima.component.html'
})
export class MateriaPrimaComponent implements OnInit {

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
	        this.producto.tipo = 'Materia Prima';
	        this.producto.empresa_id = this.apiService.auth_user().empresa_id;
	    }
	    else{
	        // Optenemos el producto
	        this.loading = true;
	        this.apiService.read('materia-prima/', id).subscribe(producto => {
	           this.producto = producto;
	           this.loading = false;
	        },error => {this.alertService.error(error);this.loading = false;});
	    }

	}

	public onSubmit() {
	    this.loading = true;
	    this.apiService.store('materia-prima', this.producto).subscribe(producto => {
	        this.loading = false;
	    	if(!this.producto.id) {
	    		this.producto = producto;
	    		this.router.navigate(['/materia-prima/'+ producto.id]);
	    	}
	        this.alertService.success("Producto guardado");
	    },error => {this.alertService.error(error); this.loading = false; });
	}
	

}
