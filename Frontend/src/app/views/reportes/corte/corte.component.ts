import { Component, OnInit } from '@angular/core';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';

@Component({
    selector: 'app-corte',
    templateUrl: './corte.component.html'
})
export class CorteComponent implements OnInit {

    public usuario:any = {};
    public indicadores:any = {};
    public sucursales:any = [];
    public usuarios:any = [];
    public filtros:any = {};

    constructor(public apiService: ApiService, public alertService: AlertService) {}

    ngOnInit(){
        this.usuario = this.apiService.auth_user();

        if(this.usuario.tipo == 'Administrador'){
            this.filtros.id_sucursal = '';
            this.filtros.id_usuario = '';
        }else{
            this.filtros.id_sucursal = this.apiService.auth_user().id_sucursal;
            this.filtros.id_usuario = this.apiService.auth_user().id;
        }
        this.filtros.fecha = this.apiService.date();

        this.apiService.getAll('sucursales/list').subscribe(sucursales => { 
            this.sucursales = sucursales;
            if(this.filtros.id_sucursal){
                this.sucursales = sucursales.filter((item:any) => item.id == this.filtros.id_sucursal);
            }
        }, error => {this.alertService.error(error); });

        this.apiService.getAll('usuarios/list').subscribe(usuarios => {
            this.usuarios = usuarios;
            if(this.apiService.auth_user().tipo != 'Administrador' && this.apiService.auth_user().tipo != 'Supervisor'){
                this.usuarios = this.usuarios.filter((item:any) => item.id == this.apiService.auth_user().id );
            }
        }, error => {this.alertService.error(error);});


        this.filtrar();
        
    }

    public descargar(){
        window.open(this.apiService.baseUrl + '/api/corte/documento/' + (this.filtros.id_usuario ? this.filtros.id_usuario : null)  + '/' + (this.filtros.id_sucursal ? this.filtros.id_sucursal : null)  + '/' + this.filtros.fecha + '?token=' + this.apiService.auth_token(), 'Impresión', 'width=400');
    }

    public filtrar(){
        this.apiService.getAll('corte', this.filtros).subscribe(indicadores => { 
            this.indicadores = indicadores;
        }, error => {this.alertService.error(error); });
    }
    
}
