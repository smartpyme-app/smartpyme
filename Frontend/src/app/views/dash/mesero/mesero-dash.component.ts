import { Component, OnInit } from '@angular/core';

import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';


@Component({
  selector: 'app-mesero-dash',
  templateUrl: './mesero-dash.component.html'
})
export class MeseroDashComponent implements OnInit {

    public dash:any = {};
    public loading:boolean = false;
    public dashResfresh:any;

    constructor( 
        private apiService: ApiService, private alertService: AlertService
    ) { }


    ngOnInit() {
        this.loading = true;
        this.apiService.getAll('dash/mesero').subscribe(dash => {
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
        this.apiService.getAll('dash/mesero').subscribe(dash => {
            this.dash = dash;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    ngOnDestroy(){
        clearInterval(this.dashResfresh);

    }


}
