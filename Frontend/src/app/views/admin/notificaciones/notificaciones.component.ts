import { Component, OnInit, Input, TemplateRef } from '@angular/core';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';

@Component({
  selector: 'app-notificaciones',
  templateUrl: './notificaciones.component.html'
})

export class NotificacionesComponent implements OnInit {

    public usuario:any = {};
    public cajas:any = [];
    public departamentos:any = [];
    public sucursales:any = [];
    public notificaciones:any = [];
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
        this.filtro.id_sucursal = '';
        this.filtro.tipo = '';
        this.filtro.estado = '';
        
        this.apiService.getAll('notificaciones').subscribe(notificaciones => { 
            this.notificaciones = notificaciones;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public search(){
        if(this.buscador && this.buscador.length > 1) {
            this.loading = true;
            this.apiService.read('notificaciones/buscar/', this.buscador).subscribe(notificaciones => { 
                this.notificaciones = notificaciones;
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
                this.notificaciones.data.unshift(usuario);
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
                for (let i = 0; i < this.notificaciones.data.length; i++) { 
                    if (this.notificaciones.data[i].id == data.id )
                        this.notificaciones.data.splice(i, 1);
                }
            }, error => {this.alertService.error(error); this.loading = false;});
                   
        }
    }


    onFiltrar(){
        this.loading = true;
        this.apiService.store('notificaciones/filtrar', this.filtro).subscribe(notificaciones => { 
            this.notificaciones = notificaciones;
            this.loading = false; this.filtrado = true;
            this.modalRef?.hide();
        }, error => {this.alertService.error(error); this.loading = false;});

    }


}

