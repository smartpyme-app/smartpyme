import { Component, OnInit } from '@angular/core';

import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';

declare var $:any;

@Component({
  selector: 'app-galonaje',
  templateUrl: './galonaje.component.html',
})

export class GalonajeComponent implements OnInit {

	public galonajes:any[] = [];
    public loading:boolean = false;
    public filtro:any = {};

    constructor(private apiService: ApiService, private alertService: AlertService){ }

    ngOnInit() {     

        this.filtro.inicio = this.apiService.date();
        this.filtro.fin = this.apiService.date();
        this.filtro.tipo_documento = '';
        this.loadAll();
    }

    public loadAll() {
        this.loading = true;
        this.apiService.store('galonaje', this.filtro).subscribe(galonajes => { 
            this.galonajes = galonajes;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});

    }


}
