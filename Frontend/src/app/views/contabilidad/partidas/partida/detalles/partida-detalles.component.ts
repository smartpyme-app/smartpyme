import { Component, OnInit, EventEmitter, Input, Output, TemplateRef, ViewChild } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

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
    modalRef!: BsModalRef;

    public buscador:string = '';
    public loading:boolean = false;

    rows = [{noQuestion : 0}];

    constructor( 
        public apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService
    ) { }

    ngOnInit() {
        this.apiService.getAll('catalogo/list').subscribe(catalogo => {
            this.catalogo = catalogo;
        }, error => {this.alertService.error(error);});
    }

    openModalEdit(template: TemplateRef<any>, detalle:any) {
        this.detalle = detalle;
        this.modalRef = this.modalService.show(template, {class: 'modal-md', backdrop: 'static'});
    }

    public selectCuenta(){
        this.detalle.id_cuenta = this.detalle.cuenta.id;
        this.detalle.codigo = this.detalle.cuenta.codigo;
        this.detalle.nombre_cuenta = this.detalle.cuenta.nombre;
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
                let indexAEliminar;
                    indexAEliminar = this.partida.detalles.findIndex((item:any) => item.id_cuenta === detalle.id_cuenta);
                    if (indexAEliminar !== -1) {
                      this.partida.detalles.splice(indexAEliminar, 1);
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

    public addNewRow() {
        this.rows.push({noQuestion : 0});
      }

    public deleteRows(){
        this.rows.pop();
    }


}
