import { Component, OnInit, TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

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

    sumEgresos(){
        this.presupuesto.egresos = ((this.presupuesto.alquiler ? parseFloat(this.presupuesto.alquiler) : 0)
                                    + (this.presupuesto.varios ? parseFloat(this.presupuesto.varios) : 0)
                                    + (this.presupuesto.mantenimiento ? parseFloat(this.presupuesto.mantenimiento) : 0)
                                    + (this.presupuesto.marketing ? parseFloat(this.presupuesto.marketing) : 0)
                                    + (this.presupuesto.materia_prima ? parseFloat(this.presupuesto.materia_prima) : 0)
                                    + (this.presupuesto.comisiones ? parseFloat(this.presupuesto.comisiones) : 0)
                                    + (this.presupuesto.planilla ? parseFloat(this.presupuesto.planilla) : 0)
                                    + (this.presupuesto.servicios ? parseFloat(this.presupuesto.servicios) : 0)
                                    + (this.presupuesto.combustible ? parseFloat(this.presupuesto.combustible) : 0)
                                    + (this.presupuesto.prestamos ? parseFloat(this.presupuesto.prestamos) : 0)
                                    + (this.presupuesto.costo_de_venta ? parseFloat(this.presupuesto.costo_de_venta) : 0)
                                    + (this.presupuesto.insumos ? parseFloat(this.presupuesto.insumos) : 0)
                                    + (this.presupuesto.impuestos ? parseFloat(this.presupuesto.impuestos) : 0)
                                    + (this.presupuesto.gastos_administrativos ? parseFloat(this.presupuesto.gastos_administrativos) : 0)
                                    + (this.presupuesto.publicidad ? parseFloat(this.presupuesto.publicidad) : 0)).toFixed(2);
        console.log(this.presupuesto);
    }

    calUtilidad(){
        this.presupuesto.utilidad = (this.presupuesto.ingresos - this.presupuesto.egresos).toFixed(2);
    }

}
