import { Component, OnInit, EventEmitter, Input, Output, TemplateRef, ViewChild } from '@angular/core';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal/bs-modal-ref.service';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-devolucion-venta-detalles',
  templateUrl: './devolucion-venta-detalles.component.html'
})
export class DevolucionVentaDetallesComponent implements OnInit {

    @Input() devolucion: any = {};
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
        private apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService
    ) { }

    ngOnInit() {

    }

    openModalEdit(template: TemplateRef<any>, detalle:any) {
        this.detalle = detalle;
        this.modalRef = this.modalService.show(template, {class: 'modal-md', backdrop: 'static'});
    }

    public updateTotal(detalle:any){
        if(!this.detalle.exenta){
            this.detalle.exenta = 0;
        }
        if(!this.detalle.no_sujeta){
            this.detalle.no_sujeta = 0;
        }
        
        detalle.total  = (parseFloat(detalle.cantidad) * parseFloat(detalle.precio) - parseFloat(detalle.descuento)).toFixed(2);
        detalle.total_costo  = (parseFloat(detalle.cantidad) * parseFloat(detalle.costo)).toFixed(2);
        this.update.emit(this.devolucion);
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

    // Eliminar detalle
        public delete(detalle:any){
            if (confirm('Confirma eliminar el detalle')) { 

                for (var i = 0; i < this.devolucion.detalles.length; ++i) {
                    if (this.devolucion.detalles[i].producto_id === detalle.producto_id ){
                        this.devolucion.detalles.splice(i, 1);
                        this.update.emit(this.devolucion);
                    }
                }
            }

        }

    public sumTotalEmit(){
        this.sumTotal.emit();
    }


}
