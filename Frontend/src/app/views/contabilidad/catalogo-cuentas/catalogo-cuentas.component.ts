import { Component, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { NgSelectModule } from '@ng-select/ng-select';
import { ImportarExcelComponent } from '@shared/parts/importar-excel/importar-excel.component';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { BasePaginatedComponent, PaginatedResponse } from '@shared/base/base-paginated.component';

import * as moment from 'moment';
import Swal from 'sweetalert2';

@Component({
    selector: 'app-catalogo-cuentas',
    templateUrl: './catalogo-cuentas.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, ImportarExcelComponent],
    
})

export class CatalogoCuentasComponent extends BasePaginatedComponent implements OnInit {

    public cuentas: PaginatedResponse<any> = {} as PaginatedResponse;
    public sucursales:any = [];
    public clientes:any = [];
    public usuarios:any = [];
    public cuenta:any = {};
    public saving:boolean = false;
    public override filtros:any = {};

    modalRef!: BsModalRef;

    constructor(apiService: ApiService, alertService: AlertService,
                private modalService: BsModalService
    ){
        super(apiService, alertService);
    }

    protected getPaginatedData(): PaginatedResponse | null {
        return this.cuentas;
    }

    protected setPaginatedData(data: PaginatedResponse): void {
        this.cuentas = data;
    }

    ngOnInit() {

        this.loadAll();
    }

    public setOrden(columna: string) {
        if (this.filtros.orden === columna) {
          this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
        } else {
          this.filtros.orden = columna;
          this.filtros.direccion = 'asc';
        }

        this.filtrarCuentas();
    }

    public loadAll() {
        this.filtros.tipo = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'id';
        this.filtros.direccion = 'asc';
        this.filtros.paginate = 10;
        this.filtrarCuentas();
    }

    public filtrarCuentas(){
        this.loading = true;
        this.apiService.getAll('catalogo/cuentas', this.filtros)
          .pipe(this.untilDestroyed())
          .subscribe(cuentas => {
            this.cuentas = cuentas;
            this.loading = false;
            if(this.modalRef){
                this.modalRef.hide();
            }
        }, error => {this.alertService.error(error); this.loading = false;});
    }


    public openModal(template: TemplateRef<any>, cuenta:any) {
        this.cuenta = cuenta;
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


    public setEstado(cuenta:any){
        this.cuenta = cuenta;
        this.onSubmit();
    }

    // setPagination() ahora se hereda de BasePaginatedComponent

    public delete(cuenta:any){

        Swal.fire({
          title: '¿Estás seguro?',
          text: '¡No podrás revertir esto!',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Sí, eliminarlo',
          cancelButtonText: 'Cancelar'
        }).then((result) => {
          if (result.isConfirmed) {
                this.apiService.delete('cuenta/', cuenta.id)
                  .pipe(this.untilDestroyed())
                  .subscribe(data => {
                    for (let i = 0; i < this.cuentas.data.length; i++) {
                        if (this.cuentas.data[i].id == data.id )
                            this.cuentas.data.splice(i, 1);
                    }
                }, error => {this.alertService.error(error); });
          } else if (result.dismiss === Swal.DismissReason.cancel) {
            // Swal.fire('Cancelado', 'Tu archivo está seguro :)', 'info');
          }
        });

    }

    public onSubmit(){
        this.saving = true;
        this.apiService.store('cuenta', this.cuenta)
          .pipe(this.untilDestroyed())
          .subscribe(cuenta => {
            if (!this.cuenta.id) {
                this.loadAll();
                this.alertService.success('Paquete creada', 'El cuenta fue añadida exitosamente.');
            }else{
                this.alertService.success('Paquete guardada', 'El cuenta fue guardada exitosamente.');
            }
            this.saving = false;
            if(this.modalRef){
                this.modalRef.hide();
            }
            this.alertService.modal = false;
        }, error => {this.alertService.error(error); this.saving = false;});
    }

}
