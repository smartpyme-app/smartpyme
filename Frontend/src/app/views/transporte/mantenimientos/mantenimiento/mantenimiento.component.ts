import { Component, OnInit, TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { SumPipe }     from '../../../../pipes/sum.pipe';
import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';


@Component({
  selector: 'app-mantenimiento',
  templateUrl: './mantenimiento.component.html',
  providers: [ SumPipe ]
})

export class MantenimientoComponent implements OnInit {

	public mantenimiento: any= {};
    public flotas:any = [];
    public loading = false;
    
	constructor( 
	    private apiService: ApiService, private alertService: AlertService,
	    private route: ActivatedRoute, private router: Router, private sumPipe:SumPipe
	) { }

	ngOnInit() {
	    
	    const id = +this.route.snapshot.paramMap.get('id')!;
	        
        if(isNaN(id)){
            this.cargarDatosIniciales();
        }
        else{
            this.loading = true;
            this.apiService.read('mantenimiento/', id).subscribe(mantenimiento => {
               	this.mantenimiento = mantenimiento;
        		this.loading = false;
            });
        }

        this.apiService.getAll('flotas').subscribe(flotas => { 
            this.flotas = flotas;
            this.loading = false;
        }, error => {this.alertService.error(error); });

	}

	cargarDatosIniciales(){
		this.mantenimiento = {};
		this.mantenimiento.fecha = this.apiService.date();
		this.mantenimiento.tipo = 'Preventivo';
        this.mantenimiento.estado = 'En Proceso';
		this.mantenimiento.detalles = [];
		this.mantenimiento.bodega_id = this.apiService.auth_user().bodega_id;
        this.mantenimiento.usuario_id = this.apiService.auth_user().id;
        this.mantenimiento.sucursal_id = this.apiService.auth_user().sucursal_id;
		this.sumTotal();
	}


    updateOrden(mantenimiento:any) {
        this.mantenimiento = mantenimiento;
        this.sumTotal();
    }

	public sumTotal() {
        this.mantenimiento.total = this.sumPipe.transform(this.mantenimiento.detalles, 'total');
	}

	public onCompletado() {
        if (confirm('¿Confirma guardar el mantenimiento como completado?')) {
            this.mantenimiento.estado = 'Completado';
            this.onSubmit();
        }

	}

    public onSubmit() {
        this.loading = true;
        this.apiService.store('mantenimiento/facturacion', this.mantenimiento).subscribe(mantenimiento => {
            this.router.navigate(['/mantenimientos']);
            this.alertService.success("Guardado");
		    this.loading = false;
        },error => {this.alertService.error(error); this.loading = false; });

    }

}
