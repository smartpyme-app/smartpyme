import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

import * as moment from 'moment';

@Component({
  selector: 'app-contribuyentes',
  templateUrl: './contribuyentes.component.html',
})

export class ContribuyentesComponent implements OnInit {

    public ivas:any[] = [];
    public sucursales:any[] = [];
    public loading:boolean = false;
    public filtros:any = {};
    modalRef!: BsModalRef;

    constructor( 
        public apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService
    ) { }

    ngOnInit() {   
        this.filtros.id_sucursal = this.apiService.auth_user().id_sucursal;
        this.filtros.tipo_documento = 'Crédito fiscal';
        this.filtros.time = 'day';
        this.filtros.inicio = moment().startOf(this.filtros.time).format('YYYY-MM-DD');
        this.filtros.fin = moment().endOf(this.filtros.time).format('YYYY-MM-DD');

        this.apiService.getAll('sucursales/list').subscribe(sucursales => { 
            this.sucursales = sucursales;
        }, error => {this.alertService.error(error); this.loading = false;});

        this.loadAll();
    }

    public loadAll() {
        this.loading = true;
        this.apiService.getAll('libro-iva', this.filtros).subscribe(ivas => { 
            
            this.ivas = ivas;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public setTime($time:any){
        this.filtros.time = $time;
        this.filtros.inicio = moment().startOf(this.filtros.time).format('YYYY-MM-DD');
        this.filtros.fin = moment().endOf(this.filtros.time).format('YYYY-MM-DD');
        this.loadAll();
    }

    public openModal(template: TemplateRef<any>) {
        this.modalRef = this.modalService.show(template);
    } 

}
