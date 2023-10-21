import { Component, OnInit,TemplateRef, Input } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { SumPipe }     from '../../../pipes/sum.pipe';

import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';

@Component({
  selector: 'app-plan-de-pagos',
  templateUrl: './plan-de-pagos.component.html'
})
export class PlanDePagosComponent implements OnInit {

    public credito:any = {};
    public loading = false;

    modalRef!: BsModalRef;

    constructor( public apiService:ApiService, private alertService:AlertService, private sumPipe:SumPipe,
        private route: ActivatedRoute, private router: Router, private modalService: BsModalService,
    ) {
        // this.router.routeReuseStrategy.shouldReuseRoute = function() {return false; };
    }

    ngOnInit() {
        this.loadAll();
    }


    public loadAll() {

        this.credito.fecha = this.apiService.date();
        this.credito.tipo_plazo = 'Meses';
        // this.credito.prima = 0;
        // this.credito.total = 0;
        // this.credito.plazo = 0;
        this.credito.interes_anual = 0;
        // this.credito.usuario_id = this.apiService.auth_user().id;
        // this.credito.empresa_id = this.apiService.auth_user().empresa_id;
        
    }


    public onSubmit(){
        this.loading = true;

        setTimeout(()=>{
            window.open(this.apiService.baseUrl + '/api/reporte/credito/plan-de-pagos/' + this.credito.total + '/'  + this.credito.plazo + '/'  + this.credito.tipo_plazo + '/'  + this.credito.interes_anual + '/'  + this.credito.prima + '/'  + this.credito.periodo_de_gracia + '/' +  '?token=' + this.apiService.auth_token(), 'hola', 'width=400');
            this.loading = false;
        }, 1000)

    }


}
