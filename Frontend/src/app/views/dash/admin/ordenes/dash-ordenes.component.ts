import { Component, OnInit, Input } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { NgChartsModule } from 'ng2-charts';

import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';


@Component({
  selector: 'app-dash-ordenes',
  templateUrl: './dash-ordenes.component.html'
})
export class DashOrdenesComponent implements OnInit {

    @Input() dash:any = {};
    @Input() loading:boolean = false;

	constructor( private alertService:AlertService, private apiService:ApiService
	) { }

	ngOnInit() {  }

    public imprimirOrdenDeCarga(flete:any){
        window.open(this.apiService.baseUrl + '/api/flete/orden-de-carga/' + flete.id + '?token=' + this.apiService.auth_token(), 'hola', 'width=800');
    }

    public imprimirCartaDePorte(flete:any){
        window.open(this.apiService.baseUrl + '/api/flete/carta-de-porte/' + flete.id + '?token=' + this.apiService.auth_token(), 'hola', 'width=800');
    }

    public imprimirManifiestoDeCarga(flete:any){
        window.open(this.apiService.baseUrl + '/api/flete/manifiesto-de-carga/' + flete.id + '?token=' + this.apiService.auth_token(), 'hola', 'width=800');
    }

}
