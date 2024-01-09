import { Component, OnInit, Input, TemplateRef } from '@angular/core';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';

@Component({
  selector: 'app-notificaciones',
  templateUrl: './notificaciones.component.html'
})

export class NotificacionesComponent implements OnInit {

    public notificacion:any = {};
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

    openModal(template: TemplateRef<any>, notificacion:any) {
        this.notificacion = notificacion;
        this.notificacion.leido = true;
        this.setEstado(this.notificacion);

        this.modalRef = this.modalService.show(template);
    }
    

    public onSubmit() {
        this.loading = true;
        this.apiService.store('notificacion', this.notificacion).subscribe(notificacion => {
            this.notificacion = notificacion;
            this.loading = false;
        },error => {this.alertService.error(error); this.loading = false; });

    }

    public setEstado(notificacion:any){
        this.apiService.store('notificacion', notificacion).subscribe(notificacion => { 
            // this.alertService.success('Actualizado');
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('notificacion/', id) .subscribe(data => {
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

