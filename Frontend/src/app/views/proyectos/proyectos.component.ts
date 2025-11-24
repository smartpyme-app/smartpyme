import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { NgSelectModule } from '@ng-select/ng-select';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseCrudComponent } from '@shared/base/base-crud.component';

import * as moment from 'moment';
import Swal from 'sweetalert2';

@Component({
    selector: 'app-proyectos',
    templateUrl: './proyectos.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, PaginationComponent, PopoverModule, TooltipModule],

})

export class ProyectosComponent extends BaseCrudComponent<any> implements OnInit {

    public proyectos:any = {};
    public sucursales:any = [];
    public clientes:any = [];
    public usuarios:any = [];
    public proyecto:any = {};

    constructor(
        apiService: ApiService, 
        alertService: AlertService,
        modalManager: ModalManagerService
    ){
        super(apiService, alertService, modalManager, {
            endpoint: 'proyecto',
            itemsProperty: 'proyectos',
            itemProperty: 'proyecto',
            reloadAfterSave: false,
            reloadAfterDelete: false,
            messages: {
                created: 'El proyecto fue añadido exitosamente.',
                updated: 'El proyecto fue guardado exitosamente.',
                createTitle: 'proyecto creada',
                updateTitle: 'proyecto guardado'
            },
            afterSave: (item, isNew) => {
                if (isNew) {
                    this.loadAll();
                }
                this.proyecto = {};
            }
        });
    }

    protected aplicarFiltros(): void {
        this.filtrarProyectos();
    }

    ngOnInit() {
        this.apiService.getAll('clientes/list')
            .pipe(this.untilDestroyed())
            .subscribe(clientes => { 
                this.clientes = clientes;
            }, error => {this.alertService.error(error); });

        this.loadAll();
    }

    public setOrden(columna: string) {
        if (this.filtros.orden === columna) {
          this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
        } else {
          this.filtros.orden = columna;
          this.filtros.direccion = 'asc';
        }

        this.filtrarProyectos();
    }

    public override loadAll() {
        this.filtros.id_cliente = '';
        this.filtros.id_sucursal = '';
        this.filtros.id_asesor = '';
        this.filtros.id_usuario = '';
        this.filtros.tipo = '';
        this.filtros.estado = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'fecha_inicio';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;
        this.filtrarProyectos();
    }

    public filtrarProyectos(){
        this.loading = true;
        
        if(!this.filtros.id_cliente){
            this.filtros.id_cliente = '';
        }

        this.apiService.getAll('proyectos', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe(proyectos => { 
                this.proyectos = proyectos;
                this.loading = false;
                if(this.modalRef){
                    this.closeModal();
                }
            }, error => {this.alertService.error(error); this.loading = false;});
    }

    override openModal(template: TemplateRef<any>, proyecto?: any) {
        super.openLargeModal(template, proyecto);
    }

    public openFilter(template: TemplateRef<any>) {
        this.apiService.getAll('usuarios/list')
            .pipe(this.untilDestroyed())
            .subscribe(usuarios => { 
                this.usuarios = usuarios;
            }, error => {this.alertService.error(error); });
        super.openLargeModal(template);
    }

    public setEstado(proyecto:any, estado:any){
        this.proyecto = proyecto;
        this.proyecto.estado = estado;
        if(estado == 'Anulado'){
            this.proyecto.enable = 0;
        }else{
            this.proyecto.enable = 1;
        }
        this.onSubmit();
    }

    public override delete(item: any | number): void {
        const itemToDelete = typeof item === 'number' ? item : (item as any).id;
        
        Swal.fire({
          title: '¿Estás seguro?',
          text: '¡No podrás revertir esto!',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Sí, eliminarlo',
          cancelButtonText: 'Cancelar'
        }).then((result) => {
          if (result.isConfirmed) {
                this.loading = true;
                this.apiService.delete('proyecto/', itemToDelete)
                    .pipe(this.untilDestroyed())
                    .subscribe({
                        next: (deletedItem: any) => {
                            const index = this.proyectos.data?.findIndex((p: any) => p.id === deletedItem.id);
                            if (index !== -1 && index >= 0) {
                                this.proyectos.data.splice(index, 1);
                            }
                            this.alertService.success('Registro eliminado', 'El registro fue eliminado exitosamente.');
                            this.loading = false;
                        },
                        error: (error: any) => {
                            this.alertService.error(error);
                            this.loading = false;
                        }
                    });
          }
        });
    }

}
