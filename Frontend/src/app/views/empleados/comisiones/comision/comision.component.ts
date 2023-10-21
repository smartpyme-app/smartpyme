import { Component, OnInit} from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';

@Component({
  selector: 'app-comision',
  templateUrl: './comision.component.html'
})
export class ComisionComponent implements OnInit {

	public comision: any = {};
	public empleados:any = [];
	public lugares:any [] = [];
 	public loading = false;

   constructor(private apiService: ApiService, private alertService: AlertService,  
    	private route: ActivatedRoute, private router: Router,
    	private modalService: BsModalService
    ){ }

	ngOnInit() {
        this.loadAll();
        this.apiService.getAll('empleados/list').subscribe(empleados => { 
            this.empleados = empleados;
            this.loading = false;
        }, error => {this.alertService.error(error); });
	}

    public loadAll() {
        const id = +this.route.snapshot.paramMap.get('id')!;
        if(isNaN(id)){
            this.comision = {};
            this.comision.fecha = this.apiService.date();
            this.comision.tipo = 'Por venta';
            this.comision.estado = 'Pendiente';
            this.comision.usuario_id = this.apiService.auth_user().id;
        }
        else{
            this.loading = true;
            this.apiService.read('comision/', id).subscribe(comision => { 
                this.comision = comision;
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false;});
        }

    }
	
	public onSubmit() {
	    this.loading = true;
	    this.apiService.store('comision', this.comision).subscribe(comision => {
           this.router.navigate(['/comisiones']);
           this.alertService.success("Guardado");
	       this.loading = false;
	    }, error => {this.alertService.error(error); this.loading = false; });

	}

}
