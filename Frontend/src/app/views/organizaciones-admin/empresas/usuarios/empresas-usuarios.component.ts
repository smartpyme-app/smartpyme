import { Component, OnInit, Input, TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { BaseCrudComponent } from '@shared/base/base-crud.component';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';

@Component({
    selector: 'app-empresas-usuarios',
    templateUrl: './empresas-usuarios.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, TooltipModule],
    changeDetection: ChangeDetectionStrategy.OnPush,
    
})

export class EmpresasUsuariosComponent extends BaseCrudComponent<any> implements OnInit {

    public usuario:any = {};
    public sucursales:any = [];
    public sucursalesList:any = [];
    public empresas:any = [];
    public usuarios:any = {};
    public paginacion = [];
    public filtrado:boolean = false;
    public showpassword:boolean = false;
    public showpassword2:boolean = false;
    public roles:any = [];

    constructor( 
        apiService:ApiService, 
        alertService:AlertService, 
        modalManager: ModalManagerService,
        private cdr: ChangeDetectorRef
    ){
        super(apiService, alertService, modalManager, {
            endpoint: 'admin-usuario',
            itemsProperty: 'usuarios',
            itemProperty: 'usuario',
            reloadAfterSave: true,
            reloadAfterDelete: false,
            messages: {
                created: 'El usuario fue añadido exitosamente.',
                updated: 'El usuario fue guardado exitosamente.',
                deleted: 'Usuario eliminado exitosamente.',
                createTitle: 'Usuario creado',
                updateTitle: 'Usuario guardado',
                deleteTitle: 'Usuario eliminado',
                deleteConfirm: '¿Desea eliminar el Registro?'
            }
        });
    }

    protected aplicarFiltros(): void {
        this.onFiltrar();
    }

	ngOnInit() {
        this.filtros.id_empresa = '';
        this.filtros.estado = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'id_empresa';
        this.filtros.direccion = 'asc';
        this.filtros.paginate = 10;

        this.loadAll();

        this.apiService.getAll('licencias/empresas/list').pipe(this.untilDestroyed()).subscribe({
            next: (empresas) => {
                this.empresas = empresas;
                this.cdr.markForCheck();
            },
            error: (error) => {
                this.alertService.error(error);
                this.cdr.markForCheck();
            }
        });
    }

    public override loadAll(){
        this.loading = true;
        this.cdr.markForCheck();        
        this.apiService.getAll('licencias/usuarios', this.filtros).pipe(this.untilDestroyed()).subscribe({
            next: (usuarios) => {
                this.usuarios = usuarios;
                this.usuarios.data.forEach((usuario:any) => {
                    usuario.rol_id = usuario.roles[0].id;
                    usuario.rol_name = usuario.roles[0].name;
                });
                this.loading = false;
                this.cdr.markForCheck();
            },
            error: (error) => {
                this.alertService.error(error);
                this.loading = false;
                this.cdr.markForCheck();
            }
        });

        this.apiService.getAll('roles').pipe(this.untilDestroyed()).subscribe({
            next: (roles) => {
                this.roles = roles;
                this.roles.forEach((rol:any) => {
                    rol.name = rol.name.split('_')
                                     .map((word: string) => word.charAt(0).toUpperCase() + word.slice(1))
                                     .join(' ');
                });
                this.cdr.markForCheck();
            },
            error: (error) => {
                this.alertService.error(error);
                this.cdr.markForCheck();
            }
        });
    }

    override openModal(template: TemplateRef<any>, usuario?: any) {
        this.usuario = usuario || {};

        this.apiService.getAll('sucursales/list').pipe(this.untilDestroyed()).subscribe({
            next: (sucursales) => {
                this.sucursalesList = sucursales;
                this.setSucursales();
                this.cdr.markForCheck();
            },
            error: (error) => {
                this.alertService.error(error);
                this.cdr.markForCheck();
            }
        });

        super.openLargeModal(template);
    }

    setSucursales(){
        this.sucursales = this.sucursalesList.filter((item:any) => item.id_empresa == this.usuario.id_empresa);
        this.usuario.id_sucursal = this.sucursales[0].id;
        console.log(this.sucursales);
    }

    public mostrarPassword(){
        this.showpassword = !this.showpassword;
    }  
    
    public mostrarPassword2(){
        this.showpassword2 = !this.showpassword2;
    }  

    public setEstado(usuario:any){
        this.apiService.store('admin-usuario', usuario).pipe(this.untilDestroyed()).subscribe({
            next: (usuario) => {
                if(usuario.enable == 1){
                    this.alertService.success('Usuario activado', 'El usuario fue activado exitosamente.');
                }else{
                    this.alertService.success('Usuario desactivado', 'El usuario fue desactivado exitosamente.');
                }
                this.cdr.markForCheck();
            },
            error: (error) => {
                this.alertService.error(error);
                this.loading = false;
                this.cdr.markForCheck();
            }
        });
    }

    public override delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('admin-usuario/', id).pipe(this.untilDestroyed()).subscribe({
                next: (data) => {
                    for (let i = 0; i < this.usuarios.data.length; i++) { 
                        if (this.usuarios.data[i].id == data.id )
                            this.usuarios.data.splice(i, 1);
                    }
                },
                error: (error) => {
                    this.alertService.error(error);
                    this.loading = false;
                }
            });
        }
    }

    onFiltrar(){
        this.loading = true;
        this.cdr.markForCheck();
        this.apiService.store('admin-usuarios/filtrar', this.filtros).pipe(this.untilDestroyed()).subscribe({
            next: (usuarios) => {
                this.usuarios = usuarios;
                this.loading = false;
                this.closeModal();
                this.cdr.markForCheck();
            },
            error: (error) => {
                this.alertService.error(error);
                this.loading = false;
                this.cdr.markForCheck();
            }
        });
    }
}
