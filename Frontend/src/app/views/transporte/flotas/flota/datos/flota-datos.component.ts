import { Component, OnInit } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';

import { AlertService } from '../../../../../services/alert.service';
import { ApiService } from '../../../../../services/api.service';

import * as moment from 'moment';

@Component({
  selector: 'app-flota-datos',
  templateUrl: './flota-datos.component.html',
})
export class FlotaDatosComponent implements OnInit {

    public dash:any;
    public loading:boolean = false;

    public filtro:any = {};
    

    constructor( public apiService:ApiService, private alertService:AlertService, private route: ActivatedRoute ){}

    ngOnInit() {
        this.filtro.id = +this.route.snapshot.paramMap.get('id')!;
        if (this.filtro.id) {
            this.filtro.time = 'week';
            this.onFiltrar(this.filtro.time);
        }
    }

    onFiltrar($time:any){
        this.filtro.time = $time;        
        this.filtro.inicio = moment().startOf(this.filtro.time).format('YYYY-MM-DD');
        this.filtro.fin = moment().endOf(this.filtro.time).format('YYYY-MM-DD');

        this.loading = true;
        this.apiService.store('flota/datos', this.filtro).subscribe(dash => { 
            this.dash = dash;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});

    }


}
