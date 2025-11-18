import { Component, OnInit, Input, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { BasePaginatedModalComponent, PaginatedResponse } from '@shared/base/base-paginated-modal.component';

import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';
import { ModalManagerService } from '../../../services/modal-manager.service';
import { TruncatePipe } from '../../../pipes/truncate.pipe';

@Component({
    selector: 'app-notificaciones',
    templateUrl: './notificaciones.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, TruncatePipe],
    
})

export class NotificacionesComponent extends BasePaginatedModalComponent implements OnInit {

    public notificacion:any = {};
    public cajas:any = [];
    public departamentos:any = [];
    public sucursales:any = [];
    public notificaciones: PaginatedResponse<any> = {} as PaginatedResponse;
    public paginacion = [];
    public override filtros:any = {};

    constructor(
        protected override apiService:ApiService,
        protected override alertService:AlertService,
        protected override modalManager: ModalManagerService
    ){
        super(apiService, alertService, modalManager);
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
        this.apiService.getAll('notificaciones', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe(notificaciones => { 
            this.notificaciones = notificaciones;
            this.loading = false;
            if(this.modalRef){
                this.closeModal();
            }
        }, error => {this.alertService.error(error); });
    }

    override openModal(template: TemplateRef<any>, notificacion:any) {
        this.notificacion = notificacion;
        this.notificacion.leido = true;
        this.setEstado(this.notificacion);

        super.openModal(template);
    }
    

    public onSubmit() {
        this.loading = true;
        this.apiService.store('notificacion', this.notificacion)
            .pipe(this.untilDestroyed())
            .subscribe(notificacion => {
            this.notificacion = notificacion;
            this.loading = false;
        },error => {this.alertService.error(error); this.loading = false; });

    }

    public setEstado(notificacion:any){
        this.apiService.store('notificacion', notificacion)
            .pipe(this.untilDestroyed())
            .subscribe(notificacion => { 
            // this.alertService.success('Actualizado');
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('notificacion/', id)
                .pipe(this.untilDestroyed())
                .subscribe(data => {
                for (let i = 0; i < this.notificaciones.data.length; i++) { 
                    if (this.notificaciones.data[i].id == data.id )
                        this.notificaciones.data.splice(i, 1);
                }
            }, error => {this.alertService.error(error); this.loading = false;});
                   
        }
    }

    // setPagination() ahora se hereda de BasePaginatedComponent



}

