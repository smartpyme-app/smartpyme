import { Component, OnInit, Input, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { BasePaginatedModalComponent, PaginatedResponse } from '@shared/base/base-paginated-modal.component';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';

@Component({
    selector: 'app-empresas-usuarios',
    templateUrl: './empresas-usuarios.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule],
    
})

export class EmpresasUsuariosComponent extends BasePaginatedModalComponent implements OnInit {

    public usuario:any = {};
    public sucursales:any = [];
    public sucursalesList:any = [];
    public empresas:any = [];
    public usuarios: PaginatedResponse<any> = {} as PaginatedResponse;
    public paginacion = [];
    public filtrado:boolean = false;
    public override filtros:any = {};
    public showpassword:boolean = false;
    public showpassword2:boolean = false;
    public roles:any = [];

    constructor( 
        apiService:ApiService, 
        alertService:AlertService, 
        modalManager: ModalManagerService
    ){
        super(apiService, alertService, modalManager);
    }

    protected getPaginatedData(): PaginatedResponse | null {
        return this.usuarios;
    }

    protected setPaginatedData(data: PaginatedResponse): void {
        this.usuarios = data;
    }

	ngOnInit() {
        this.filtros.id_empresa = '';
        this.filtros.estado = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'id_empresa';
        this.filtros.direccion = 'asc';
        this.filtros.paginate = 10;

        this.loadAll();

        this.apiService.getAll('licencias/empresas/list').pipe(this.untilDestroyed()).subscribe(empresas => { 
            this.empresas = empresas;
        }, error => {this.alertService.error(error); });
    }

    public loadAll(){
        this.loading = true;        
        this.apiService.getAll('licencias/usuarios', this.filtros).pipe(this.untilDestroyed()).subscribe(usuarios => { 
            this.usuarios = usuarios;
            this.usuarios.data.forEach((usuario:any) => {
                usuario.rol_id = usuario.roles[0].id;
                usuario.rol_name = usuario.roles[0].name;
            });
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});

        this.apiService.getAll('roles').pipe(this.untilDestroyed()).subscribe(roles => { 
            this.roles = roles;
            this.roles.forEach((rol:any) => {
                rol.name = rol.name.split('_')
                                 .map((word: string) => word.charAt(0).toUpperCase() + word.slice(1))
                                 .join(' ');
            });

        }, error => {this.alertService.error(error); });
    }


    override openModal(template: TemplateRef<any>, usuario:any) {
        this.usuario = usuario;

        this.apiService.getAll('sucursales/list').pipe(this.untilDestroyed()).subscribe(sucursales => {
            this.sucursalesList = sucursales;
            this.setSucursales();
        }, error => {this.alertService.error(error); });

        super.openLargeModal(template);
    }

    setSucursales(){
        this.sucursales = this.sucursalesList.filter((item:any) => item.id_empresa == this.usuario.id_empresa);
        this.usuario.id_sucursal = this.sucursales[0].id;
        console.log(this.sucursales);
    }

    // setPagination() ahora se hereda de BasePaginatedComponent
    
    public mostrarPassword(){
        this.showpassword = !this.showpassword;
    }  
    
    public mostrarPassword2(){
        this.showpassword2 = !this.showpassword2;
    }  

    public onSubmit() {
        this.saving = true;
        // Guardamos al usuario
        this.apiService.store('admin-usuario', this.usuario).pipe(this.untilDestroyed()).subscribe(usuario => {
            this.loadAll();
            this.saving = false;
            if(!this.usuario.id){
                this.alertService.success('Usuario creado', 'El usuario fue añadido exitosamente.');
            }else{
                this.alertService.success('Usuario guardado', 'El usuario fue guardado exitosamente.');
            }
            this.closeModal();
        },error => {this.alertService.error(error); this.saving = false; });

    }

    public setEstado(usuario:any){
        this.apiService.store('admin-usuario', usuario).pipe(this.untilDestroyed()).subscribe(usuario => { 
            if(usuario.enable == 1){
                this.alertService.success('Usuario activado', 'El usuario fue activado exitosamente.');
            }else{
                this.alertService.success('Usuario desactivado', 'El usuario fue desactivado exitosamente.');
            }
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('admin-usuario/', id).pipe(this.untilDestroyed()).subscribe(data => {
                for (let i = 0; i < this.usuarios.data.length; i++) { 
                    if (this.usuarios.data[i].id == data.id )
                        this.usuarios.data.splice(i, 1);
                }
            }, error => {this.alertService.error(error); this.loading = false;});
                   
        }
    }


    onFiltrar(){
        this.loading = true;
        this.apiService.store('admin-usuarios/filtrar', this.filtros).pipe(this.untilDestroyed()).subscribe(usuarios => { 
            this.usuarios = usuarios;
            this.loading = false;;
            this.closeModal();
        }, error => {this.alertService.error(error); this.loading = false;});

    }


}

