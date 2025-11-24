import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { TruncatePipe } from '@pipes/truncate.pipe';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { ImportarExcelComponent } from '@shared/parts/importar-excel/importar-excel.component';
import { BaseCrudComponent } from '@shared/base/base-crud.component';

@Component({
    selector: 'app-proveedores',
    templateUrl: './proveedores.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, TruncatePipe, PopoverModule, TooltipModule, PaginationComponent, ImportarExcelComponent],

})
export class ProveedoresComponent extends BaseCrudComponent<any> implements OnInit {

    public proveedores:any = {};
    public proveedor:any = {};
    public downloading:boolean = false;
    public producto:any = {};
    public categorias:any = [];

    constructor(
        apiService:ApiService, 
        alertService:AlertService,
        modalManager: ModalManagerService
    ){
        super(apiService, alertService, modalManager, {
            endpoint: 'proveedor',
            itemsProperty: 'proveedores',
            itemProperty: 'proveedor',
            reloadAfterSave: false,
            reloadAfterDelete: false,
            messages: {
                created: 'El proveedor fue actualizado exitosamente.',
                updated: 'El proveedor fue actualizado exitosamente.',
                createTitle: 'Proveedor actualizado',
                updateTitle: 'Proveedor actualizado'
            },
            afterSave: () => {
                this.proveedor = {};
            }
        });
    }

    protected aplicarFiltros(): void {
        this.filtrarProveedores();
    }

    ngOnInit() {
        this.loadAll();
    }

    public override loadAll() {
        this.filtros.id_sucursal = '';
        this.filtros.id_categoria = '';
        this.filtros.buscador = '';
        this.filtros.estado = '';
        this.filtros.orden = 'nombre';
        this.filtros.direccion = 'asc';
        this.filtros.paginate = 10;
        this.filtrarProveedores();
        this.closeModal();
    }

    public async filtrarProveedores(): Promise<void> {
        this.loading = true;
        try {
            this.proveedores = await this.apiService.getAll('proveedores', this.filtros)
                .pipe(this.untilDestroyed())
                .toPromise();
        } catch (error: any) {
            this.alertService.error(error);
        } finally {
            this.loading = false;
        }
    }

    public setTipo(proveedor:any){
        this.proveedor = proveedor;
        this.onSubmit();
    }

    public setActivo(proveedor:any, estado:any){
        this.proveedor = proveedor;
        this.proveedor.enable = estado;
        this.onSubmit();
    }

    public setOrden(columna: string) {
        if (this.filtros.orden === columna) {
          this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
        } else {
          this.filtros.orden = columna;
          this.filtros.direccion = 'asc';
        }

        this.loadAll();
    }

    public override async delete(item: any | number): Promise<void> {
        const itemToDelete = typeof item === 'number' ? item : (item as any).id;
        
        if (!confirm('¿Desea eliminar el Registro?')) {
            return;
        }

        this.loading = true;
        try {
            // Nota: El endpoint original usa 'cliente/' pero debería ser 'proveedor/'
            const deletedItem = await this.apiService.delete('proveedor/', itemToDelete)
                .pipe(this.untilDestroyed())
                .toPromise();
            
            if (this.proveedores.data) {
                const index = this.proveedores.data.findIndex((p: any) => p.id === deletedItem.id);
                if (index !== -1) {
                    this.proveedores.data.splice(index, 1);
                }
            }
            this.alertService.success('Registro eliminado', 'El registro fue eliminado exitosamente.');
        } catch (error: any) {
            this.alertService.error(error);
        } finally {
            this.loading = false;
        }
    }

    override openModal(template: TemplateRef<any>) {
        super.openModal(template);
    }

    public async descargarPersonas(): Promise<void> {
        this.downloading = true;
        try {
            const data = await this.apiService.export('proveedores-personas/exportar', this.filtros)
                .pipe(this.untilDestroyed())
                .toPromise() as Blob;
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'proveedores-personas.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.alertService.modal = false;
        } catch (error: any) {
            this.alertService.error(error);
            this.alertService.modal = false;
        } finally {
            this.downloading = false;
        }
    }

    public async descargarEmpresas(): Promise<void> {
        this.downloading = true;
        this.alertService.modal = false;
        try {
            const data = await this.apiService.export('proveedores-empresas/exportar', this.filtros)
                .pipe(this.untilDestroyed())
                .toPromise() as Blob;
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'proveedores-empresas.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.alertService.modal = false;
        } catch (error: any) {
            this.alertService.error(error);
            this.alertService.modal = false;
        } finally {
            this.downloading = false;
        }
    }

}
