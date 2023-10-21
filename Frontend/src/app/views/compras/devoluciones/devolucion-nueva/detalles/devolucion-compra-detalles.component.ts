import { Component, OnInit, EventEmitter, Input, Output, TemplateRef, ViewChild } from '@angular/core';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal/bs-modal-ref.service';

import { ApiService } from '../../../../../services/api.service';
import { AlertService } from '../../../../../services/alert.service';

@Component({
  selector: 'app-devolucion-compra-detalles',
  templateUrl: './devolucion-compra-detalles.component.html'
})
export class DevolucionCompraDetallesComponent implements OnInit {

    @Input() compra: any = {};
    public detalle:any = {};
    public supervisor:any = {};
    public cantidad!:any;
    public costo!:any;

    @Output() update = new EventEmitter();
    @Output() sumTotal = new EventEmitter();
    modalRef!: BsModalRef;

    @ViewChild('msupervisor')
    public supervisorTemplate!: TemplateRef<any>;

    public buscador:string = '';
    public loading:boolean = false;

    constructor( 
        private apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService
    ) { }

    ngOnInit() {

    }

    openModalEdit(template: TemplateRef<any>, detalle:any) {
        this.detalle = detalle;
        this.costo = this.detalle.costo;
        this.modalRef = this.modalService.show(template, {class: 'modal-sm'});
    }

    // Actualizar detalle
    actualizarDetalle(){
        this.detalle.subtotal = (this.detalle.costo - this.detalle.descuento) * this.detalle.cantidad;
        
        if(this.detalle.tipo == "Gravada") {
            this.detalle.iva     = this.detalle.subtotal * 0.13;
        }

        this.detalle.total = this.detalle.iva + this.detalle.subtotal;
        console.log(this.detalle);
        this.update.emit(this.compra);
    }

    public setCantidad(){
        this.detalle.cantidad = this.cantidad;
        this.detalle.costo = this.costo;
        this.actualizarDetalle()
        this.modalRef.hide();
    }

    public setPrecio(costo:any){
        this.costo = costo;
        this.actualizarDetalle();
    }

    public modalSupervisor(detalle:any){
        this.detalle = detalle;
        this.modalRef = this.modalService.show(this.supervisorTemplate, {class: 'modal-xs'});
    }

    public supervisorCheck(){
        this.loading = true;
        this.apiService.store('usuario-validar', this.supervisor).subscribe(supervisor => {
            this.modalRef.hide();
            this.eliminarDetalle(this.detalle);
            this.loading = false;
            this.supervisor = {};
        },error => {this.alertService.error(error); this.loading = false; });
    }

    // Eliminar detalle
        public eliminarDetalle(detalle:any){
            if (confirm('Confirma que desea quitar el detalle')) { 

                for (var i = 0; i < this.compra.detalles.length; ++i) {
                    if (this.compra.detalles[i].producto_id === detalle.producto_id ){
                        this.compra.detalles.splice(i, 1);
                        this.update.emit(this.compra);
                    }
                }
            }


        }

    public sumTotalEmit(){
        this.sumTotal.emit();
    }


}
