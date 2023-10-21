import { Component, OnInit } from '@angular/core';

import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';

declare var $:any;

@Component({
  selector: 'app-libro-iva',
  templateUrl: './libro-iva.component.html',
})

export class LibroIvaComponent implements OnInit {

	public ivas:any[] = [];
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
        this.apiService.store('libro-iva', this.filtro).subscribe(ivas => { 
            this.ivas = ivas;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

}
