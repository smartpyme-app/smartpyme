import { Component, OnInit, Input, TemplateRef } from '@angular/core';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-usuarios',
  templateUrl: './usuarios.component.html'
})

export class UsuariosComponent implements OnInit {

    public sucursales:any = [];
    public bodegas:any = [];
    public usuarios:any = [];
    public usuario:any = {};
    public paginacion = [];
    public loading:boolean = false;
    public saving:boolean = false;
    public filtrado:boolean = false;
    public usuarios_activos:any = 0;
    public filtros:any = {};
    public showpassword:boolean = false;
    public showpassword2:boolean = false;
    public authUser: any = {};



    modalRef?: BsModalRef;

    constructor( public apiService:ApiService, public alertService:AlertService,
        private modalService: BsModalService ){}

	ngOnInit() {
        // Recuperar filtros del localStorage si existen
        const savedFilters = localStorage.getItem('usuarios_filtros');
        if (savedFilters) {
            this.filtros = JSON.parse(savedFilters);
        } else {
            // Valores por defecto si no hay filtros guardados
            this.filtros.id_sucursal = '';
            this.filtros.estado = '';
            this.filtros.buscador = '';
            this.filtros.orden = 'name';
            this.filtros.direccion = 'desc';
            this.filtros.paginate = 30;
        }

        this.loadAll();

        this.apiService.getAll('sucursales/list').subscribe(sucursales => { 
            this.sucursales = sucursales;
        }, error => {this.alertService.error(error); });

        this.apiService.getAll('bodegas/list').subscribe(bodegas => { 
            this.bodegas = bodegas;
        }, error => {this.alertService.error(error); });

        this.usuarioLogueado();
    }

    public loadAll(){
        this.loading = true;
        if(!this.filtros.id_sucursal){
            this.filtros.id_sucursal = '';
        }      
        // Guardar filtros en localStorage
        localStorage.setItem('usuarios_filtros', JSON.stringify(this.filtros));
        
        this.apiService.getAll('usuarios', this.filtros).subscribe(usuarios => { 
            this.usuarios = usuarios;
            this.contarActivos();
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public contarActivos(){
        this.usuarios_activos = this.usuarios.data.filter((item:any) => item.enable == '1').length;
    }

    openModal(template: TemplateRef<any>, usuario:any) {
        this.alertService.modal = true;
        this.usuario = usuario;
        if (!this.usuario.id) {
            this.usuario.tipo = 'Administrador';
            this.usuario.id_sucursal = this.apiService.auth_user().id_sucursal;
            this.usuario.id_empresa = this.apiService.auth_user().id_empresa;
        }
        this.modalRef = this.modalService.show(template, { class: 'modal-lg', backdrop: 'static' });
    }
    
    public mostrarPassword(){
        this.showpassword = !this.showpassword;
    }  
    
    public mostrarPassword2(){
        this.showpassword2 = !this.showpassword2;
    }  

    public onSubmit() {
        this.saving = true;
        // Guardamos al usuario
        this.apiService.store('usuario', this.usuario).subscribe(usuario => {
            this.loadAll();
            this.saving = false;
            this.alertService.success('Usuario guardado', 'El usuario fue guardado exitosamente.');
            this.modalRef?.hide();
            this.alertService.modal = false;
        },error => {this.alertService.error(error); this.saving = false; });

    }

    public setEstado(usuario:any){
        this.apiService.store('usuario', usuario).subscribe(usuario => { 
            if(usuario.enable == '1'){
                this.alertService.success('Usuario activado', 'El usuario fue activado exitosamente.');
            }else{
                this.alertService.success('Usuario desactivado', 'El usuario fue desactivado exitosamente.');
            }
            this.contarActivos();
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('usuario/', id) .subscribe(data => {
                for (let i = 0; i < this.usuarios.data.length; i++) { 
                    if (this.usuarios.data[i].id == data.id )
                        this.usuarios.data.splice(i, 1);
                }
            }, error => {this.alertService.error(error); this.loading = false;});
                   
        }
    }

    selectSucursal(){
        this.usuario.id_bodega = this.usuario.id_sucursal;
    }


    onFiltrar(){
        this.loading = true;
        // Guardar filtros en localStorage antes de aplicarlos
        localStorage.setItem('usuarios_filtros', JSON.stringify(this.filtros));
        
        this.apiService.store('usuarios/filtrar', this.filtros).subscribe(usuarios => { 
            this.usuarios = usuarios;
            this.loading = false;;
            this.modalRef?.hide();
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public limpiarFiltros() {

        this.filtros = {
            id_sucursal: '',
            estado: '',
            buscador: '',
            orden: 'name',
            direccion: 'desc',
            paginate: 30
        };
        

        localStorage.removeItem('usuarios_filtros');
        

        this.loadAll();
    }

    public usuarioLogueado() {
      this.authUser = this.apiService.auth_user();
    }
  
}
