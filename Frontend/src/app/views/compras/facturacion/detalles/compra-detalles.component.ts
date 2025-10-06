import { Component, OnInit, EventEmitter, Input, Output, TemplateRef, ViewChild } from '@angular/core';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal/bs-modal-ref.service';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

import Swal from 'sweetalert2';

@Component({
  selector: 'app-compra-detalles',
  templateUrl: './compra-detalles.component.html'
})
export class CompraDetallesComponent implements OnInit {

    @Input() compra: any = {};
    public detalle:any = {};
    public supervisor:any = {};

    @Output() update = new EventEmitter();
    @Output() sumTotal = new EventEmitter();
    modalRef!: BsModalRef;

    @ViewChild('msupervisor')
    public supervisorTemplate!: TemplateRef<any>;

    public buscador:string = '';
    public loading:boolean = false;

    constructor( 
        public apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService
    ) { }

    ngOnInit() {

    }

    openModalEdit(template: TemplateRef<any>, detalle:any) {
        this.detalle = detalle;
        this.modalRef = this.modalService.show(template, {class: 'modal-md', backdrop: 'static'});
    }

    public updateTotal(detalle:any){
        detalle.total  = (parseFloat((detalle.cantidad ?? 0)) * parseFloat((detalle.costo ?? 0)) - parseFloat((detalle.descuento ?? 0))).toFixed(2);
        this.update.emit(this.compra);
    }

    public modalSupervisor(detalle:any){
        this.detalle = detalle;
        this.modalRef = this.modalService.show(this.supervisorTemplate, {class: 'modal-xs'});
    }

    public supervisorCheck(){
        this.loading = true;
        this.apiService.store('usuario-validar', this.supervisor).subscribe(supervisor => {
            this.modalRef.hide();
            this.delete(this.detalle);
            this.loading = false;
            this.supervisor = {};
        },error => {this.alertService.error(error); this.loading = false; });
    }

    // Agregar detalle
        productoSelect(producto:any):void{
            this.detalle = Object.assign({}, producto);
            this.detalle.id = null;
            
            // Verifica si el producto ya fue ingresado
            let detalle = this.compra.detalles.find((x:any) => x.id_producto == this.detalle.id_producto);
            
            if(detalle) {
                this.detalle = detalle;
                this.detalle.cantidad += producto.cantidad;
            }
            this.detalle.total_costo = (this.detalle.costo * this.detalle.cantidad);
            this.detalle.total = (parseFloat(this.detalle.cantidad) * parseFloat(this.detalle.costo) - parseFloat(this.detalle.descuento)).toFixed(2);
            
            
            if(!detalle)
                this.compra.detalles.push(this.detalle);

            this.update.emit(this.compra);
            console.log(this.compra);
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
                    const indexAEliminar = this.compra.detalles.findIndex((item:any) => item.id_producto === detalle.id_producto);
                    if (indexAEliminar !== -1) {
                        if(detalle.id) {
                            this.apiService.delete('compra/detalle/', detalle.id).subscribe(detalle => {
                                this.compra.detalles.splice(indexAEliminar, 1);
                                this.update.emit(this.compra);
                            },error => {this.alertService.error(error); this.loading = false; });
                        }else{
                            this.compra.detalles.splice(indexAEliminar, 1);
                            this.update.emit(this.compra);
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


}
