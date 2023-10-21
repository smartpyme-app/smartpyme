import { Component, OnInit } from '@angular/core';

import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';


@Component({
  selector: 'app-vendedor-dash',
  templateUrl: './vendedor-dash.component.html'
})
export class VendedorDashComponent implements OnInit {

    public dash:any = {};
    public loading:boolean = false;
    public dashResfresh:any;

    constructor( 
        public apiService: ApiService, private alertService: AlertService
    ) { }


    ngOnInit() {
        this.loading = true;
        this.apiService.getAll('dash/vendedor').subscribe(dash => {
            this.dash = dash;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false; });
        
        this.dashResfresh = setInterval(()=> {
            if (!this.loading)
                this.loadAll();
        }, 25000);
    }
    
    public loadAll(){
        this.loading = true;
        this.apiService.getAll('dash/vendedor').subscribe(dash => {
            this.dash = dash;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    ngOnDestroy(){
        clearInterval(this.dashResfresh);

    }


}
