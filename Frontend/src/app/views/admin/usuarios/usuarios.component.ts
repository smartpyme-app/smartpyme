import { Component, OnInit, Input, TemplateRef } from '@angular/core';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';

@Component({
  selector: 'app-usuarios',
  templateUrl: './usuarios.component.html'
})

export class UsuariosComponent implements OnInit {

    public usuario:any = {};
    public cajas:any = [];
    public departamentos:any = [];
    public sucursales:any = [];
    public usuarios:any = [];
    public paginacion = [];
    public loading:boolean = false;
    public filtrado:boolean = false;
    public filtro:any = {};
    public buscador:any = '';

    modalRef?: BsModalRef;

    constructor( public apiService:ApiService, private alertService:AlertService, private modalService: BsModalService ){}

	ngOnInit() {
        this.loadAll();
    }

    public loadAll(){
        this.loading = true;
        this.apiService.getAll('usuarios').subscribe(usuarios => { 
            this.usuarios = usuarios;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public search(){
        if(this.buscador && this.buscador.length > 1) {
            this.loading = true;
            this.apiService.read('usuarios/buscar/', this.buscador).subscribe(usuarios => { 
                this.usuarios = usuarios;
                this.loading = false;this.filtrado = true;
            }, error => {this.alertService.error(error); this.loading = false;this.filtrado = false; });
        }
    }

    openModal(template: TemplateRef<any>, usuario:any) {
        this.apiService.getAll('cajas').subscribe(cajas => { 
            this.cajas = cajas;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
        this.apiService.getAll('departamentos').subscribe(departamentos => { 
            this.departamentos = departamentos;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
        this.apiService.getAll('sucursales').subscribe(sucursales => { 
            this.sucursales = sucursales;
        }, error => {this.alertService.error(error); });
        this.usuario = usuario;
        if (!this.usuario.id) {
            this.usuario.tipo = 'Vendedor';
            this.usuario.sucursal_id = this.apiService.auth_user().sucursal_id;
            this.usuario.activo = true;
            this.usuario.empleado = true;
        }
        this.modalRef = this.modalService.show(template);
    }
    

    public onSubmit() {
        this.loading = true;
        // Guardamos al usuario
        this.apiService.store('usuario', this.usuario).subscribe(usuario => {
            if (!this.usuario.id) {
                this.usuarios.data.unshift(usuario);
            }
            this.usuario = usuario;
            this.loading = false;
            this.alertService.success("Usuario guardado");
            this.modalRef?.hide();
        },error => {this.alertService.error(error); this.loading = false; });

    }

    public setEstado(usuario:any){
        this.apiService.store('usuario', usuario).subscribe(usuario => { 
            this.alertService.success('Actualizado');
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

    // Filtros

    openFilter(template: TemplateRef<any>) {     

        if(!this.filtrado) {
            this.filtro.sucursal_id = '';
            this.filtro.tipo = '';
        }
        if(!this.sucursales.data){
            this.apiService.getAll('sucursales').subscribe(sucursales => { 
                this.sucursales = sucursales;
            }, error => {this.alertService.error(error); });
        }
        this.modalRef = this.modalService.show(template);
    }

    onFiltrar(){
        this.loading = true;
        this.apiService.store('usuarios/filtrar', this.filtro).subscribe(usuarios => { 
            this.usuarios = usuarios;
            this.loading = false; this.filtrado = true;
            this.modalRef?.hide();
        }, error => {this.alertService.error(error); this.loading = false;});

    }


}

