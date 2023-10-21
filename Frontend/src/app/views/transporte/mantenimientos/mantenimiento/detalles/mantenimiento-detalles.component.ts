import { Component, OnInit, EventEmitter, Input, Output, TemplateRef, ViewChild } from '@angular/core';
import { BsModalService, BsModalRef} from 'ngx-bootstrap/modal';

import { ApiService } from '../../../../../services/api.service';
import { AlertService } from '../../../../../services/alert.service';

@Component({
  selector: 'app-mantenimiento-detalles',
  templateUrl: './mantenimiento-detalles.component.html'
})
export class MantenimientoDetallesComponent implements OnInit {

    @Input() mantenimiento: any = {};
    public detalle: any = {};
    public detalleModificado: any = {};
    public cantidad!:number;

    @Output() update = new EventEmitter();
    modalRef?: BsModalRef;

    public buscador:string = '';
    public loading:boolean = false;

    constructor( 
        public apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService
    ) { }

    ngOnInit() {

    }

    public editarDetalle(template: TemplateRef<any>, detalle:any) {
        this.detalle = detalle;
        this.detalleModificado.producto_id = this.detalle.producto_id;
        this.detalleModificado.tipo = this.detalle.tipo;
        this.detalleModificado.costo = this.detalle.costo;
        this.detalleModificado.cantidad = this.detalle.cantidad;
        this.modalRef = this.modalService.show(template, {class: 'modal-md', backdrop: 'static'});
    }

    // Producto o Gasolina
    productoSelect(detalle:any):void{
        this.mantenimiento.detalles.push(detalle);
        this.update.emit(this.mantenimiento);
    }

    public guardarDetalle() {
        this.detalle.cantidad = this.detalleModificado.cantidad;
        this.detalle.costo = this.detalleModificado.costo;
        this.detalle.total = this.detalle.cantidad * this.detalle.costo;
        this.modalRef?.hide();
        this.update.emit(this.mantenimiento);
    }

    calcular(){
        this.detalleModificado.total = (parseFloat(this.detalleModificado.costo) * parseFloat(this.detalleModificado.cantidad)).toFixed(2);
    }
    
    // Eliminar detalle
        public eliminarDetalle(detalle:any){
            if (confirm('Confirma que desea eliminar el elemento')) { 
                if(detalle.id) {
                    this.apiService.delete('mantenimiento/detalle/', detalle.id).subscribe(detalle => {
                        for (var i = 0; i < this.mantenimiento.detalles.length; ++i) {
                            if (this.mantenimiento.detalles[i].id === detalle.id ){
                                this.mantenimiento.detalles.splice(i, 1);
                                this.update.emit(this.mantenimiento);
                            }
                        }
                    },error => {this.alertService.error(error); this.loading = false; });
                }else{

                    for (var i = 0; i < this.mantenimiento.detalles.length; ++i) {
                        if (this.mantenimiento.detalles[i].producto_id === detalle.producto_id ){
                            this.mantenimiento.detalles.splice(i, 1);
                            this.update.emit(this.mantenimiento);
                        }
                    }
                }
            }

        }

}
