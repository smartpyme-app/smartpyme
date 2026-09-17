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

@Component({
    selector: 'app-admin-activos-plantillas',
    templateUrl: './admin-activos-plantillas.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, TooltipModule],
    changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AdminActivosPlantillasComponent extends BaseCrudComponent<any> implements OnInit {

    readonly paises = ['SV', 'CR', 'HN', 'GT', 'NI', 'PA', 'BZ', 'MX'];

    public codPais = 'SV';
    public plantillas: any[] = [];
    public plantilla: any = {};

    constructor(
        apiService: ApiService,
        alertService: AlertService,
        modalManager: ModalManagerService,
        private cdr: ChangeDetectorRef,
    ) {
        super(apiService, alertService, modalManager, {
            endpoint: 'activos-fijos/plantilla',
            itemsProperty: 'plantillas',
            itemProperty: 'plantilla',
            messages: {
                created: 'Plantilla creada correctamente.',
                updated: 'Plantilla guardada correctamente.',
                createTitle: 'Plantilla creada',
                updateTitle: 'Plantilla guardada',
            },
            initNewItem: (item) => ({
                ...item,
                cod_pais: this.codPais,
                metodo_depreciacion: 'linea_recta',
                valor_residual_default: 0,
                permite_bien_usado: false,
                activo: true,
            }),
        });
    }

    ngOnInit() {
        this.loadAll();
    }

    public override loadAll() {
        this.loading = true;
        this.apiService.getAll(`activos-fijos/plantillas/${this.codPais}`)
            .pipe(this.untilDestroyed())
            .subscribe(plantillas => {
                this.plantillas = plantillas ?? [];
                this.loading = false;
                this.cdr.markForCheck();
            }, error => {
                this.alertService.error(error);
                this.loading = false;
                this.cdr.markForCheck();
            });
    }

    public cambiarPais() {
        this.loadAll();
    }

    protected aplicarFiltros(): void {
        this.loadAll();
    }

    override openModal(template: TemplateRef<any>, plantilla?: any) {
        if (plantilla) {
            super.openModal(template, { ...plantilla }, { class: 'modal-md', backdrop: 'static' });
        } else {
            super.openModal(template, undefined, { class: 'modal-md', backdrop: 'static' });
            this.plantilla.cod_pais = this.codPais;
        }
    }

    public override delete(item: any | number): void {
        const id = typeof item === 'number' ? item : item?.id;
        Swal.fire({
            title: '¿Eliminar plantilla?',
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
        const pct = Number(this.plantilla.porcentaje_anual);
        if (pct > 0) {
            this.plantilla.vida_util_anios = Math.round((100 / pct) * 100) / 100;
        }
    }

    sincronizarDesdeVidaUtil() {
        const años = Number(this.plantilla.vida_util_anios);
        if (años > 0) {
            this.plantilla.porcentaje_anual = Math.round((100 / años) * 100) / 100;
        }
    }
}
