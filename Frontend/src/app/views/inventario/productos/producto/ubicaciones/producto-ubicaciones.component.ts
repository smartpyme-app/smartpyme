import { Component, Input, TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { TooltipModule } from 'ngx-bootstrap/tooltip';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';
import { NotificacionesContainerComponent } from '@shared/parts/notificaciones/notificaciones-container.component';

@Component({
    selector: 'app-producto-ubicaciones',
    templateUrl: './producto-ubicaciones.component.html',
    standalone: true,
    imports: [CommonModule, FormsModule, TooltipModule, NotificacionesContainerComponent],
    changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProductoUbicacionesComponent extends BaseModalComponent {

    @Input() producto: any = {};

    public form: any = {};
    public editIndex: number | null = null;

    constructor(
        private apiService: ApiService,
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService,
        private cdr: ChangeDetectorRef,
    ) {
        super(modalManager, alertService);
    }

    get filas(): any[] {
        const rows: any[] = [];
        for (const inv of this.producto?.inventarios || []) {
            for (const u of inv.ubicaciones || []) {
                rows.push({
                    inventario: inv,
                    ubicacion: u,
                    nombre_bodega: inv.nombre_bodega || '—',
                });
            }
        }
        return rows;
    }

    get inventariosDisponibles(): any[] {
        return this.producto?.inventarios || [];
    }

    override openModal(template: TemplateRef<any>, fila: any = null) {
        this.editIndex = null;
        if (fila?.ubicacion) {
            this.form = {
                id: fila.ubicacion.id ?? null,
                id_inventario: fila.inventario.id,
                ubicacion: fila.ubicacion.ubicacion ?? null,
                ubicacion_especifica: fila.ubicacion.ubicacion_especifica ?? null,
                detalle: fila.ubicacion.detalle ?? null,
                activa: fila.ubicacion.activa !== false,
            };
            const inv = fila.inventario;
            this.editIndex = (inv.ubicaciones || []).findIndex((u: any) =>
                (fila.ubicacion.id && u.id === fila.ubicacion.id) || u === fila.ubicacion
            );
        } else {
            this.form = {
                id: null,
                id_inventario: this.inventariosDisponibles[0]?.id ?? null,
                ubicacion: null,
                ubicacion_especifica: null,
                detalle: null,
                activa: true,
            };
        }
        this.loading = false;
        this.cdr.markForCheck();
        super.openModal(template, { class: 'modal-lg' });
    }

    public onSubmit() {
        if (!this.form.id_inventario) {
            this.alertService.error('Seleccione una bodega');
            return;
        }
        const inv = (this.producto.inventarios || []).find((i: any) => i.id == this.form.id_inventario);
        if (!inv) {
            this.alertService.error('No se encontró el inventario de la bodega');
            return;
        }

        const ubicaciones = (inv.ubicaciones || []).map((u: any) => ({ ...u }));
        const row = {
            id: this.form.id || undefined,
            ubicacion: this.form.ubicacion,
            ubicacion_especifica: this.form.ubicacion_especifica,
            detalle: this.form.detalle,
            activa: this.form.activa !== false,
        };

        if (this.editIndex !== null && this.editIndex >= 0) {
            ubicaciones[this.editIndex] = { ...ubicaciones[this.editIndex], ...row };
        } else {
            ubicaciones.push(row);
        }

        this.loading = true;
        this.cdr.markForCheck();

        const payload = { ...inv, ubicaciones };
        this.apiService.store('inventario', payload)
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (invResp) => {
                    const idx = this.producto.inventarios.findIndex((i: any) => i.id === invResp.id);
                    if (idx !== -1) {
                        Object.assign(this.producto.inventarios[idx], invResp);
                    }
                    this.loading = false;
                    this.closeModal();
                    this.alertService.success('Ubicación guardada', 'La ubicación se guardó correctamente.');
                    this.cdr.markForCheck();
                },
                error: (error) => {
                    const msg = error.error?.error || error.error?.message || error.message || 'Error al guardar';
                    this.alertService.error(msg);
                    this.loading = false;
                    this.cdr.markForCheck();
                },
            });
    }

    public delete(fila: any) {
        if (!confirm('¿Desea eliminar esta ubicación?')) {
            return;
        }
        const inv = fila.inventario;
        const ubicaciones = (inv.ubicaciones || [])
            .filter((u: any) => u !== fila.ubicacion && !(fila.ubicacion.id && u.id === fila.ubicacion.id))
            .map((u: any) => ({ ...u }));

        this.loading = true;
        this.cdr.markForCheck();

        const payload = { ...inv, ubicaciones };
        this.apiService.store('inventario', payload)
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (invResp) => {
                    const idx = this.producto.inventarios.findIndex((i: any) => i.id === invResp.id);
                    if (idx !== -1) {
                        Object.assign(this.producto.inventarios[idx], invResp);
                    }
                    this.loading = false;
                    this.alertService.success('Ubicación eliminada', 'La ubicación se eliminó correctamente.');
                    this.cdr.markForCheck();
                },
                error: (error) => {
                    this.alertService.error(error);
                    this.loading = false;
                    this.cdr.markForCheck();
                },
            });
    }

}
