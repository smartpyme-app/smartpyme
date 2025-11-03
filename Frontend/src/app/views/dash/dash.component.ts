import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { AdminDashComponent } from './admin/admin-dash.component';
import { CajaDashComponent } from './caja/caja-dash.component';
import { VendedorDashComponent } from './vendedor/vendedor-dash.component';
import { OrganizacionesDashComponent } from './organizaciones/organizaciones-dash.component';

@Component({
    selector: 'app-dash',
    templateUrl: './dash.component.html',
    standalone: true,
    imports: [
        CommonModule, 
        RouterModule,
        AdminDashComponent,
        CajaDashComponent,
        VendedorDashComponent,
        OrganizacionesDashComponent
    ],
    
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

        // if(this.usuario.tipo == 'Ventas'){
        //     this.router.navigate(['/vendedor/ventas']);
        // }
        // if(this.usuario.tipo == 'Citas'){
        //     this.router.navigate(['/citas']);
        // }

        if(this.apiService.validateRole('usuario_ventas', true)){
            this.router.navigate(['/vendedor/ventas']);
        }

        if(this.apiService.validateRole('usuario_citas', true)){
            this.router.navigate(['/citas']);
        }

    }




}
