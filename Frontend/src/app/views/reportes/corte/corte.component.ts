import { Component, OnInit, DestroyRef, inject, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
    selector: 'app-corte',
    templateUrl: './corte.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule],
    changeDetection: ChangeDetectionStrategy.OnPush,
    
})
export class CorteComponent implements OnInit {

    public usuario:any = {};
    public indicadores:any = {};
    public sucursales:any = [];
    public usuarios:any = [];
    public canales:any = [];
    public filtros:any = {};

    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

    constructor(public apiService: ApiService, public alertService: AlertService, private cdr: ChangeDetectorRef) {}

    ngOnInit(){
        this.usuario = this.apiService.auth_user();

        if(this.usuario.tipo != 'Ventas' && this.usuario.tipo != 'Ventas Limitado'){
            this.filtros.id_sucursal = '';
            this.filtros.id_usuario = '';
        }else{
            this.filtros.id_sucursal = this.apiService.auth_user().id_sucursal;
            this.filtros.id_usuario = this.apiService.auth_user().id;
        }
        this.filtros.id_canal = '';
        this.filtros.fecha = this.apiService.date();

        this.apiService.getAll('sucursales/list')
            .pipe(this.untilDestroyed())
            .subscribe(sucursales => { 
                this.sucursales = sucursales;
                if(this.filtros.id_sucursal){
                    this.sucursales = sucursales.filter((item:any) => item.id == this.filtros.id_sucursal);
                }
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); });

        this.apiService.getAll('usuarios/list')
            .pipe(this.untilDestroyed())
            .subscribe(usuarios => {
                this.usuarios = usuarios;
                // if(this.apiService.auth_user().tipo != 'Administrador' && this.apiService.auth_user().tipo != 'Supervisor'){
                //     this.usuarios = this.usuarios.filter((item:any) => item.id == this.apiService.auth_user().id );
                // }
                if((this.apiService.validateRole('super_admin', false) || this.apiService.validateRole('admin', false)) && this.apiService.validateRole('usuario_supervisor', false) ){
                    this.usuarios = this.usuarios.filter((item:any) => item.id == this.apiService.auth_user().id );
                }
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error);});

        this.apiService.getAll('canales/list').subscribe(canales => {
            this.canales = canales;
        }, error => {this.alertService.error(error);});

        this.filtrar();
        
    }

    public descargar(){
        window.open(this.apiService.baseUrl + '/api/corte/documento/' + (this.filtros.id_usuario ? this.filtros.id_usuario : null)  + '/' + (this.filtros.id_sucursal ? this.filtros.id_sucursal : null)  + '/' + this.filtros.fecha + '?token=' + this.apiService.auth_token(), 'Impresión', 'width=400');
    }

    public filtrar(){
        this.apiService.getAll('corte', this.filtros)
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
