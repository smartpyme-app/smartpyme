import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { DomSanitizer, SafeHtml } from '@angular/platform-browser';

@Component({
    selector: 'app-reportes',
    templateUrl: './reportes.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule],
    
})
export class ReportesComponent implements OnInit {

    public usuario:any = {};
    public indicadores:any = {};
    public dashboards:any = [];
    public filtros:any = {};

    constructor(public apiService: ApiService, public alertService: AlertService, 
        private sanitizer: DomSanitizer) {}

    ngOnInit(){
        this.usuario = this.apiService.auth_user();
        this.loadAll();   
    }

    loadAll(){
        this.filtros.id_empresa = '';
        this.filtros.tipo = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'id';
        this.filtros.direccion = 'asc';
        this.filtros.paginate = 10;

        this.apiService.getAll('dashboards', this.filtros).subscribe(dashboards => { 
            this.dashboards = dashboards;
            for (let i = 0; i < this.dashboards['data'].length; i++) { 
                this.dashboards['data'][i].codigo_embed = this.sanitizer.bypassSecurityTrustHtml(this.dashboards['data'][i].codigo_embed);
            }
        }, error => {this.alertService.error(error); });
    }
    
}
