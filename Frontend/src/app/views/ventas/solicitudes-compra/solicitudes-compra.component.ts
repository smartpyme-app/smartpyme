import { Component, OnInit, TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseCrudComponent } from '@shared/base/base-crud.component';
import { LazyImageDirective } from '../../../directives/lazy-image.directive';

@Component({
    selector: 'app-solicitudes-compra',
    templateUrl: './solicitudes-compra.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, LazyImageDirective],
    changeDetection: ChangeDetectionStrategy.OnPush
})

export class SolicitudesCompraComponent extends BaseCrudComponent<any> implements OnInit {

    public compras:any = {};
    public compra:any = {};
    public downloading:boolean = false;
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
            endpoint: 'orden-de-compra',
            itemsProperty: 'compras',
            itemProperty: 'compra',
            reloadAfterSave: false,
            reloadAfterDelete: false,
            messages: {
                created: 'La solicitud de compra fue guardada exitosamente.',
                updated: 'La solicitud de compra fue guardada exitosamente.',
                createTitle: 'Solicitud de compra guardada',
                updateTitle: 'Solicitud de compra guardada'
            },
            afterSave: () => {
                this.compra = {};
            }
        });
    }

    protected aplicarFiltros(): void {
        this.filtrarCompras();
    }

    ngOnInit() {
        this.loadAll();

        this.apiService.getAll('proveedores/list')
            .pipe(this.untilDestroyed())
            .subscribe(proveedores => { 
                this.proveedores = proveedores;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.cdr.markForCheck(); });
    }

    public setOrden(columna: string) {
        if (this.filtros.orden === columna) {
          this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
        } else {
          this.filtros.orden = columna;
          this.filtros.direccion = 'asc';
        }

        this.filtrarCompras();
    }

    public override loadAll() {
        this.filtros.id_sucursal = '';
        this.filtros.id_proveedor = '';
        this.filtros.id_usuario = '';
        this.filtros.estado = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'fecha';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;
        this.filtrarCompras();
    }

    public async filtrarCompras(): Promise<void> {
        this.loading = true;
        if(!this.filtros.id_proveedor){
            this.filtros.id_proveedor = '';
        }

        try {
            this.compras = await this.apiService.getAll('ordenes-de-compras/solicitudes', this.filtros)
                .pipe(this.untilDestroyed())
                .toPromise();

            if(this.modalRef){
                this.closeModal();
            }
        } catch (error: any) {
            this.alertService.error(error);
        } finally {
            this.loading = false;
        }
    }

    public async setEstado(cotizacion:any){
        try {
            await this.apiService.store('orden-de-compra', cotizacion)
                .pipe(this.untilDestroyed())
                .toPromise();
            
            this.alertService.success('Solicitud de compra actualizada', 'La solicitud de compra fue actualizada exitosamente.');
        } catch (error: any) {
            this.alertService.error(error);
            this.cdr.markForCheck();
        }
    }

    public override async delete(item: any | number): Promise<void> {
        const itemToDelete = typeof item === 'number' ? item : (item as any).id;
        
        if (!confirm('¿Desea eliminar el Registro?')) {
            return;
        }

        this.loading = true;
        try {
            const deletedItem = await this.apiService.delete('orden-de-compra/', itemToDelete)
                .pipe(this.untilDestroyed())
                .toPromise();
            
            const index = this.compras.data?.findIndex((c: any) => c.id === deletedItem.id);
            if (index !== -1 && index >= 0) {
                this.compras.data.splice(index, 1);
            }
            this.alertService.success('Registro eliminado', 'El registro fue eliminado exitosamente.');
        } catch (error: any) {
            this.alertService.error(error);
            this.cdr.markForCheck();
        } finally {
            this.loading = false;
            this.cdr.markForCheck();
        }
    }

    public reemprimir(compra:any){
        window.open(this.apiService.baseUrl + '/api/reporte/facturacion/' + compra.id + '?token=' + this.apiService.auth_token(), 'Impresión', 'width=400');
    }

    async openModalEdit(template: TemplateRef<any>, compra:any) {
        this.compra = compra;
        let documentos: any;
        try {
            documentos = await this.apiService.getAll('documentos')
                .pipe(this.untilDestroyed())
                .toPromise();
        } catch (error: any) {
            this.alertService.error(error);
            this.cdr.markForCheck();
        } finally {
            if (documentos) {
                this.documentos = documentos;
            }
            this.cdr.markForCheck();
            this.openModal(template, compra);
        }
    }

    public async openFilter(template: TemplateRef<any>) {
        let sucursales: any;
        let usuarios: any;
        try {
            [sucursales, usuarios] = await Promise.all([
                this.apiService.getAll('sucursales/list')
                    .pipe(this.untilDestroyed())
                    .toPromise(),
                this.apiService.getAll('usuarios/list')
                    .pipe(this.untilDestroyed())
                    .toPromise()
            ]);
        } catch (error: any) {
            this.alertService.error(error);
            this.cdr.markForCheck();
        } finally {
            if (sucursales) {
                this.sucursales = sucursales;
            }
            if (usuarios) {
                this.usuarios = usuarios;
            }
            this.cdr.markForCheck();
            this.openModal(template);
        }
    }

    public imprimir(compra:any){
        window.open(this.apiService.baseUrl + '/api/orden-de-compra/impresion/' + compra.id + '?token=' + this.apiService.auth_token());
    }

    public async descargar(): Promise<void> {
        this.downloading = true;
        try {
            const data = await this.apiService.export('ordenes-de-compras/solicitudes/exportar', this.filtros)
                .pipe(this.untilDestroyed())
                .toPromise() as Blob;

            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'solicitudes-de-compra.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        } catch (error: any) {
            this.alertService.error(error);
        } finally {
            this.downloading = false;
        }
    }

}
