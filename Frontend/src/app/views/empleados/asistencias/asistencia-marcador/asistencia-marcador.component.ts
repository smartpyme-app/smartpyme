import { Component, OnInit } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';

import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';

import * as moment from 'moment';

@Component({
  selector: 'app-asistencia-marcador',
  templateUrl: './asistencia-marcador.component.html'
})
export class AsistenciaMarcadorComponent implements OnInit {

	public asistencia: any = {};
	public user: any = {};
	public fechaHora:any = '';
    public loading = false;
    public guardar = false;
    public clock:any; 

	constructor( 
	    private apiService: ApiService, private alertService: AlertService,
	    private route: ActivatedRoute, private router: Router
	) { }

	ngOnInit() {

        this.fechaHora = moment(new Date()).format('YYYY-MM-DDTHH:mm:ss');
        this.clock = setInterval(()=>{
            this.fechaHora = moment(new Date()).format('YYYY-MM-DDTHH:mm:ss');
            console.log(this.fechaHora);
        },1000)

        this.getLocation();

	}

    public getLocation() {
        this.loading = true;
        this.apiService.getPosition().then(pos => {
            this.asistencia.ubicacion = pos.lat + ', ' + pos.lng;
            this.loading = false;
        });
    }


	public onSubmit() {
        this.guardar = true;

        this.user.username = this.user.username.toLowerCase();
        this.user.password = this.user.password.toLowerCase();

        this.apiService.store('usuario-auth', this.user).subscribe(usuario => {
            this.asistencia.fecha = this.apiService.date();
            this.asistencia.estado = 'Asistencia';
            this.asistencia.empleado_id = usuario.empleado_id;
            this.apiService.store('empleado/asistencia/marcar', this.asistencia).subscribe(asistencia => {
            	if (!asistencia.salida) {
					this.alertService.success("Se ha registrado la entrada");
            	}else{
					this.alertService.success("Se ha registrado la salida");
            	}
                this.guardar = false;
                this.user = {};
                this.asistencia = {};
                this.getLocation();
            },error => {this.alertService.error(error); this.guardar = false; });
        },
        error => {this.alertService.error(error); this.guardar = false; });

    }

    ngOnDestroy(){
        clearInterval(this.clock);

    }

}
