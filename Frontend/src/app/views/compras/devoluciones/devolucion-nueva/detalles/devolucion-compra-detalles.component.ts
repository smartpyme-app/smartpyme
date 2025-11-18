import { Component, OnInit, EventEmitter, Input, Output, TemplateRef, ViewChild, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';

import Swal from 'sweetalert2';

@Component({
    selector: 'app-devolucion-compra-detalles',
    templateUrl: './devolucion-compra-detalles.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    
})
export class DevolucionCompraDetallesComponent extends BaseModalComponent implements OnInit {

    @Input() devolucion: any = {};
    public detalle:any = {};
    public supervisor:any = {};
    public todosSeleccionados:boolean = false;

    @Output() update = new EventEmitter();
    @Output() sumTotal = new EventEmitter();

    @ViewChild('msupervisor')
    public supervisorTemplate!: TemplateRef<any>;

    public buscador:string = '';
    public override loading:boolean = false;

    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

    constructor( 
        private apiService: ApiService,
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService
    ) {
        super(modalManager, alertService);
    }

    ngOnInit() {

    }

    openModalEdit(template: TemplateRef<any>, detalle:any) {
        this.detalle = detalle;
        this.openModal(template, {class: 'modal-md', backdrop: 'static'});
    }

    public updateTotal(detalle:any){
        detalle.total  = (parseFloat(detalle.cantidad) * parseFloat(detalle.costo) - parseFloat(detalle.descuento)).toFixed(2);
        detalle.total_costo  = (parseFloat(detalle.cantidad) * parseFloat(detalle.costo)).toFixed(2);
        this.update.emit(this.devolucion);
    }

    public modalSupervisor(detalle:any){
        this.detalle = detalle;
        this.openModal(this.supervisorTemplate, {class: 'modal-xs'});
    }

    public supervisorCheck(){
        this.loading = true;
        this.apiService.store('usuario-validar', this.supervisor)
          .pipe(this.untilDestroyed())
          .subscribe(supervisor => {
            this.closeModal();
            this.delete(this.detalle);
            this.loading = false;
            this.supervisor = {};
        },error => {this.alertService.error(error); this.loading = false; });
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
                let indexAEliminar:any;
                
                    indexAEliminar = this.devolucion.detalles.findIndex((item:any) => item.id_producto === detalle.id_producto);
                    if (indexAEliminar !== -1) {
                        this.devolucion.detalles.splice(indexAEliminar, 1);
                        this.update.emit(this.devolucion);
                    }
              } else if (result.dismiss === Swal.DismissReason.cancel) {
                // Swal.fire('Cancelado', 'Tu archivo está seguro :)', 'info');
              }
            });

        }

    public sumTotalEmit(){
        this.sumTotal.emit();
    }

    seleccionarTodos(event: any) {
        this.todosSeleccionados = event.target.checked;
        this.devolucion.detalles.forEach((detalle: any) => {
            detalle.seleccionado = this.todosSeleccionados;
        });
    }
    actualizarSeleccion() {
        this.todosSeleccionados = this.devolucion.detalles.every(
            (detalle: any) => detalle.seleccionado
        );
    }

    haySeleccionados(): boolean {
        return this.devolucion?.detalles?.some((d: any) => !!d.seleccionado) ?? false;
      }

    eliminarSeleccionados(event?: any) {

        if (event) {
            event.preventDefault(); // Prevenir cualquier acción por defecto
        }
        Swal.fire({
            title: '¿Estás seguro?',
            text: 'Se eliminarán todos los productos seleccionados',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                this.devolucion.detalles = this.devolucion.detalles.filter(
                    (detalle: any) => !detalle.seleccionado
                );
                this.todosSeleccionados = false;
                this.update.emit(this.devolucion);
                this.sumTotal.emit();
            }
        });
    }


}
