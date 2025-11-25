import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { ImportarExcelComponent } from '@shared/parts/importar-excel/importar-excel.component';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { TruncatePipe } from '@pipes/truncate.pipe';
import { BaseCrudComponent } from '@shared/base/base-crud.component';
import { LazyImageDirective } from '../../../directives/lazy-image.directive';

@Component({
    selector: 'app-clientes',
    templateUrl: './clientes.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, ImportarExcelComponent, PaginationComponent, TruncatePipe, PopoverModule, TooltipModule, LazyImageDirective],

})
export class ClientesComponent extends BaseCrudComponent<any> implements OnInit {

    public clientes:any = {};
    public cliente:any = {};
    public downloading:boolean = false;
    public producto:any = {};
    public categorias:any = [];

    constructor( 
        apiService:ApiService, 
        alertService:AlertService, 
        modalManager: ModalManagerService
    ){
        super(apiService, alertService, modalManager, {
            endpoint: 'cliente',
            itemsProperty: 'clientes',
            itemProperty: 'cliente',
            reloadAfterSave: false,
            reloadAfterDelete: false,
            messages: {
                created: 'El cliente fue actualizado exitosamente.',
                updated: 'El cliente fue actualizado exitosamente.',
                createTitle: 'Cliente actualizado',
                updateTitle: 'Cliente actualizado'
            },
            afterSave: () => {
                this.cliente = {};
            }
        });
    }

    protected aplicarFiltros(): void {
        this.filtrarClientes();
    }

    ngOnInit() {
        this.loadAll();
    }

    public override loadAll() {
        this.filtros.id_sucursal = '';
        this.filtros.tipo_contribuyente = '';
        this.filtros.tipo = '';
        this.filtros.buscador = '';
        this.filtros.estado = '';
        this.filtros.orden = 'nombre';
        this.filtros.direccion = 'asc';
        this.filtros.paginate = 10;
        this.filtrarClientes();

        // Ocultar modal de importación
        if(this.modalRef){
            this.closeModal();
        }
    }

    public async filtrarClientes(): Promise<void> {
        this.loading = true;
        try {
            this.clientes = await this.apiService.getAll('clientes', this.filtros)
                .pipe(this.untilDestroyed())
                .toPromise();
        } catch (error: any) {
            this.alertService.error(error);
        } finally {
            this.loading = false;
        }
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

    public setTipo(cliente:any){
        this.cliente = cliente;
        this.onSubmit();
    }

    public setActivo(cliente:any, estado:any){
        this.cliente = cliente;
        this.cliente.enable = estado;
        this.onSubmit();
    }

    public override async delete(item: any | number): Promise<void> {
        const itemToDelete = typeof item === 'number' ? item : (item as any).id;
        
        if (!confirm('¿Desea eliminar el Registro?')) {
            return;
        }

        this.loading = true;
        try {
            const deletedItem = await this.apiService.delete('cliente/', itemToDelete)
                .pipe(this.untilDestroyed())
                .toPromise();
            
            const index = this.clientes.data?.findIndex((c: any) => c.id === deletedItem.id);
            if (index !== -1 && index >= 0) {
                this.clientes.data.splice(index, 1);
            }
            this.alertService.success('Registro eliminado', 'El registro fue eliminado exitosamente.');
        } catch (error: any) {
            this.alertService.error(error);
        } finally {
            this.loading = false;
        }
    }

    public async descargarPersonas(): Promise<void> {
        this.downloading = true;
        try {
            const data = await this.apiService.export('clientes-personas/exportar', this.filtros)
                .pipe(this.untilDestroyed())
                .toPromise() as Blob;
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'clientes-personas.xlsx';
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
        try {
            const data = await this.apiService.export('clientes-empresas/exportar', this.filtros)
                .pipe(this.untilDestroyed())
                .toPromise() as Blob;
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'clientes-empresas.xlsx';
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
