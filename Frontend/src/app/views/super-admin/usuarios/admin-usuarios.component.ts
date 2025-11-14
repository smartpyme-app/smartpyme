import { Component, OnInit, Input, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { BasePaginatedComponent, PaginatedResponse } from '@shared/base/base-paginated.component';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
    selector: 'app-admin-usuarios',
    templateUrl: './admin-usuarios.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule],
    
})

export class AdminUsuariosComponent extends BasePaginatedComponent implements OnInit {

    public usuario:any = {};
    public sucursales:any = [];
    public bodegas:any = [];
    public sucursalesList:any = [];
    public empresas:any = [];
    public usuarios: PaginatedResponse<any> = {} as PaginatedResponse;
    public roles:any = [];
    public paginacion = [];
    public saving:boolean = false;
    public filtrado:boolean = false;
    public override filtros:any = {};
    public showpassword:boolean = false;
    public showpassword2:boolean = false;

    modalRef?: BsModalRef;

    constructor( apiService:ApiService, alertService:AlertService, private modalService: BsModalService ){
        super(apiService, alertService);
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
        this.filtros.orden = 'id';
        this.filtros.direccion = 'asc';
        this.filtros.paginate = 10;

        this.loadAll();

        this.apiService.getAll('empresas/list').subscribe(empresas => { 
            this.empresas = empresas;
        }, error => {this.alertService.error(error); });
    }

    public loadAll(){
        this.loading = true;        
        this.apiService.getAll('admin-usuarios', this.filtros).subscribe(usuarios => { 
            this.usuarios = usuarios;
            this.usuarios.data.forEach((usuario:any) => {
                usuario.rol_name = usuario.roles[0].name;
                usuario.rol_id = usuario.roles[0].id;
            });
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});

        this.apiService.getAll('roles').subscribe(roles => { 
            this.roles = roles;
            this.roles.forEach((rol:any) => {
                rol.name = rol.name.split('_')
                                 .map((word: string) => word.charAt(0).toUpperCase() + word.slice(1))
                                 .join(' ');
            });
        }, error => {this.alertService.error(error); });
    }


    openModal(template: TemplateRef<any>, usuario:any) {
        this.usuario = usuario;
        
        if (!this.usuario.id) {
            // this.usuario.tipo = 'Administrador';
            this.usuario.rol_id = 2;
            // this.usuario.id_sucursal = this.apiService.auth_user().id_sucursal;
            // this.usuario.id_empresa = this.apiService.auth_user().id_empresa;
        }

        this.apiService.getAll('sucursales/list').subscribe(sucursales => {
            this.sucursalesList = sucursales;
            this.setSucursales();
        }, error => {this.alertService.error(error); });

        this.apiService.getAll('bodegas/list').subscribe(bodegas => {
            this.bodegas = bodegas;
        }, error => {this.alertService.error(error); });

        this.modalRef = this.modalService.show(template, { class: 'modal-lg', backdrop: 'static' });
    }

    setSucursales(){
        this.sucursales = this.sucursalesList.filter((item:any) => item.id_empresa == this.usuario.id_empresa);
        this.usuario.id_sucursal = this.sucursales[0].id;
        console.log(this.sucursales);
    }

    selectSucursal(){
        this.usuario.id_bodega = this.bodegas[0].id;
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
        this.apiService.store('admin-usuario', this.usuario).subscribe(usuario => {
            this.loadAll();
            this.saving = false;
            if(!this.usuario.id){
                this.alertService.success('Usuario creado', 'El usuario fue añadido exitosamente.');
            }else{
                this.alertService.success('Usuario guardado', 'El usuario fue guardado exitosamente.');
            }
            this.modalRef?.hide();
        },error => {this.alertService.error(error); this.saving = false; });

    }

    public setEstado(usuario:any){
        this.apiService.store('admin-usuario', usuario).subscribe(usuario => { 
            if(usuario.enable == 1){
                this.alertService.success('Usuario activado', 'El usuario fue activado exitosamente.');
            }else{
                this.alertService.success('Usuario desactivado', 'El usuario fue desactivado exitosamente.');
            }
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('admin-usuario/', id) .subscribe(data => {
                for (let i = 0; i < this.usuarios.data.length; i++) { 
                    if (this.usuarios.data[i].id == data.id )
                        this.usuarios.data.splice(i, 1);
                }
            }, error => {this.alertService.error(error); this.loading = false;});
                   
        }
    }


    onFiltrar(){
        this.loading = true;
        this.apiService.store('admin-usuarios/filtrar', this.filtros).subscribe(usuarios => { 
            this.usuarios = usuarios;
            this.loading = false;;
            this.modalRef?.hide();
        }, error => {this.alertService.error(error); this.loading = false;});

    }


}

