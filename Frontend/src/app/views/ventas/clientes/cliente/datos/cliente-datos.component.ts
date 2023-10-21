import { Component, OnInit } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

import * as moment from 'moment';

@Component({
  selector: 'app-cliente-datos',
  templateUrl: './cliente-datos.component.html',
})
export class ClienteDatosComponent implements OnInit {

    public dash:any;
    public loading:boolean = false;

    public filtro:any = {};
    

    constructor( public apiService:ApiService, private alertService:AlertService, private route: ActivatedRoute ){}

    ngOnInit() {
        this.filtro.id = +this.route.snapshot.paramMap.get('id')!;
        this.filtro.time = 'year';
        this.onFiltrar(this.filtro.time);
    }

    onFiltrar($time:any){
        this.filtro.time = $time;        
        this.filtro.inicio = moment().startOf(this.filtro.time).format('YYYY-MM-DD');
        this.filtro.fin = moment().endOf(this.filtro.time).format('YYYY-MM-DD');

        this.loading = true;
        this.apiService.store('cliente/datos', this.filtro).subscribe(dash => { 
            this.dash = dash;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});

    }

    public estadoDeCuenta(){
        window.open(this.apiService.baseUrl + '/api/cliente/estado-de-cuenta/' + this.route.snapshot.paramMap.get('id')!+ '?token=' + this.apiService.auth_token(), 'hola', 'width=800');
    }


}
