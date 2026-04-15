import { Component, OnInit, TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseCrudComponent } from '@shared/base/base-crud.component';
import { FilterPipe } from '@pipes/filter.pipe';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { documentoNombreOpciones, DocumentoNombreOption } from './documento-nombre-options';

@Component({
    selector: 'app-documentos',
    templateUrl: './documentos.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, FilterPipe, PaginationComponent, PopoverModule, TooltipModule],
    changeDetection: ChangeDetectionStrategy.OnPush,
    
})

export class DocumentosComponent extends BaseCrudComponent<any> implements OnInit {

    public documentos:any = [];
    public documento:any = {};
    public sucursales:any = [];
    public filtro:any = {};
    public filtrado:boolean = false;

    public nuevaResolucion:boolean = false;
    public change:boolean = false;

    opcionesNombreDocumento(): DocumentoNombreOption[] {
        return documentoNombreOpciones(this.apiService.auth_user()?.empresa);
    }

    constructor(
        apiService: ApiService,
        alertService: AlertService,
        modalManager: ModalManagerService,
        private cdr: ChangeDetectorRef
    ){
        super(apiService, alertService, modalManager, {
            endpoint: 'documento',
            itemsProperty: 'documentos',
            itemProperty: 'documento',
            messages: {
                created: 'El documento fue añadido exitosamente.',
                updated: 'El documento fue guardado exitosamente.',
                createTitle: 'Documento creado',
                updateTitle: 'Documento guardado'
            },
            initNewItem: (item) => {
                item.id_empresa = apiService.auth_user().id_empresa;
                item.id_sucursal = apiService.auth_user().id_sucursal;
                item.activo = true;
                item.correlativo = 1;
                return item;
            }
        });
    }

    ngOnInit() {
        this.loadAll();
    }

    public override async loadAll(): Promise<void> {
        this.loading = true;
        this.filtro.estado = '';
        try {
            this.documentos = await this.apiService.getAll('documentos')
                .pipe(this.untilDestroyed())
                .toPromise();
            this.filtrado = false;
            this.cdr.markForCheck();
        } catch (error: any) {
            this.alertService.error(error);
            this.cdr.markForCheck();
        } finally {
            this.loading = false;
            this.cdr.markForCheck();
        }
    }

    protected aplicarFiltros(): void {
        this.loadAll();
    }

    public override async openModal(template: TemplateRef<any>, documentoOrConfig?: any, nuevaResolucion?: boolean): Promise<void> {
        // Manejar la sobrecarga compleja del método original
        const isCustomSignature = nuevaResolucion !== undefined || 
            (documentoOrConfig && typeof documentoOrConfig === 'object' && !documentoOrConfig.class && !documentoOrConfig.size && !documentoOrConfig.backdrop);
        
        if (isCustomSignature) {
            const documento = documentoOrConfig || {};
            this.documento = documento;
            this.nuevaResolucion = nuevaResolucion || false;
            
            if (!this.documento.id) {
                this.documento.id_empresa = this.apiService.auth_user().id_empresa;
                this.documento.id_sucursal = this.apiService.auth_user().id_sucursal;
                this.documento.activo = true;
                this.documento.correlativo = 1;
            }
            
            try {
                this.sucursales = await this.apiService.getAll('sucursales/list')
                    .pipe(this.untilDestroyed())
                    .toPromise();
                this.cdr.markForCheck();
            } catch (error: any) {
                this.alertService.error(error);
                this.cdr.markForCheck();
            }

            super.openModal(template, documento, {class: 'modal-md', backdrop: 'static'});
        
            if (nuevaResolucion) {
                this.documento.correlativo = '';
                this.documento.rangos = '';
                this.documento.numero_autorizacion = '';
                this.documento.resolucion = '';
                this.documento.fecha = '';
                this.documento.activo = true;
                this.documento.nota = '';
            }
        } else {
            // Es la firma base, solo pasar la config
            super.openModal(template, documentoOrConfig);
        }
    }

    public setEstado(documento:any, change:boolean = false){
        this.documento = documento;
        this.documento.change = change;
        this.onSubmit();
    }

    public override async onSubmit(item?: any): Promise<void> {
        const documentoToSave = item || this.documento;
        documentoToSave.nuevaResolucion = this.nuevaResolucion;
        await super.onSubmit(documentoToSave);
    }

    public override async delete(item: any | number): Promise<void> {
        // Corregir endpoint (estaba usando 'gasto/' por error)
        const itemToDelete = typeof item === 'number' ? item : (item as any).id;
        
        if (!confirm('¿Desea eliminar el Registro?')) {
            return;
        }

        this.loading = true;
        try {
            const deletedItem = await this.apiService.delete('documento/', itemToDelete)
                .pipe(this.untilDestroyed())
                .toPromise();
            
            const index = this.documentos.findIndex((d: any) => d.id === deletedItem.id);
            if (index !== -1) {
                this.documentos.splice(index, 1);
            }
            this.alertService.success('Registro eliminado', 'El registro fue eliminado exitosamente.');
            this.cdr.markForCheck();
        } catch (error: any) {
            this.alertService.error(error);
            this.cdr.markForCheck();
        } finally {
            this.loading = false;
            this.cdr.markForCheck();
        }
    }

}
