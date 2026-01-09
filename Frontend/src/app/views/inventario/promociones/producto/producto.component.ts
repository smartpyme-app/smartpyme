import { Component, OnInit, TemplateRef, DestroyRef, inject, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
    selector: 'app-producto',
    templateUrl: './producto.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule],
    changeDetection: ChangeDetectionStrategy.OnPush
})
export class ProductoComponent implements OnInit {

	public producto: any = {};
	public categorias:any[] = [];
  public loading = false;

  private destroyRef = inject(DestroyRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);

	constructor( 
	    private apiService: ApiService, private alertService: AlertService,
	    private route: ActivatedRoute, private router: Router,
	    private cdr: ChangeDetectorRef
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
	        this.apiService.read('producto/', id).pipe(this.untilDestroyed()).subscribe(producto => {
	           this.producto = producto;
				this.producto.utilidad = (this.producto.precio - this.producto.costo).toFixed(2);
				this.producto.rentabilidad = ((this.producto.utilidad / this.producto.costo) * 100).toFixed(0);
	           	this.loading = false;
	           	this.cdr.markForCheck();
	        },error => {this.alertService.error(error);this.loading = false;this.cdr.markForCheck();});
	    }

	}

	

}
