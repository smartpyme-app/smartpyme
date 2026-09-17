import { Component, OnInit, TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseCrudComponent } from '@shared/base/base-crud.component';
import Swal from 'sweetalert2';
import { ActivosNavTabsComponent } from '@views/contabilidad/activos/activos-nav-tabs/activos-nav-tabs.component';

@Component({
    selector: 'app-activos-categorias',
    templateUrl: './categorias.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, TooltipModule, ActivosNavTabsComponent],
    changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ActivosCategoriasComponent extends BaseCrudComponent<any> implements OnInit {

    public categorias: any[] = [];
    public categoria: any = {};

    constructor(
        apiService: ApiService,
        alertService: AlertService,
        modalManager: ModalManagerService,
        private cdr: ChangeDetectorRef,
    ) {
        super(apiService, alertService, modalManager, {
            endpoint: 'activos/categoria',
            itemsProperty: 'categorias',
            itemProperty: 'categoria',
            messages: {
                created: 'La categoría fue creada correctamente.',
                updated: 'La categoría fue guardada correctamente.',
                createTitle: 'Categoría creada',
                updateTitle: 'Categoría guardada',
            },
            initNewItem: (item) => ({
                ...item,
                metodo_depreciacion: 'linea_recta',
                valor_residual_default: 0,
                permite_bien_usado: false,
            }),
        });
    }

    ngOnInit() {
        this.loadAll();
    }

    public override loadAll() {
        this.loading = true;
        this.apiService.getAll('activos/categorias')
            .pipe(this.untilDestroyed())
            .subscribe(categorias => {
                this.categorias = categorias ?? [];
                this.loading = false;
                this.cdr.markForCheck();
            }, error => {
                this.alertService.error(error);
                this.loading = false;
                this.cdr.markForCheck();
            });
    }

    protected aplicarFiltros(): void {
        this.loadAll();
    }

    override openModal(template: TemplateRef<any>, categoria?: any) {
        super.openModal(template, categoria, { class: 'modal-md', backdrop: 'static' });
    }

    public override delete(item: any | number): void {
        const id = typeof item === 'number' ? item : item?.id;
        Swal.fire({
            title: '¿Eliminar categoría?',
            text: 'No se puede eliminar si tiene activos asociados.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
        }).then(result => {
            if (result.isConfirmed) {
                super.delete(id);
            }
        });
    }

    sincronizarDesdePorcentaje() {
        const pct = Number(this.categoria.porcentaje_anual);
        if (pct > 0) {
            this.categoria.vida_util_anios = Math.round((100 / pct) * 100) / 100;
        }
    }

    sincronizarDesdeVidaUtil() {
        const años = Number(this.categoria.vida_util_anios);
        if (años > 0) {
            this.categoria.porcentaje_anual = Math.round((100 / años) * 100) / 100;
        }
    }

    public importarPlantillas() {
        this.loading = true;
        this.apiService.store('activos/categorias/importar-plantillas', {})
            .pipe(this.untilDestroyed())
            .subscribe(res => {
                this.alertService.success('Importación', res.message ?? 'Plantillas importadas.');
                this.loadAll();
            }, error => {
                this.alertService.error(error);
                this.loading = false;
                this.cdr.markForCheck();
            });
    }
}
