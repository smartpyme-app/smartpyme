import { Component, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { SumPipe }     from '../../../../pipes/sum.pipe';
import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';

@Component({
  selector: 'app-devolucion-compra-nueva',
  templateUrl: './devolucion-compra-nueva.component.html',
  providers: [ SumPipe ]
})

export class DevolucionCompraNuevaComponent implements OnInit {

    public compra: any= {};
    public detalle: any = {};
    public documentos:any = [];
    public supervisor:any = {};
    public loading = false;
    public imprimir:boolean = true;
    
    modalRef!: BsModalRef;
    
	constructor( 
	    public apiService: ApiService, private alertService: AlertService,
	    private modalService: BsModalService, private sumPipe:SumPipe,
        private route: ActivatedRoute, private router: Router,
	) {
        this.router.routeReuseStrategy.shouldReuseRoute = function() {return false; };
    }

	ngOnInit() {

        const id = +this.route.snapshot.queryParamMap.get('compra_id')!;

        if(id == 0){
            this.cargarDatosIniciales();
        }
        else{
            this.loading = true;
            this.compra.cliente = {};
            this.cargarDocumentos();
            this.apiService.read('compra/', id).subscribe(compra => {
                this.compra = compra;
                this.compra.id = null;
                this.compra.compra_id = id;
                this.compra.fecha = this.apiService.date();
                this.compra.tipo = 'Interna';
                this.compra.usuario_id = this.apiService.auth_user().id;
                this.compra.sucursal_id = this.apiService.auth_user().sucursal_id;
                this.sumTotal();
                this.loading = false;
            }, error => {this.alertService.error(error);this.loading = false;});
        }

    }

    cargarDocumentos(){
        this.apiService.getAll('documentos').subscribe(documentos => {
            this.documentos = documentos;
        }, error => {this.alertService.error(error);});
    }

    cargarDatosIniciales(){
        this.cargarDocumentos();
        this.compra = {};
        this.compra.fecha = JSON.parse(sessionStorage.getItem('worder_corte')!).fecha;
        this.compra.tipo = 'Interna';
        this.compra.caja_id = JSON.parse(sessionStorage.getItem('worder_corte')!).caja_id;
        this.compra.corte_id = JSON.parse(sessionStorage.getItem('worder_corte')!).id;
        this.compra.cliente = {};
        this.compra.detalles = [];
        this.compra.canal = 'Tienda';
        this.compra.descuento = 0;
        this.detalle = {};
        this.sumTotal();
        this.compra.usuario_id = this.apiService.auth_user().id;
        this.compra.sucursal_id = this.apiService.auth_user().sucursal_id;
        this.imprimir = true;
    }

    public sumTotal() {
        if(this.compra.retencion) { 
            this.compra.iva_retenido = parseFloat((this.compra.subtotal * 0.01).toFixed(2));
        }else{
            this.compra.iva_retenido = 0;            
        }
        this.compra.total = this.sumPipe.transform(this.compra.detalles, 'total') + this.compra.iva_retenido - this.compra.descuento;
        
        this.compra.subcosto = this.sumPipe.transform(this.compra.detalles, 'subcosto');        
        this.compra.subtotal = (this.compra.total - this.compra.iva_retenido) / 1.13;
        
        this.compra.iva = this.compra.subtotal * 0.13;
    }


    updateVenta(compra:any) {
        this.compra = compra;
        this.sumTotal();
    }

    // Devolución
        openModalDevolucion(template: TemplateRef<any>) {
            this.modalRef = this.modalService.show(template, {class: 'modal-sm'});
            this.compra.tipo = 'Cambio de producto';
        }

        public onDevolucion() {

            this.loading = true;
            this.apiService.store('devolucion-compra', this.compra).subscribe(compra => {
                this.loading = false;
                this.router.navigate(['/devoluciones/compras']);
                this.alertService.success("Guardado");
            },error => {this.alertService.error(error); this.loading = false; });
        }



}
