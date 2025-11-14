import { Component, OnInit, Input, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { BasePaginatedComponent, PaginatedResponse } from '@shared/base/base-paginated.component';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';
import { TruncatePipe } from '../../../pipes/truncate.pipe';

@Component({
    selector: 'app-notificaciones',
    templateUrl: './notificaciones.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, TruncatePipe],
    
})

export class NotificacionesComponent extends BasePaginatedComponent implements OnInit {

    public notificacion:any = {};
    public cajas:any = [];
    public departamentos:any = [];
    public sucursales:any = [];
    public notificaciones: PaginatedResponse<any> = {} as PaginatedResponse;
    public paginacion = [];
    public override filtros:any = {};

    modalRef?: BsModalRef;

    constructor( apiService:ApiService, alertService:AlertService, private modalService: BsModalService ){
        super(apiService, alertService);
    }

    protected getPaginatedData(): PaginatedResponse | null {
        return this.notificaciones;
    }

    protected setPaginatedData(data: PaginatedResponse): void {
        this.notificaciones = data;
    }

	ngOnInit() {
        this.loadAll();
    }

    public loadAll() {
        this.filtros.categoria = '';
        this.filtros.tipo = '';
        this.filtros.leido = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'id';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;

        this.filtrarNotificaciones();
    }

    public filtrarNotificaciones(){
        this.loading = true;
        this.apiService.getAll('notificaciones', this.filtros).subscribe(notificaciones => { 
            this.notificaciones = notificaciones;
            this.loading = false;
            if(this.modalRef){
                this.modalRef.hide();
            }
        }, error => {this.alertService.error(error); });
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

    // setPagination() ahora se hereda de BasePaginatedComponent



}

