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
    public bodegas:any = [];
    public usuarios:any = [];
    public canales:any = [];
    public filtros:any = {};

    constructor(public apiService: ApiService, public alertService: AlertService) {}

    ngOnInit(){
        this.usuario = this.apiService.auth_user();

        this.filtros.criterio = 'sucursal';
        this.filtros.id_bodega = '';
        this.filtros.id_canal = '';
        this.filtros.fecha = this.apiService.date();

        if(this.esUsuarioVentas()){
            this.filtros.id_sucursal = this.usuario.id_sucursal;
            this.filtros.id_usuario = this.usuario.id;
        }else{
            this.filtros.id_sucursal = '';
            this.filtros.id_usuario = '';
        }

        this.apiService.getAll('sucursales/list').subscribe(sucursales => {
            this.sucursales = sucursales;
            if(this.filtros.id_sucursal){
                this.sucursales = sucursales.filter((item:any) => item.id == this.filtros.id_sucursal);
            }
        }, error => {this.alertService.error(error); });

        this.apiService.getAll('bodegas/list').subscribe(bodegas => {
            this.bodegas = bodegas;
            if(this.esUsuarioVentas()){
                this.bodegas = bodegas.filter((item:any) => item.id == this.usuario.id_bodega);
            }
        }, error => {this.alertService.error(error); });

        this.apiService.getAll('usuarios/list').subscribe(usuarios => {
            this.usuarios = usuarios;
            if(this.apiService.auth_user().tipo != 'Administrador' && this.apiService.auth_user().tipo != 'Supervisor'){
                this.usuarios = this.usuarios.filter((item:any) => item.id == this.apiService.auth_user().id );
            }
        }, error => {this.alertService.error(error);});

        this.apiService.getAll('canales/list').subscribe(canales => {
            this.canales = canales;
        }, error => {this.alertService.error(error);});

        this.filtrar();
    }

    public esUsuarioVentas(): boolean {
        return this.usuario.tipo == 'Ventas' || this.usuario.tipo == 'Ventas Limitado';
    }

    public onCriterioChange(){
        if(this.filtros.criterio === 'bodega'){
            this.filtros.id_sucursal = '';
            if(this.esUsuarioVentas()){
                if(!this.usuario.id_bodega){
                    this.filtros.id_bodega = '';
                    this.alertService.warning('Cierre de caja', 'No tienes una bodega asignada para consultar el cierre por bodega.');
                    return;
                }
                this.filtros.id_bodega = this.usuario.id_bodega;
            }else{
                this.filtros.id_bodega = this.usuario.id_bodega || '';
            }
        }else{
            this.filtros.id_bodega = '';
            if(this.esUsuarioVentas()){
                this.filtros.id_sucursal = this.usuario.id_sucursal;
            }else{
                this.filtros.id_sucursal = '';
            }
        }
        this.filtrar();
    }

    public paramsCorte(): any {
        const params:any = {
            fecha: this.filtros.fecha,
            id_usuario: this.filtros.id_usuario,
            id_canal: this.filtros.id_canal
        };
        if(this.filtros.criterio === 'bodega'){
            params.id_bodega = this.filtros.id_bodega;
        }else{
            params.id_sucursal = this.filtros.id_sucursal;
        }
        return params;
    }

    public descargar(){
        if(this.filtros.criterio === 'bodega' && !this.filtros.id_bodega){
            this.alertService.warning('Cierre de caja', 'Seleccione una bodega para descargar el cierre.');
            return;
        }
        const idUsuario = this.filtros.id_usuario ? this.filtros.id_usuario : null;
        const idSucursal = this.filtros.criterio === 'sucursal' && this.filtros.id_sucursal ? this.filtros.id_sucursal : null;
        let url = this.apiService.baseUrl + '/api/corte/documento/' + idUsuario + '/' + idSucursal + '/' + this.filtros.fecha + '?token=' + this.apiService.auth_token();
        if(this.filtros.criterio === 'bodega' && this.filtros.id_bodega){
            url += '&id_bodega=' + this.filtros.id_bodega;
        }
        window.open(url, 'Impresión', 'width=400');
    }

    public filtrar(){
        if(this.filtros.criterio === 'bodega' && !this.filtros.id_bodega){
            this.indicadores = {};
            return;
        }
        this.apiService.getAll('corte', this.paramsCorte()).subscribe(indicadores => {
            this.indicadores = indicadores;
        }, error => {this.alertService.error(error); });
    }

    public onUsuarioClear(){
        this.filtros.id_usuario = '';
        this.filtrar();
    }

}
