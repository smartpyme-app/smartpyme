import { Component, OnInit, EventEmitter, Input, Output, TemplateRef, ViewChild, inject, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
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
    changeDetection: ChangeDetectionStrategy.OnPush,
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
        protected override modalManager: ModalManagerService,
        private cdr: ChangeDetectorRef
    ) {
        super(modalManager, alertService);
    }

    ngOnInit() {
        this.apiService.getAll('catalogo/list')
          .pipe(this.untilDestroyed())
          .subscribe(catalogo => {
            this.catalogo = catalogo;
            this.cdr.markForCheck();
        }, error => {this.alertService.error(error); this.cdr.markForCheck();});
    }

    public selectCuenta(){
        let cuenta = this.catalogo.find((item:any) => item.id == this.detalle.id_cuenta);
        if (cuenta) {
            this.detalle.codigo = cuenta.codigo;
            this.detalle.nombre_cuenta = cuenta.nombre;
        }
    }
    
    /**
     * Se llama cuando se cambia la cuenta de un detalle en la tabla
     */
    public onCuentaChange(detalle: any) {
        // Actualizar código y nombre de cuenta
        let cuenta = this.catalogo.find((item:any) => item.id == detalle.id_cuenta);
        if (cuenta) {
            detalle.codigo = cuenta.codigo;
            detalle.nombre_cuenta = cuenta.nombre;
        }
        // Marcar como modificado
        this.marcarComoModificado(detalle);
    }
    
    /**
     * Marcar un detalle como modificado cuando se edita cualquier campo
     */
    public marcarComoModificado(detalle: any) {
        if (detalle && detalle.id) {
            this.detallesModificados.add(detalle.id);
            console.log('Detalle marcado como modificado:', detalle.id);
        }
    }

    /**
     * Método legacy - ya no se usa, se reemplazó por onDetalleChange()
     * Se mantiene por compatibilidad pero no debería ser llamado
     */
    public updateTotal(detalle:any){
        // Este método tenía lógica de ventas/compras que no aplica a partidas contables
        // Ahora se usa onDetalleChange() que maneja correctamente debe/haber
        console.warn('updateTotal() está deprecado, usar onDetalleChange()');
        this.onDetalleChange(detalle);
    }
    
    /**
     * Se llama cuando se modifica debe o haber de un detalle
     */
    public onDetalleChange(detalle: any) {
        // Inicializar valores de debe y haber si están vacíos o undefined
        if (!detalle.debe || detalle.debe === '' || detalle.debe === null) {
            detalle.debe = 0;
        }
        if (!detalle.haber || detalle.haber === '' || detalle.haber === null) {
            detalle.haber = 0;
        }
        
        // Convertir a número y asegurar que sean valores numéricos válidos
        detalle.debe = parseFloat(detalle.debe) || 0;
        detalle.haber = parseFloat(detalle.haber) || 0;
        
        // Marcar el detalle como modificado si tiene ID
        this.marcarComoModificado(detalle);
        
        // Emitir actualización de la partida
        this.cdr.markForCheck();
        this.update.emit(this.partida);
        
        // Si es una partida nueva (sin ID), recalcular localmente inmediatamente
        if (!this.partida.id) {
            this.sumTotal.emit();
            return;
        }
        
        // Para partidas existentes con detalles nuevos (sin ID), recalcular localmente
        // porque los nuevos detalles no están en el backend todavía
        if (!detalle.id) {
            this.sumTotal.emit();
            return;
        }
        
        // Para partidas existentes con detalles que tienen ID, usar debounce y recalcular desde el backend
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
                    
                    this.cdr.markForCheck();
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
        // Inicializar valores de debe y haber si están vacíos o undefined
        if (!this.detalle.debe || this.detalle.debe === '' || this.detalle.debe === null) {
            this.detalle.debe = 0;
        }
        if (!this.detalle.haber || this.detalle.haber === '' || this.detalle.haber === null) {
            this.detalle.haber = 0;
        }
        
        // Convertir a número y asegurar que sean valores numéricos válidos
        this.detalle.debe = parseFloat(this.detalle.debe) || 0;
        this.detalle.haber = parseFloat(this.detalle.haber) || 0;
        
        // Asegurar que el array de detalles existe
        if (!this.partida.detalles) {
            this.partida.detalles = [];
        }
        
        // Agregar el detalle al array
        this.partida.detalles.push(this.detalle);

        this.cdr.markForCheck();
        this.update.emit(this.partida);
        
        // Recalcular totales después de agregar la fila
        // Esto funciona tanto para partidas nuevas como existentes con detalles nuevos
        this.sumTotal.emit();
        
        // Limpiar el detalle para el próximo uso
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
                            let indexAEliminar = this.partida.detalles.findIndex((item:any) => item.id_cuenta === detalle.id_cuenta);
                            if (indexAEliminar !== -1) {
                                this.partida.detalles.splice(indexAEliminar, 1);
                            }
                            this.alertService.success('Detalle eliminado', 'El detalle fue eliminado exitosamente.');
                            this.cdr.markForCheck();
                            this.update.emit(this.partida);
                            
                            // Si es una partida nueva, recalcular totales después de eliminar
                            if (!this.partida.id) {
                                this.sumTotal.emit();
                            }
                        }, error => {this.alertService.error(error); this.cdr.markForCheck(); });
                    }else{
                        let indexAEliminar = this.partida.detalles.findIndex((item:any) => item.id_cuenta === detalle.id_cuenta);
                        if (indexAEliminar !== -1) {
                            this.partida.detalles.splice(indexAEliminar, 1);
                        }
                        this.alertService.success('Detalle eliminado', 'El detalle fue eliminado exitosamente.');
                        this.cdr.markForCheck();
                        this.update.emit(this.partida);
                        
                        // Si es una partida nueva, recalcular totales después de eliminar
                        if (!this.partida.id) {
                            this.sumTotal.emit();
                        }
                    }
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
