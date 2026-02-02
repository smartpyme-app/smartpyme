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

@Component({
    selector: 'app-producto-inventarios',
    templateUrl: './producto-inventarios.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, SumPipe],
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

    public setAjuste(event:any){
        this.inventario.stock = event.stock_real;
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

        if(!this.inventario.id) {
            this.inventario.stock = 0;
            this.inventario.id_producto = this.producto.id;
            this.inventario.id_bodega = this.inventario.id_bodega;
            this.alertService.success('Inventario creado', 'El inventario fue añadido exitosamente.');
        }else{
            this.alertService.success('Inventario guardado', 'El inventario fue guardado exitosamente.');
        }
        super.openModal(template, {class: 'modal-md'});
    }

    public onSubmit() {
        this.loading = true;
        this.cdr.markForCheck();

        this.apiService.store('inventario', this.inventario)
          .pipe(this.untilDestroyed())
          .subscribe(inventario => {
            if(!this.inventario.id) {
                this.producto.inventarios.push(inventario);
                this.alertService.success('Inventario creado', 'El inventario fue añadido exitosamente.');
            } else {
                this.alertService.success('Inventario actualizado', 'El inventario fue actualizado exitosamente.');
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

    obtenerIdSucursal(id:number) {
        this.inventario.id_sucursal = this.bodegas.find((bodega:any) => bodega.id == id).id_sucursal;
    }

}
