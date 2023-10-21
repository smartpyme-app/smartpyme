import { Component, OnInit } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';

import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';

import * as moment from 'moment';

@Component({
  selector: 'app-asistencia',
  templateUrl: './asistencia.component.html'
})
export class AsistenciaComponent implements OnInit {

	public asistencia: any = {};
    public empleados: any = [];
    public loading = false;
    public guardar = false;

	constructor( 
	    private apiService: ApiService, private alertService: AlertService,
	    private route: ActivatedRoute, private router: Router
	) { }

	ngOnInit() {
        this.loadAll();
        this.apiService.getAll('empleados/list').subscribe(empleados => { 
            this.empleados = empleados;
        }, error => {this.alertService.error(error); });
	}

    public loadAll() {
        const id = +this.route.snapshot.paramMap.get('id')!;
        if(isNaN(id)){
            this.asistencia = {};
            this.asistencia.estado = 'Asistencia';
            this.asistencia.sucursal_id = this.apiService.auth_user().sucursal_id;
            this.asistencia.fecha = this.apiService.date();            
        }
        else{
            this.loading = true;
            this.apiService.read('empleado/asistencia/', id).subscribe(asistencia => { 
                this.asistencia = asistencia;
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false;});
        }

    }

	public onSubmit() {
        this.guardar = true;

        this.apiService.store('empleado/asistencia', this.asistencia).subscribe(asistencia => {
            this.router.navigate(['/asistencias']);
            this.alertService.success("Guardado");
        },error => {this.alertService.error(error); this.guardar = false; });

    }


}
