import { Component, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { NgSelectModule } from '@ng-select/ng-select';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { BasePaginatedComponent, PaginatedResponse } from '@shared/base/base-paginated.component';

import * as moment from 'moment';
import Swal from 'sweetalert2';

@Component({
    selector: 'app-proyectos',
    templateUrl: './proyectos.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, PaginationComponent, PopoverModule, TooltipModule],

})

export class ProyectosComponent extends BasePaginatedComponent implements OnInit {

    public proyectos: PaginatedResponse<any> = {} as PaginatedResponse;
    public sucursales:any = [];
    public clientes:any = [];
    public usuarios:any = [];
    public proyecto:any = {};
    public saving:boolean = false;
    public override filtros:any = {};

    modalRef!: BsModalRef;

    constructor(apiService: ApiService, alertService: AlertService,
                private modalService: BsModalService
    ){
        super(apiService, alertService);
    }

    protected getPaginatedData(): PaginatedResponse | null {
        return this.proyectos;
    }

    protected setPaginatedData(data: PaginatedResponse): void {
        this.proyectos = data;
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

    public loadAll() {
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
                    this.modalRef.hide();
                }
            }, error => {this.alertService.error(error); this.loading = false;});
    }


    public openModal(template: TemplateRef<any>, proyecto:any) {
        this.proyecto = proyecto;
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template, {class: 'modal-lg', backdrop: 'static'});
    }


    public openFilter(template: TemplateRef<any>) {
        this.apiService.getAll('usuarios/list')
            .pipe(this.untilDestroyed())
            .subscribe(usuarios => { 
                this.usuarios = usuarios;
            }, error => {this.alertService.error(error); });
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template, {class: 'modal-lg', backdrop: 'static'});
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

    // setPagination() ahora se hereda de BasePaginatedComponent

    public delete(proyecto:any){

        Swal.fire({
          title: '¿Estás seguro?',
          text: '¡No podrás revertir esto!',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Sí, eliminarlo',
          cancelButtonText: 'Cancelar'
        }).then((result) => {
          if (result.isConfirmed) {
                this.apiService.delete('proyecto/', proyecto.id)
                    .pipe(this.untilDestroyed())
                    .subscribe(data => {
                        for (let i = 0; i < this.proyectos.data.length; i++) { 
                            if (this.proyectos.data[i].id == data.id )
                                this.proyectos.data.splice(i, 1);
                        }
                    }, error => {this.alertService.error(error); });
          } else if (result.dismiss === Swal.DismissReason.cancel) {
            // Swal.fire('Cancelado', 'Tu archivo está seguro :)', 'info');
          }
        });

    }

    public onSubmit(){
        this.saving = true;
        this.apiService.store('proyecto', this.proyecto)
            .pipe(this.untilDestroyed())
            .subscribe(proyecto => {
            if (!this.proyecto.id) {
                this.loadAll();
                this.alertService.success('proyecto creada', 'El proyecto fue añadido exitosamente.');
            }else{
                this.alertService.success('proyecto guardado', 'El proyecto fue guardado exitosamente.');
            }
            this.saving = false;
            if(this.modalRef){
                this.modalRef.hide();
            }
            this.proyecto = {};
            this.alertService.modal = false;
        }, error => {this.alertService.error(error); this.saving = false;});
    }

}
