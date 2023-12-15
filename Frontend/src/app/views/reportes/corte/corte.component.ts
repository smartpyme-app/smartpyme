import { Component, OnInit } from '@angular/core';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';

@Component({
    selector: 'app-corte',
    templateUrl: './corte.component.html'
})
export class CorteComponent implements OnInit {

    public indicadores:any = {};
    public sucursales:any = [];
    public filtros:any = {};

    constructor(public apiService: ApiService, public alertService: AlertService) {}

    ngOnInit(){
        this.filtros.id_sucursal = null;
        this.filtros.fecha = this.apiService.date();

        this.apiService.getAll('sucursales/list').subscribe(sucursales => { 
            this.sucursales = sucursales;
        }, error => {this.alertService.error(error); });

        this.filtrar();
        
    }

    public descargar(){
        window.open(this.apiService.baseUrl + '/api/corte/documento/' + this.filtros.id_sucursal + '/' + this.filtros.fecha + '?token=' + this.apiService.auth_token(), 'Impresión', 'width=400');
    }

    public filtrar(){
        this.apiService.getAll('corte', this.filtros).subscribe(indicadores => { 
            this.indicadores = indicadores;
        }, error => {this.alertService.error(error); });
    }
    
}
