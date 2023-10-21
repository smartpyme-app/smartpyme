import { Component, OnInit } from '@angular/core';

import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';

declare var $: any;

@Component({
  selector: 'app-admin-dash',
  templateUrl: './admin-dash.component.html'
})
export class AdminDashComponent implements OnInit {

    public dash:any = {};
    public sucursales:any[] = [];
    public filtro:any = {};
    public saludo:string = '';
    public usuario:any = {};
    public loading:boolean = false;

    constructor( 
        private apiService: ApiService, private alertService: AlertService
    ) { }


    ngOnInit() {

        this.saludo  = this.apiService.saludar();
        this.usuario  = this.apiService.auth_user();
        this.filtro.inicio  = this.apiService.date();
        this.filtro.fin     = this.apiService.date();
        this.filtro.sucursal_id = '';
        
        this.loadAll();

        this.apiService.getAll('sucursales').subscribe(sucursales => { 
            this.sucursales = sucursales;
        }, error => {this.alertService.error(error); });


    }
    
    public loadAll(){
        this.loading = true;
        this.apiService.store('dash', this.filtro).subscribe(dash => {
            this.dash = dash;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }


}
