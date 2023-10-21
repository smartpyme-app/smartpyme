import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';

import * as moment from 'moment';

@Component({
  selector: 'app-motoristas-fletes',
  templateUrl: './motoristas-fletes.component.html'
})

export class MotoristasFletesComponent implements OnInit {

	public motoristas:any = [];
    public buscador:any = '';
    public loading:boolean = false;

    public filtro:any = {};
    modalRef!: BsModalRef;

    constructor(
        public apiService: ApiService, private alertService: AlertService, 
        private modalService: BsModalService
    ){}

    ngOnInit() {
        this.filtro.inicio = moment().startOf('week').format('YYYY-MM-DD');
        this.filtro.fin = moment().endOf('week').format('YYYY-MM-DD');
        this.loadAll();
    }

    public loadAll() {

        if(!this.filtro.inicio) {
            this.filtro.inicio = moment().startOf(this.filtro.time).format('YYYY-MM-DD');
            this.filtro.fin = moment().endOf(this.filtro.time).format('YYYY-MM-DD');
            this.filtro.nombre     = '';
        }

        this.loading = true;

        this.apiService.store('motoristas/fletes', this.filtro).subscribe(motoristas => { 
            this.motoristas = motoristas;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});

    }


}
