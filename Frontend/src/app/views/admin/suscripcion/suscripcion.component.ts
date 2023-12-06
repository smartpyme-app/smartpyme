import { Component, OnInit, ViewChild } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-suscripcion',
  templateUrl: './suscripcion.component.html'
})
export class SuscripcionComponent implements OnInit {

    public suscripcion: any = {};
    public loading = false;
    public saving = false;

  	constructor( 
  	    public apiService: ApiService, private alertService: AlertService,
  	    private route: ActivatedRoute, private router: Router
  	) { }

  	ngOnInit() {
  	    this.loading = true;
        this.apiService.getAll('suscripcion').subscribe(suscripcion => {
            this.suscripcion = suscripcion;
            this.loading = false;
        },error => {this.alertService.error(error); this.loading = false; });
  	}
     
  	public onSubmit() {
  	    this.saving = true;
  	    this.apiService.store('suscripcion', this.suscripcion).subscribe(suscripcion => {
  	        this.suscripcion = suscripcion;
  	        this.alertService.success('Suscripción guardada', 'Los datos de tu plan fueron guardados exitosamente.');
  	        this.saving = false;
  	    },error => {this.alertService.error(error); this.saving = false; });
  	}

    public imprimirDoc(recibo:any){
        window.open(this.apiService.baseUrl + '/api/recibo/pdf/' + recibo.id + '?token=' + this.apiService.auth_token());
    }

}
