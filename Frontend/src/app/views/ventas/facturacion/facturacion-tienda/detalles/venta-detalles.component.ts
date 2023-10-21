import { Component, OnInit, EventEmitter, Input, Output, TemplateRef, ViewChild } from '@angular/core';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal/bs-modal-ref.service';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-venta-detalles',
  templateUrl: './venta-detalles.component.html'
})
export class VentaDetallesComponent implements OnInit {

    @Input() venta: any = {};
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

    public updateTotal(){
        this.detalle.subtotal  = (parseFloat(this.detalle.cantidad) * parseFloat(this.detalle.precio)).toFixed(2);

        if (!this.detalle.precio) { this.detalle.precio = 0; }
        if (!this.detalle.no_sujeta) { this.detalle.no_sujeta = 0; }
        if (!this.detalle.exenta) { this.detalle.exenta = 0; }
        if (!this.detalle.gravada) { this.detalle.gravada = 0; }

        this.detalle.gravada  = this.detalle.subtotal;
        
        this.detalle.iva = 0;
        if(this.detalle.gravada) {
            let valorIva   = (parseFloat(this.detalle.gravada) + parseFloat(this.detalle.gravada) * parseFloat(this.detalle.impuesto)).toFixed(2);
            this.detalle.iva       = (parseFloat(valorIva) - parseFloat(this.detalle.gravada)).toFixed(2);
        }
        
        this.detalle.total     = (parseFloat(this.detalle.gravada) + parseFloat(this.detalle.no_sujeta) + parseFloat(this.detalle.exenta) + parseFloat(this.detalle.iva)).toFixed(2);
        this.update.emit(this.venta);
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

    // Agregar detalle
        productoSelect(producto:any):void{
            this.detalle = Object.assign({}, producto);
            this.detalle.id = null;
            
            // Verifica si el producto ya fue ingresado
            let detalle = this.venta.detalles.find((x:any) => x.producto_id == this.detalle.producto_id);
            
            if(detalle) {
                this.detalle = detalle;
                this.detalle.cantidad += producto.cantidad;
            }
            this.detalle.subcosto = (this.detalle.costo * this.detalle.cantidad);

            // Impuestos
                this.detalle.iva         = 0;
                this.detalle.exenta      = 0;
                this.detalle.gravada     = 0;
                this.detalle.no_sujeta   = 0;
                this.detalle.total       = 0;
                this.detalle.subtotal    = 0;

                this.detalle.subtotal  = (parseFloat(this.detalle.cantidad) * parseFloat(this.detalle.precio)).toFixed(2);

                if(this.detalle.tipo_impuesto == "Gravada") {
                    let valorIva   = (parseFloat(this.detalle.subtotal) + parseFloat(this.detalle.subtotal) * parseFloat(this.detalle.impuesto)).toFixed(2);
                    this.detalle.gravada   = this.detalle.subtotal;
                    this.detalle.iva       = (parseFloat(valorIva) - parseFloat(this.detalle.subtotal)).toFixed(2);
                }
                if(this.detalle.tipo_impuesto == "Exenta") {
                    this.detalle.exenta = this.detalle.subtotal;
                }
                if(this.detalle.tipo_impuesto == "No Sujeta") {
                    this.detalle.no_sujeta = this.detalle.subtotal;
                }
                this.detalle.total = (parseFloat(this.detalle.subtotal) + parseFloat(this.detalle.exenta) + parseFloat(this.detalle.no_sujeta) + parseFloat(this.detalle.iva)).toFixed(2);
            
            
            if(!detalle)
                this.venta.detalles.push(this.detalle);

            // Mantener el scroll hasta abajo en la lista de productos
            setTimeout(function(){
                var objDiv = document.getElementById("detalles")!;
                objDiv.scrollTop = objDiv.scrollHeight;
            },300);

            this.update.emit(this.venta);
            
            this.detalle = {};
            if (this.modalRef) { this.modalRef.hide() }

        }

    // Eliminar detalle
        public eliminarDetalle(detalle:any){
            // if (confirm('Confirma que desea cerrar el turno en caja')) { 

            if(detalle.id) {
                this.apiService.delete('venta/detalle/', detalle.id).subscribe(detalle => {
                    for (var i = 0; i < this.venta.detalles.length; ++i) {
                        if (this.venta.detalles[i].producto_id === detalle.producto_id ){
                            this.venta.detalles.splice(i, 1);
                            this.update.emit(this.venta);
                        }
                    }
                },error => {this.alertService.error(error); this.loading = false; });
            }else{

                for (var i = 0; i < this.venta.detalles.length; ++i) {
                    if (this.venta.detalles[i].producto_id === detalle.producto_id ){
                        this.venta.detalles.splice(i, 1);
                        this.update.emit(this.venta);
                    }
                }
            }

        }

    public sumTotalEmit(){
        this.sumTotal.emit();
    }


}
