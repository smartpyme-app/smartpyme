import { Component, OnInit, EventEmitter, Input, Output, TemplateRef, ViewChild, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';

import Swal from 'sweetalert2';

@Component({
    selector: 'app-partida-detalles',
    templateUrl: './partida-detalles.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule],
    
})
export class PartidaDetallesComponent extends BaseModalComponent implements OnInit {

    @Input() partida: any = {};
    public detalle:any = {};
    public catalogo:any = [];

    @Output() update = new EventEmitter();
    @Output() sumTotal = new EventEmitter();
    @Output() cargarMas = new EventEmitter();
    @Output() totalesActualizados = new EventEmitter();

    public buscador:string = '';
    public override loading:boolean = false;
    private detallesModificados: Set<number> = new Set(); // IDs de detalles modificados
    private recalcularTotalesTimeout: any = null; // Para debounce

    constructor( 
        public apiService: ApiService,
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService
    ) {
        super(modalManager, alertService);
    }

    ngOnInit() {
        this.apiService.getAll('catalogo/list')
          .pipe(this.untilDestroyed())
          .subscribe(catalogo => {
            this.catalogo = catalogo;
        }, error => {this.alertService.error(error);});
    }

    public selectCuenta(){
        let cuenta = this.catalogo.find((item:any) => item.id == this.detalle.id_cuenta);
        this.detalle.codigo = cuenta.codigo;
        this.detalle.nombre_cuenta = cuenta.nombre;
    }

    public updateTotal(detalle:any){
        if(!detalle.cantidad){
            detalle.cantidad = 0;
        }
        if(detalle.descuento_porcentaje){
            detalle.descuento = detalle.cantidad * (detalle.precio * (detalle.descuento_porcentaje / 100));
        }else{
            detalle.descuento = 0;
        }

        detalle.total_costo  = (parseFloat(detalle.cantidad) * parseFloat(detalle.costo)).toFixed(4);
        detalle.total  = (parseFloat(detalle.cantidad) * parseFloat(detalle.precio) - parseFloat(detalle.descuento)).toFixed(4);
        this.update.emit(this.partida);
    }
    
    /**
     * Se llama cuando se modifica debe o haber de un detalle
     */
    public onDetalleChange(detalle: any) {
        // Marcar el detalle como modificado
        if (detalle.id) {
            this.detallesModificados.add(detalle.id);
        }
        
        // Debounce: esperar 500ms antes de recalcular para evitar demasiadas llamadas
        if (this.recalcularTotalesTimeout) {
            clearTimeout(this.recalcularTotalesTimeout);
        }
        
        this.recalcularTotalesTimeout = setTimeout(() => {
            this.recalcularTotales();
        }, 500); // Esperar 500ms después del último cambio
    }
    
    /**
     * Recalcular totales llamando al backend
     * Funciona tanto para partidas con muchos detalles como con pocos
     */
    private recalcularTotales() {
        if (!this.partida.id || this.detallesModificados.size === 0) {
            return;
        }
        
        // Obtener solo los detalles modificados
        const detallesModificados = (this.partida.detalles || [])
            .filter((d: any) => d.id && this.detallesModificados.has(d.id))
            .map((d: any) => ({
                id: d.id,
                debe: d.debe || null,
                haber: d.haber || null
            }));
        
        if (detallesModificados.length === 0) {
            return;
        }
        
        console.log('Recalculando totales con detalles modificados:', {
            cantidad: detallesModificados.length,
            total_detalles: this.partida.pagination?.total || this.partida.detalles?.length || 0
        });
        
        // Llamar al backend para recalcular totales
        // El backend calculará desde TODOS los detalles (modificados + no modificados)
        this.apiService.store(`partida/${this.partida.id}/recalcular-totales`, {
            detalles_modificados: detallesModificados
        })
        .pipe(this.untilDestroyed())
        .subscribe({
            next: (response) => {
                // Actualizar los totales con la respuesta del backend
                if (response.total_debe !== undefined && response.total_haber !== undefined) {
                    this.partida.debe = parseFloat(response.total_debe).toFixed(2);
                    this.partida.haber = parseFloat(response.total_haber).toFixed(2);
                    this.partida.diferencia = parseFloat(response.diferencia).toFixed(2);
                    
                    // Emitir evento para que el componente padre sepa que los totales cambiaron
                    this.totalesActualizados.emit({
                        debe: this.partida.debe,
                        haber: this.partida.haber,
                        diferencia: this.partida.diferencia
                    });
                    
                    console.log('Totales actualizados desde backend:', {
                        debe: this.partida.debe,
                        haber: this.partida.haber,
                        diferencia: this.partida.diferencia,
                        detalles_modificados: detallesModificados.length,
                        total_detalles: this.partida.pagination?.total || this.partida.detalles?.length || 0
                    });
                }
            },
            error: (error) => {
                console.error('Error al recalcular totales:', error);
                // No mostrar error al usuario, solo log
            }
        });
    }
    
    /**
     * Obtener lista de IDs de detalles modificados (para el guardado)
     */
    public getDetallesModificados(): number[] {
        return Array.from(this.detallesModificados);
    }
    
    /**
     * Limpiar lista de detalles modificados
     */
    public limpiarDetallesModificados() {
        this.detallesModificados.clear();
    }

    public override openModal(template: TemplateRef<any>, detalle:any){
        this.detalle = detalle;
        console.log(this.detalle);
        super.openModal(template, {class: 'modal-md', backdrop: 'static'});
    }

    public onsubmit(){
        this.partida.detalles.push(this.detalle);

        this.update.emit(this.partida);
        this.detalle = {};

        if (this.modalRef) { this.closeModal(); }
    }

    // Eliminar detalle
        public delete(detalle:any){

            Swal.fire({
              title: '¿Estás seguro?',
              text: '¡No podrás revertir esto!',
              icon: 'warning',
              showCancelButton: true,
              confirmButtonText: 'Sí, eliminarlo',
              cancelButtonText: 'Cancelar'
            }).then((result) => {
              if (result.isConfirmed) {
                    if(detalle.id) {
                        this.apiService.delete('partida/detalle/', detalle.id)
                          .pipe(this.untilDestroyed())
                          .subscribe(detalle => {
                            let indexAEliminar;
                            if (indexAEliminar !== -1) {
                                indexAEliminar = this.partida.detalles.findIndex((item:any) => item.id_cuenta === detalle.id_cuenta);
                                this.partida.detalles.splice(indexAEliminar, 1);
                            }
                            this.alertService.success('Detalle eliminado', 'El detalle fue eliminado exitosamente.');
                        }, error => {this.alertService.error(error); });
                    }else{
                        let indexAEliminar;
                        if (indexAEliminar !== -1) {
                            indexAEliminar = this.partida.detalles.findIndex((item:any) => item.id_cuenta === detalle.id_cuenta);
                            this.partida.detalles.splice(indexAEliminar, 1);
                        }
                        this.partida.detalles.splice(indexAEliminar, 1);
                        this.alertService.success('Detalle eliminado', 'El detalle fue eliminado exitosamente.');
                    }

                this.update.emit(this.partida);
              } else if (result.dismiss === Swal.DismissReason.cancel) {
                // Swal.fire('Cancelado', 'Tu archivo está seguro :)', 'info');
              }
            });

        }

    public sumTotalEmit(){
        this.sumTotal.emit();
    }
    
    public cargarMasDetalles(){
        this.cargarMas.emit();
    }

}
