import { Component, OnInit, TemplateRef } from '@angular/core';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-empleados-ventas',
  templateUrl: './empleados-ventas.component.html'
})

export class EmpleadosVentasComponent implements OnInit {

	public empleados:any = [];
    public loading:boolean = false;
    public buscador:any = '';
    public filtro:any = {};
    
    modalRef?: BsModalRef;

    constructor( public apiService:ApiService, private alertService:AlertService, private modalService: BsModalService ){}

	ngOnInit() {
        let today = new Date();

        this.filtro.mes = today.getMonth() + 1;
        this.filtro.ano = today.getFullYear();
        this.loadAll();
    }

    public loadAll() {
    	this.loading = true;
        this.apiService.store('empleados/ventas', this.filtro).subscribe(empleados => { 
            this.empleados = empleados;
    		this.loading = false;
        }, error => {this.alertService.error(error); });
    }


}

