import { Component, OnInit, TemplateRef, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
    selector: 'app-presupuesto-detalles',
    templateUrl: './presupuesto-detalles.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    
})
export class PresupuestoDetallesComponent implements OnInit {

	public presupuesto: any = {};

    public loading = false;
    public saving = false;
    modalRef!: BsModalRef;

    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

	constructor( 
	    public apiService: ApiService, private alertService: AlertService,
	    private route: ActivatedRoute, private router: Router,
	    private modalService: BsModalService
    ) { 
        this.router.routeReuseStrategy.shouldReuseRoute = function() {return false; };
    }

    ngOnInit() {
        this.loadAll();
	}

	public loadAll(){
	    this.route.params
	      .pipe(this.untilDestroyed())
	      .subscribe((params:any) => {
	        if (params.id) {
	            this.loading = true;
	            this.apiService.read('presupuesto/', params.id)
	              .pipe(this.untilDestroyed())
	              .subscribe(presupuesto => {
		            this.presupuesto = presupuesto;
	            	this.loading = false;
	            }, error => {this.alertService.error(error); this.loading = false; });
	        }
	        else{
	    		this.presupuesto = {};
	            this.presupuesto.fecha = this.apiService.date();
	            this.presupuesto.id_empresa = this.apiService.auth_user().empresa.id;
	            this.presupuesto.estado = "En Proceso";
	        }
	    });
	}

}
