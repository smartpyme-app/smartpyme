import { Component, OnInit, EventEmitter, Input, Output, TemplateRef, ViewChild } from '@angular/core';
import { BsModalService, BsModalRef} from 'ngx-bootstrap/modal';

import { ApiService } from '../../../../services/api.service';
import { AlertService } from '../../../../services/alert.service';

@Component({
  selector: 'app-compra-detalles',
  templateUrl: './compra-detalles.component.html'
})
export class CompraDetallesComponent implements OnInit {

    @Input() compra: any = {};
    public detalle: any = {};
    public detalleModificado: any = {};
    public cantidad!:number;

    @Output() update = new EventEmitter();
    @Output() sumTotal = new EventEmitter();
    modalRef?: BsModalRef;

    public buscador:string = '';
    public loading:boolean = false;

    constructor( 
        public apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService
    ) { }

    ngOnInit() {

    }

    public openModalEdit(template: TemplateRef<any>, detalle:any) {
        this.detalle = detalle;
        this.modalRef = this.modalService.show(template, {class: 'modal-lg', backdrop: 'static'});
    }

    public calcularByCosto(){
        if (!this.detalle.exenta) { this.detalle.exenta = 0; }
        if (!this.detalle.gravada) { this.detalle.gravada = 0; }
        if (!this.detalle.no_sujeta) { this.detalle.no_sujeta = 0; }
        if (!this.detalle.iva) { this.detalle.iva = 0; }
        if(this.detalle.costo) {
            this.detalle.costo_iva  = (parseFloat(this.detalle.costo) + (this.detalle.costo * 0.13)).toFixed(2);
            this.detalle.subtotal = ((this.detalle.cantidad * this.detalle.costo) - this.detalle.descuento).toFixed(2);
            this.detalle.gravada = ((this.detalle.cantidad * this.detalle.costo) - this.detalle.descuento).toFixed(2);
        
            this.detalle.iva = (this.detalle.subtotal * this.detalle.impuesto).toFixed(2);        
            this.detalle.total = (parseFloat(this.detalle.subtotal) + parseFloat(this.detalle.exenta) + parseFloat(this.detalle.no_sujeta) + parseFloat(this.detalle.iva)).toFixed(2);
        }
  
    }
    
    public updateTotal(){
        if (!this.detalle.exenta) { this.detalle.exenta = 0; }
        if (!this.detalle.gravada) { this.detalle.gravada = 0; }
        if (!this.detalle.no_sujeta) { this.detalle.no_sujeta = 0; }

        if (this.detalle.gravada) {
            let valorIva   = (parseFloat(this.detalle.gravada) + parseFloat(this.detalle.gravada) * parseFloat(this.detalle.impuesto)).toFixed(2);
            this.detalle.gravada   = this.detalle.gravada;
            this.detalle.iva       = (parseFloat(valorIva) - parseFloat(this.detalle.gravada)).toFixed(2);
        }else{
            this.detalle.iva       = 0;
        }
    }

    public onSubmit(){
        this.detalle = {};
        this.sumTotal.emit();
        this.modalRef?.hide();
    }
    
    // Producto o Gasolina
    productoSelect(detalle:any):void{
        this.detalle = Object.assign({}, detalle);
        this.detalle.id = null;
        this.compra.detalles.push(this.detalle);
        this.detalle = {};

        // Mantener el scroll hasta abajo en la lista de productos
        setTimeout(function(){
            var objDiv = document.getElementById("detalles");
            objDiv!.scrollTop = objDiv!.scrollHeight;
        },300);
        
        this.sumTotal.emit();
    }


    // Eliminar detalle
        public delete(detalle:any){
            if (confirm('Confirma que desea eliminar el elemento')) { 
                if(detalle.id) {
                    this.apiService.delete('compra/detalle/', detalle.id).subscribe(detalle => {
                        for (var i = 0; i < this.compra.detalles.length; ++i) {
                            if (this.compra.detalles[i].id === detalle.id ){
                                this.compra.detalles.splice(i, 1);
                                this.update.emit(this.compra);
                            }
                        }
                    },error => {this.alertService.error(error); this.loading = false; });
                }else{

                    for (var i = 0; i < this.compra.detalles.length; ++i) {
                        if (this.compra.detalles[i].producto_id === detalle.producto_id ){
                            this.compra.detalles.splice(i, 1);
                            this.update.emit(this.compra);
                        }
                    }
                }
            }

        }

    public sumTotalEmit(){
        this.sumTotal.emit();
    }

}
