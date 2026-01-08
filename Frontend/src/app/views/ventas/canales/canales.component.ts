import { Component, OnInit, TemplateRef, inject, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
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

@Component({
    selector: 'app-canales',
    templateUrl: './canales.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, FilterPipe, PaginationComponent, PopoverModule, TooltipModule],
    changeDetection: ChangeDetectionStrategy.OnPush
})

export class CanalesComponent extends BaseCrudComponent<any> implements OnInit {

    public canales:any = [];
    public canal:any = {};
    public filtro:any = {};
    public filtrado:boolean = false;
    private cdr = inject(ChangeDetectorRef);

    constructor(
        apiService: ApiService,
        alertService: AlertService,
        modalManager: ModalManagerService
    ){
        super(apiService, alertService, modalManager, {
            endpoint: 'canal',
            itemsProperty: 'canales',
            itemProperty: 'canal',
            reloadAfterSave: false,
            reloadAfterDelete: false,
            messages: {
                created: 'El canal fue añadido exitosamente.',
                updated: 'El canal fue guardado exitosamente.',
                createTitle: 'Canal creado',
                updateTitle: 'Canal guardado'
            },
            initNewItem: (item) => {
                item.id_empresa = apiService.auth_user().id_empresa;
                item.enable = true;
                return item;
            },
            afterSave: (item, isNew) => {
                if (!isNew) {
                    const index = this.canales.findIndex((c: any) => c.id === item.id);
                    if (index !== -1) {
                        this.canales[index] = item;
                    }
                }
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
            this.canales = await this.apiService.getAll('canales')
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
        // No aplica filtros paginados, usa loadAll directamente
        this.loadAll();
    }

    override openModal(template: TemplateRef<any>, canal?: any) {
        super.openModal(template, canal, {class: 'modal-md', backdrop: 'static'});
    }

    public setEstado(canal:any){
        this.canal = canal;
        // Usar onSubmit con isStatusChange = true
        this.onSubmit(canal, true);
    }

    public override async onSubmit(item?: any, isStatusChange: boolean = false): Promise<void> {
        if (isStatusChange) {
            // Manejo especial para cambio de estado
            this.loading = true;
            const canalToSave = item || this.canal;
            try {
                const savedCanal = await this.apiService.store('canal', canalToSave)
                    .pipe(this.untilDestroyed())
                    .toPromise();
                
                const index = this.canales.findIndex((c: any) => c.id === savedCanal.id);
                if (index !== -1) {
                    this.canales[index] = savedCanal;
                }
                this.alertService.success('Estado actualizado', 'El estado del canal fue cambiado exitosamente.');
                this.loading = false;
                this.cdr.markForCheck();
            } catch (error: any) {
                this.alertService.error(error);
                this.loading = false;
                this.cdr.markForCheck();
            }
        } else {
            // Usar el método base para operaciones normales
            await super.onSubmit(item, false);
            // Mensaje personalizado con delay
            setTimeout(() => {
                const canal = item || this.canal;
                if (!canal.id) {
                    this.alertService.success('Canal creado', 'El canal fue añadido exitosamente.');
                } else {
                    this.alertService.success('Canal guardado', 'El canal fue guardado exitosamente.');
                }
            }, 300);
        }
    }

    public override async delete(item: any | number): Promise<void> {
        // Corregir endpoint (estaba usando 'gasto/' por error)
        const itemToDelete = typeof item === 'number' ? item : (item as any).id;
        
        if (!confirm('¿Desea eliminar el Registro?')) {
            return;
        }

        this.loading = true;
        try {
            const deletedItem = await this.apiService.delete('canal/', itemToDelete)
                .pipe(this.untilDestroyed())
                .toPromise();
            
            const index = this.canales.findIndex((c: any) => c.id === deletedItem.id);
            if (index !== -1) {
                this.canales.splice(index, 1);
            }
            this.alertService.success('Registro eliminado', 'El registro fue eliminado exitosamente.');
        } catch (error: any) {
            this.alertService.error(error);
        } finally {
            this.loading = false;
        }
    }

}
