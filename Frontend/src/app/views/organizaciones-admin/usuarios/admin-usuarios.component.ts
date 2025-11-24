import { Component, OnInit, Input, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { BaseCrudComponent } from '@shared/base/base-crud.component';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';

@Component({
    selector: 'app-admin-usuarios',
    templateUrl: './admin-usuarios.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule],
    
})

export class AdminUsuariosComponent extends BaseCrudComponent<any> implements OnInit {

    public usuario:any = {};
    public sucursales:any = [];
    public sucursalesList:any = [];
    public empresas:any = [];
    public usuarios:any = {};
    public roles:any = [];
    public paginacion = [];
    public filtrado:boolean = false;
    public showpassword:boolean = false;
    public showpassword2:boolean = false;

    constructor( 
        apiService:ApiService, 
        alertService:AlertService, 
        modalManager: ModalManagerService
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
        this.loadAll();
    }

	ngOnInit() {
        this.filtros.id_empresa = '';
        this.filtros.estado = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'id';
        this.filtros.direccion = 'asc';
        this.filtros.paginate = 10;

        this.loadAll();

        this.apiService.getAll('empresas/list').pipe(this.untilDestroyed()).subscribe({
            next: (empresas) => {
                this.empresas = empresas;
            },
            error: (error) => {
                this.alertService.error(error);
            }
        });
    }

    public override loadAll(){
        this.loading = true;        
        this.apiService.getAll('admin-usuarios', this.filtros).pipe(this.untilDestroyed()).subscribe({
            next: (usuarios) => {
                this.usuarios = usuarios;
                this.usuarios.data.forEach((usuario:any) => {
                    usuario.rol_name = usuario.roles[0].name;
                    usuario.rol_id = usuario.roles[0].id;
                });
                this.loading = false;
            },
            error: (error) => {
                this.alertService.error(error);
                this.loading = false;
            }
        });

        this.apiService.getAll('roles').pipe(this.untilDestroyed()).subscribe({
            next: (roles) => {
                this.roles = roles;
            },
            error: (error) => {
                this.alertService.error(error);
            }
        });
    }

    override openModal(template: TemplateRef<any>, usuario?: any) {
        this.usuario = usuario || {};
        
        if (!this.usuario.id) {
           // this.usuario.tipo = 'Administrador';
            this.usuario.rol_id = 2;
            this.usuario.id_sucursal = this.apiService.auth_user().id_sucursal;
            this.usuario.id_empresa = this.apiService.auth_user().id_empresa;
        }

        this.apiService.getAll('sucursales/list').pipe(this.untilDestroyed()).subscribe({
            next: (sucursales) => {
                this.sucursalesList = sucursales;
                this.setSucursales();
            },
            error: (error) => {
                this.alertService.error(error);
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
            },
            error: (error) => {
                this.alertService.error(error);
                this.loading = false;
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
}
