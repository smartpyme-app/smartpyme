import { Component, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { BaseCrudComponent } from '@shared/base/base-crud.component';

import * as moment from 'moment';
import Swal from 'sweetalert2';

@Component({
    selector: 'app-cuentas',
    templateUrl: './cuentas.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, TooltipModule, PaginationComponent],
    
})

export class CuentasComponent extends BaseCrudComponent<any> implements OnInit {

    public cuentas:any = {};
    public cuenta:any = {};
    public override saving:boolean = false;

    constructor(
        protected override apiService: ApiService,
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService
    ){
        super(apiService, alertService, modalManager, {
            endpoint: 'cuenta',
            itemsProperty: 'cuentas',
            itemProperty: 'cuenta',
            reloadAfterSave: true,
            reloadAfterDelete: false,
            messages: {
                created: 'El cuenta fue añadida exitosamente.',
                updated: 'El cuenta fue guardada exitosamente.',
                deleted: 'Cuenta eliminada exitosamente.',
                createTitle: 'Cuenta creada',
                updateTitle: 'Cuenta guardada',
                deleteTitle: 'Cuenta eliminada',
                deleteConfirm: '¿Estás seguro?'
            }
        });
    }

    protected aplicarFiltros(): void {
        this.filtrarCuentas();
    }

    ngOnInit() {
        this.cuenta.del = this.apiService.date();
        this.cuenta.al  = this.apiService.date();

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

    public override loadAll() {
        this.filtros.tipo = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'id';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;
        this.filtrarCuentas();
    }

    public filtrarCuentas(){
        this.loading = true;
        this.apiService.getAll('bancos/cuentas', this.filtros)
          .pipe(this.untilDestroyed())
          .subscribe({
            next: (cuentas) => {
                this.cuentas = cuentas;
                this.loading = false;
                if(this.modalRef){
                    this.closeModal();
                }
            },
            error: (error) => {
                this.alertService.error(error);
                this.loading = false;
            }
          });
    }

    public override openModal(template: TemplateRef<any>, cuenta?: any) {
        this.cuenta = cuenta || {};
        super.openModal(template, {class: 'modal-lg', backdrop: 'static'});
    }

    public openFilter(template: TemplateRef<any>) {
        this.openModal(template, {class: 'modal-md', backdrop: 'static'});
    }

    public setEstado(cuenta:any){
        this.cuenta = cuenta;
        this.onSubmit();
    }

    public override delete(cuenta:any){
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
                  .subscribe({
                    next: (data) => {
                        for (let i = 0; i < this.cuentas.data.length; i++) { 
                            if (this.cuentas.data[i].id == data.id )
                                this.cuentas.data.splice(i, 1);
                        }
                    },
                    error: (error) => {
                        this.alertService.error(error);
                    }
                  });
          } else if (result.dismiss === Swal.DismissReason.cancel) {
            // Swal.fire('Cancelado', 'Tu archivo está seguro :)', 'info');
          }
        });
    }

    public imprimir(cuenta:any){
        window.open(this.apiService.baseUrl + '/api/banco/cuenta/libro/' + cuenta.id + '/' + cuenta.del + '/' + cuenta.al + '?token=' + this.apiService.auth_token());
    }
}
