import { Component, OnInit } from '@angular/core';

import { AlertService } from '../../services/alert.service';
import { ApiService } from '../../services/api.service';

@Component({
  selector: 'app-dash',
  templateUrl: './dash.component.html'
})
export class DashComponent implements OnInit {

    public usuario:any;
    public saludo:any;

    constructor( 
        private apiService: ApiService, private alertService: AlertService
    ) { }

    ngOnInit() {
        this.usuario = this.apiService.auth_user();        
        this.saludo = this.apiService.saludar();        
    }

}
