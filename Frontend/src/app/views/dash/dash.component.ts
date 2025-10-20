import { Component, OnInit } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-dash',
  templateUrl: './dash.component.html'
})
export class DashComponent implements OnInit {

    public usuario:any;
    public saludo:any;

    constructor( 
        public apiService: ApiService, private alertService: AlertService,
        private router: Router
    ) { }

    ngOnInit() {
        this.usuario = this.apiService.auth_user();        
        this.saludo = this.apiService.saludar();

        if(this.usuario.tipo == 'Ventas' || this.usuario.tipo == 'Ventas Limitado'){
            this.router.navigate(['/vendedor/ventas']);    
        }
        if(this.usuario.tipo == 'Citas'){
            this.router.navigate(['/citas']);    
        }

    }




}
