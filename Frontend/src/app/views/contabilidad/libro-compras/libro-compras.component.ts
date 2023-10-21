import { Component, OnInit } from '@angular/core';

import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';

declare var $:any;

@Component({
  selector: 'app-libro-compras',
  templateUrl: './libro-compras.component.html',
})

export class LibroComprasComponent implements OnInit {

	public compras:any[] = [];
    public loading:boolean = false;
    public filtro:any = {};

    constructor(private apiService: ApiService, private alertService: AlertService){ }

	ngOnInit() {        

        this.filtro.inicio = this.apiService.date();
        this.filtro.fin = this.apiService.date();
        this.loadAll();
    }

    public loadAll() {
        this.loading = true;
        this.apiService.store('libro-compras', this.filtro).subscribe(compras => { 
            this.compras = compras;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

}
