import { Component, OnInit, DestroyRef, inject, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { CurrencyPipe } from '@pipes/currency-format.pipe';
import { ConsolidadoEstilosSalonComponent } from '@shared/parts/consolidado-estilos-salon/consolidado-estilos-salon.component';

@Component({
    selector: 'app-corte',
    templateUrl: './corte.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, CurrencyPipe, ConsolidadoEstilosSalonComponent],
    changeDetection: ChangeDetectionStrategy.OnPush,
    
})
export class CorteComponent implements OnInit {

    public usuario:any = {};
    public indicadores:any = {};
    public sucursales:any = [];
    public bodegas:any = [];
    public usuarios:any = [];
    public canales:any = [];
    public filtros:any = {};

    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

    constructor(public apiService: ApiService, public alertService: AlertService, private cdr: ChangeDetectorRef) {}

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

        this.apiService.getAll('sucursales/list')
            .pipe(this.untilDestroyed())
            .subscribe(sucursales => { 
                this.sucursales = sucursales;
                if(this.filtros.id_sucursal){
                    this.sucursales = sucursales.filter((item:any) => item.id == this.filtros.id_sucursal);
                }
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); });

        this.apiService.getAll('bodegas/list')
            .pipe(this.untilDestroyed())
            .subscribe(bodegas => {
                this.bodegas = bodegas;
                if(this.esUsuarioVentas()){
                    this.bodegas = bodegas.filter((item:any) => item.id == this.usuario.id_bodega);
                }
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); });

        this.apiService.getAll('usuarios/list')
            .pipe(this.untilDestroyed())
            .subscribe(usuarios => {
                this.usuarios = usuarios;
                if((this.apiService.validateRole('super_admin', false) || this.apiService.validateRole('admin', false)) && this.apiService.validateRole('usuario_supervisor', false) ){
                    this.usuarios = this.usuarios.filter((item:any) => item.id == this.apiService.auth_user().id );
                }
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error);});

        this.apiService.getAll('canales/list')
            .pipe(this.untilDestroyed())
            .subscribe(canales => {
                this.canales = canales;
                this.cdr.markForCheck();
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
            this.cdr.markForCheck();
            return;
        }
        this.apiService.getAll('corte', this.paramsCorte())
            .pipe(this.untilDestroyed())
            .subscribe(indicadores => { 
                this.indicadores = indicadores;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); });
    }

    public onUsuarioClear(){
        this.filtros.id_usuario = '';
        this.filtrar();
        this.cdr.markForCheck();
    }

}
