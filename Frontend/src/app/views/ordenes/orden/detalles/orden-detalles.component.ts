import { Component, OnInit, EventEmitter, Input, Output, TemplateRef, ViewChild } from '@angular/core';
import { BsModalService, BsModalRef} from 'ngx-bootstrap/modal';

import { ApiService } from '../../../../services/api.service';
import { AlertService } from '../../../../services/alert.service';

@Component({
  selector: 'app-orden-detalles',
  templateUrl: './orden-detalles.component.html'
})
export class OrdenDetallesComponent implements OnInit {

    @Input() orden: any = {};
    public detalle:any = {};
    public detalle2:any = {};

    @Output() update = new EventEmitter();
    modalRef?: BsModalRef;

    public buscador:string = '';
    @Input() loadingOrden: any = {};
    public loading:boolean = false;

    constructor( 
        public apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService
    ) { }

    ngOnInit() {

    }

    openModalEdit(template: TemplateRef<any>, detalle:any) {
        this.detalle = detalle;
        this.detalle2.cantidad = this.detalle.cantidad;
        this.detalle2.nota = this.detalle.nota;
        this.modalRef = this.modalService.show(template, {class: 'modal-md', backdrop:'static'});
    }

    public setEstado(detalle:any, estado:any):void{
        detalle.estado = estado;
        this.onSubmit(detalle);
    }

    public setCantidad(){
        this.detalle.cantidad = this.detalle2.cantidad;
        this.detalle.nota = this.detalle2.nota;
        this.detalle.total = this.detalle.cantidad * this.detalle.precio;
        this.onSubmit(this.detalle);
        this.modalRef!.hide();
    }

    public onSubmit(detalle:any){
        this.apiService.store('orden/detalle', detalle).subscribe(detalle => {
            this.loading = false;
            detalle = detalle;
            this.update.emit(this.orden);
        },error => {this.alertService.error(error); this.loading = false; });
    }

    // Eliminar detalle
    public eliminarDetalle(detalle:any){
        if (confirm('Confirma que desea eliminar el elemento')) { 
            this.apiService.delete('orden/detalle/', detalle.id).subscribe(detalle => {
                for (var i = 0; i < this.orden.detalles.length; ++i) {
                    if (this.orden.detalles[i].id === detalle.id ){
                        this.orden.detalles.splice(i, 1);
                        this.update.emit(this.orden);
                    }
                }
            },error => {this.alertService.error(error); this.loading = false; });
        }

    }


}
