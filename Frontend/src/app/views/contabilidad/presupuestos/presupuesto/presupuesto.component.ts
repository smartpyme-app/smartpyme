import { Component, OnInit, TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';

@Component({
  selector: 'app-presupuesto',
  templateUrl: './presupuesto.component.html'
})
export class PresupuestoComponent implements OnInit {

	public presupuesto: any = {};

    public loading = false;
    public saving = false;
    modalRef!: BsModalRef;

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
	    this.route.params.subscribe((params:any) => {
	        if (params.id) {
	            this.loading = true;
	            this.apiService.read('presupuesto/', params.id).subscribe(presupuesto => {
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

	public onSubmit() {
        this.saving = true;
        this.apiService.store('presupuesto', this.presupuesto).subscribe(presupuesto => {
            if (!this.presupuesto.id) {
                this.alertService.success('Presupuesto creado', 'El presupuesto fue añadido exitosamente.');
            }else{
                this.alertService.success('Presupuesto guardado', 'El presupuesto fue guardado exitosamente.');
            }
            this.router.navigateByUrl('/presupuestos');
            this.saving = false;
        }, error => {this.alertService.error(error); this.saving = false; });
    }

}
