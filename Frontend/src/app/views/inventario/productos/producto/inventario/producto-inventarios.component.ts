import { Component, OnInit, TemplateRef, Input, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { SumPipe } from '@pipes/sum.pipe';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';
import { CrearAjusteComponent } from '@shared/modals/crear-ajuste/crear-ajuste.component';
import { NotificacionesContainerComponent } from '@shared/parts/notificaciones/notificaciones-container.component';
import { TooltipModule } from 'ngx-bootstrap/tooltip';

@Component({
    selector: 'app-producto-inventarios',
    templateUrl: './producto-inventarios.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, SumPipe, CrearAjusteComponent, NotificacionesContainerComponent, TooltipModule],
    changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProductoInventariosComponent extends BaseModalComponent implements OnInit {

    @Input() producto: any = {};
    public bodegas: any = [];
    /** Bodegas que aún no tienen inventario asignado para este producto (evita duplicados) */
    public bodegasDisponibles: any = [];
    public sucursal: any = {};
    public inventario: any = {};
    public sucursalSelected: any = {};
    public buscador:string = '';

    constructor(
        private apiService: ApiService, 
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService,
        private route: ActivatedRoute, 
        private router: Router,
        private cdr: ChangeDetectorRef
    ){
        super(modalManager, alertService);
    }

    ngOnInit() {
    }

    /** El hijo actualiza la fila por referencia; solo forzamos detección de cambios. */
    public setAjuste(_event: unknown): void {
        this.cdr.markForCheck();
    }

    override openModal(template: TemplateRef<any>, inventario:any) {
        this.inventario = inventario;

        this.apiService.getAll('bodegas/list')
          .pipe(this.untilDestroyed())
          .subscribe(bodegas => {
            this.bodegas = bodegas;
            const idsBodegasAsignadas = (this.producto?.inventarios || []).map((inv: any) => inv.id_bodega);
            this.bodegasDisponibles = this.bodegas.filter((b: any) => 
                !idsBodegasAsignadas.includes(b.id) || b.id === this.inventario?.id_bodega
            );
            this.loading = false;
            this.cdr.markForCheck();
        }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck(); });

        if (!this.inventario.id) {
            this.inventario.id_producto = this.producto.id;
            this.inventario.stock = 0;
            this.inventario.stock_minimo = this.inventario.stock_minimo ?? 0;
            this.inventario.stock_maximo = this.inventario.stock_maximo ?? 0;
        }
        super.openModal(template, {class: 'modal-md'});
    }

    public onSubmit() {
        this.loading = true;
        this.cdr.markForCheck();

        const payload = { ...this.inventario };
        if (!payload.id) {
            payload.stock = 0;
        }

        this.apiService.store('inventario', payload)
          .pipe(this.untilDestroyed())
          .subscribe(invResp => {
            if (!this.inventario.id) {
                this.producto.inventarios.push(invResp);
                this.alertService.success('Inventario creado', 'El inventario fue añadido exitosamente. Use «Agregar stock» para cargar existencias.');
            } else {
                const idx = this.producto.inventarios.findIndex((inv: any) => inv.id === invResp.id);
                if (idx !== -1) {
                    Object.assign(this.producto.inventarios[idx], invResp);
                }
                this.alertService.success('Inventario actualizado', 'Los límites de stock se guardaron correctamente.');
            }

            this.inventario = {};
            this.loading = false;
            this.cdr.markForCheck();
            this.closeModal();
        }, error => {
            const errorMessage = error.error?.error || error.error?.message || error.message || 'Error desconocido';

            this.alertService.error(errorMessage);
            this.loading = false;
            this.cdr.markForCheck();
        });
    }

    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('inventario/', id)
              .pipe(this.untilDestroyed())
              .subscribe(data => {
                for (let i = 0; i < this.producto.inventarios.length; i++) {
                    if (this.producto.inventarios[i].id == data.id )
                        this.producto.inventarios.splice(i, 1);
                }
                this.alertService.success('Inventario eliminado', 'El inventario fue eliminado exitosamente.');
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.cdr.markForCheck(); });

        }

    }

    obtenerIdSucursal(id: number | string | null | undefined) {
        const b = this.bodegas.find((bodega: any) => bodega.id == id);
        if (b) {
            this.inventario.id_sucursal = b.id_sucursal;
        }
    }

}
