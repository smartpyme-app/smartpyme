import { Component, OnInit, EventEmitter, Input, Output, TemplateRef, ViewChild } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { Subject } from 'rxjs';
import { debounceTime } from 'rxjs/operators';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

import Swal from 'sweetalert2';

@Component({
  selector: 'app-partida-detalles',
  templateUrl: './partida-detalles.component.html'
})
export class PartidaDetallesComponent implements OnInit {

    @Input() partida: any = {};
    public detalle:any = {};
    public catalogo:any = [];

    @Output() update = new EventEmitter();
    @Output() sumTotal = new EventEmitter();
    @Output() onTotalesActualizados = new EventEmitter<any>();
    modalRef!: BsModalRef;

    public buscador:string = '';
    public loading:boolean = false;
    public loadingMasDetalles:boolean = false;
    
    // Tracking de detalles modificados
    public detallesModificados: Set<number> = new Set();
    
    // Subject para debounce del recálculo de totales
    private recalcularTotalesDebounced = new Subject<void>();

    // Exponer Math y parseFloat para usar en el template
    Math = Math;
    parseFloat = parseFloat;

    constructor( 
        public apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService
    ) { }

    ngOnInit() {
        this.apiService.getAll('catalogo/list').subscribe(catalogo => {
            this.catalogo = catalogo;
        }, error => {this.alertService.error(error);});
        
        // Suscribirse al subject para recálculo debounced de totales
        this.recalcularTotalesDebounced.pipe(
            debounceTime(500) // Esperar 500ms después del último cambio
        ).subscribe(() => {
            this.recalcularTotales();
        });
    }
    
    /**
     * Obtener IDs de detalles modificados
     */
    public getDetallesModificados(): number[] {
        return Array.from(this.detallesModificados);
    }
    
    /**
     * Limpiar el tracking de detalles modificados
     */
    public limpiarDetallesModificados(): void {
        this.detallesModificados.clear();
    }
    
    /**
     * Recalcular totales llamando al backend
     */
    private recalcularTotales(): void {
        if (!this.partida.id) {
            // Si no tiene ID, es una partida nueva, usar cálculo local
            return;
        }
        
        // Obtener solo los detalles modificados con sus valores actuales
        const detallesModificados = (this.partida.detalles || [])
            .filter((d: any) => d.id && this.detallesModificados.has(d.id))
            .map((d: any) => ({
                id: d.id,
                debe: d.debe,
                haber: d.haber
            }));
        
        if (detallesModificados.length === 0) {
            return;
        }
        
        console.log('Recalculando totales con detalles modificados:', detallesModificados.length);
        
        this.apiService.store(`partida/${this.partida.id}/recalcular-totales`, {
            detalles_modificados: detallesModificados
        }).subscribe({
            next: (totales) => {
                console.log('Totales recalculados:', totales);
                // Emitir los nuevos totales al componente padre
                this.onTotalesActualizados.emit({
                    debe: parseFloat(totales.total_debe).toFixed(2),
                    haber: parseFloat(totales.total_haber).toFixed(2),
                    diferencia: parseFloat(totales.diferencia).toFixed(2)
                });
            },
            error: (error) => {
                console.error('Error al recalcular totales:', error);
                // No mostrar error al usuario, solo loguear
            }
        });
    }

    public selectCuenta(){
        let cuenta = this.catalogo.find((item:any) => item.id == this.detalle.id_cuenta);
        this.detalle.codigo = cuenta.codigo;
        this.detalle.nombre_cuenta = cuenta.nombre;
    }

    public updateTotal(detalle:any){
        // Si el detalle tiene ID, marcarlo como modificado
        if (detalle.id) {
            this.detallesModificados.add(detalle.id);
            console.log('Detalle marcado como modificado:', detalle.id);
        }
        
        // Si es una partida existente, disparar recálculo debounced
        if (this.partida.id) {
            this.recalcularTotalesDebounced.next();
        }
        
        // Mantener lógica original para partidas nuevas
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

    public openModal(template: TemplateRef<any>, detalle:any){
        this.detalle = detalle;
        console.log(this.detalle);
        this.modalRef = this.modalService.show(template, {class: 'modal-md', backdrop: 'static'});
    }


    public onsubmit(){
        this.partida.detalles.push(this.detalle);

        this.update.emit(this.partida);
        this.detalle = {};

        if (this.modalRef) { this.modalRef.hide() }
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
                        this.apiService.delete('partida/detalle/', detalle.id).subscribe(detalle => {
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
    
    /**
     * Cargar más detalles - este método será sobrescrito por el componente padre
     */
    public cargarMasDetalles() {
        // La implementación real está en el componente padre
        // Este método existe solo para evitar errores de compilación
    }

}
