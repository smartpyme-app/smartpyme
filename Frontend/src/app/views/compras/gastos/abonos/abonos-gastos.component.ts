import { Component, OnInit, TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { NgSelectModule } from '@ng-select/ng-select';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { BaseCrudComponent } from '@shared/base/base-crud.component';
import { LazyImageDirective } from '../../../../directives/lazy-image.directive';

@Component({
  selector: 'app-abonos-gastos',
  templateUrl: './abonos-gastos.component.html',
  standalone: true,
  imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, TooltipModule, PaginationComponent, LazyImageDirective],
  changeDetection: ChangeDetectionStrategy.OnPush
})

export class AbonosGastosComponent extends BaseCrudComponent<any> implements OnInit {

    public abonos:any = [];
    public abono:any = {};
    public downloading:boolean = false;
    public formaPagos:any = [];
    public proveedores:any = [];
    public usuarios:any = [];
    public sucursales:any = [];
    public documentos:any = [];
    public filtrado:boolean = false;

    constructor(
        apiService: ApiService,
        alertService: AlertService,
        modalManager: ModalManagerService,
        private cdr: ChangeDetectorRef
    ){
        super(apiService, alertService, modalManager, {
            endpoint: 'gasto/abono',
            itemsProperty: 'abonos',
            itemProperty: 'abono',
            reloadAfterSave: false,
            reloadAfterDelete: false,
            messages: {
                created: 'El abono fue guardado exitosamente.',
                updated: 'El abono fue guardado exitosamente.',
                deleted: 'El abono fue eliminado exitosamente.',
                createTitle: 'Abono guardado',
                updateTitle: 'Abono guardado',
                deleteTitle: 'Abono eliminado',
                deleteConfirm: '¿Desea eliminar el Registro?'
            }
        });
    }

    protected aplicarFiltros(): void {
        this.filtrarAbonos();
    }

    ngOnInit() {
        this.loadAll();

        this.apiService.getAll('proveedores/list')
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (proveedores) => {
                    this.proveedores = proveedores;
                },
                error: (error) => {
                    this.alertService.error(error);
                }
            });
    }

    public setOrden(columna: string) {
        if (this.filtros.orden === columna) {
          this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
        } else {
          this.filtros.orden = columna;
          this.filtros.direccion = 'asc';
        }

        this.filtrarAbonos();
    }

    public override loadAll() {
        this.filtros.id_sucursal = '';
        this.filtros.id_proveedor = '';
        this.filtros.estado = '';
        this.filtros.buscador = '';
        this.filtros.forma_pago = '';
        this.filtros.orden = 'fecha';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;
        
        this.loading = true;
        this.filtrarAbonos();
    }

    public filtrarAbonos(){
        this.loading = true;
        this.apiService.getAll('gastos/abonos', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (abonos) => {
                    this.abonos = abonos;
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

    public setEstado(abono: any){
        this.onSubmit(abono, true);
    }

    public override delete(id: number) {
        super.delete(id);
    }

    public openFilter(template: TemplateRef<any>) {
        this.apiService.getAll('formas-de-pago/list')
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (formaPagos) => {
                    this.formaPagos = formaPagos;
                },
                error: (error) => {
                    this.alertService.error(error);
                }
            });
        this.openModal(template, undefined, { class: 'modal-md', backdrop: 'static' });
    }

    public descargar(){
        this.downloading = true;
        this.apiService.export('gastos/abonos/exportar', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (data: Blob) => {
                    const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'abonos-gastos.xlsx';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);
                    this.downloading = false;
                },
                error: (error) => {
                    this.alertService.error(error);
                    this.downloading = false;
                }
            });
    }


}

