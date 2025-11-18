import { Component, OnInit, EventEmitter, Input, Output, TemplateRef, ViewChild, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { NgSelectModule } from '@ng-select/ng-select';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

import Swal from 'sweetalert2';

@Component({
    selector: 'app-partida-detalles',
    templateUrl: './partida-detalles.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule],
    
})
export class PartidaDetallesComponent implements OnInit {

    @Input() partida: any = {};
    public detalle:any = {};
    public catalogo:any = [];

    @Output() update = new EventEmitter();
    @Output() sumTotal = new EventEmitter();
    modalRef!: BsModalRef;

    public buscador:string = '';
    public loading:boolean = false;

    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

    constructor( 
        public apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService
    ) { }

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


}
