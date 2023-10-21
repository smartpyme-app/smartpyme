import { Component, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { SumPipe }     from '@pipes/sum.pipe';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-devolucion-nueva',
  templateUrl: './devolucion-nueva.component.html',
  providers: [ SumPipe ]
})

export class DevolucionVentaNuevaComponent implements OnInit {

    public venta: any= {};
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

        const id = +this.route.snapshot.queryParamMap.get('venta_id')!;

        if(id == 0){
            this.cargarDatosIniciales();
        }
        else{
            this.loading = true;
            this.venta.cliente = {};
            this.cargarDocumentos();
            this.apiService.read('venta/', id).subscribe(venta => {
                this.venta = venta;
                this.venta.id = null;
                this.venta.fecha = this.apiService.date();
                this.venta.venta_id = id;
                this.venta.tipo = 'Interna';

                this.venta.percepcion = this.venta.iva_percibido > 0 ? true : false; 
                this.venta.retencion = this.venta.iva_retenido > 0 ? true : false;

                let corte = JSON.parse(sessionStorage.getItem('worder_corte')!);
                if (corte) {
                    this.venta.caja_id = JSON.parse(sessionStorage.getItem('worder_corte')!).caja_id;
                    this.venta.corte_id = JSON.parse(sessionStorage.getItem('worder_corte')!).id;
                }
                this.venta.usuario_id = this.apiService.auth_user().id;
                this.venta.sucursal_id = this.apiService.auth_user().sucursal_id;
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
        this.venta = {};
        this.venta.fecha = JSON.parse(sessionStorage.getItem('worder_corte')!).fecha;
        this.venta.tipo = 'Interna';
        this.venta.caja_id = JSON.parse(sessionStorage.getItem('worder_corte')!).caja_id;
        this.venta.corte_id = JSON.parse(sessionStorage.getItem('worder_corte')!).id;
        this.venta.cliente = {};
        this.venta.detalles = [];
        this.venta.canal = 'Tienda';
        this.venta.descuento = 0;
        this.detalle = {};
        this.sumTotal();
        this.venta.usuario_id = this.apiService.auth_user().id;
        this.venta.sucursal_id = this.apiService.auth_user().sucursal_id;
        this.imprimir = true;
    }

    public sumTotal() {
        this.venta.subtotal = (parseFloat(this.sumPipe.transform(this.venta.detalles, 'subtotal'))).toFixed(2);
        this.venta.iva_percibido = this.venta.percepcion ? this.venta.subtotal * 0.01 : 0; 
        this.venta.iva_retenido = this.venta.retencion ? this.venta.subtotal * 0.01 : 0; 

        this.venta.iva = (parseFloat(this.sumPipe.transform(this.venta.detalles, 'iva'))).toFixed(2);
        this.venta.descuento = (parseFloat(this.sumPipe.transform(this.venta.detalles, 'descuento') + parseFloat(this.venta.descuento))).toFixed(2);
        this.venta.subcosto = (parseFloat(this.sumPipe.transform(this.venta.detalles, 'subcosto'))).toFixed(2);
        this.venta.no_sujeta = (parseFloat(this.sumPipe.transform(this.venta.detalles, 'no_sujeta'))).toFixed(2);
        this.venta.exenta = (parseFloat(this.sumPipe.transform(this.venta.detalles, 'exenta'))).toFixed(2);
        this.venta.gravada = (parseFloat(this.sumPipe.transform(this.venta.detalles, 'gravada'))).toFixed(2);
        this.venta.total = (parseFloat(this.sumPipe.transform(this.venta.detalles, 'total')) + parseFloat(this.venta.iva_percibido) - parseFloat(this.venta.iva_retenido)).toFixed(2);
    }


    updateVenta(venta:any) {
        this.venta = venta;
        this.sumTotal();
    }

    // Devolución
        openModalDevolucion(template: TemplateRef<any>) {
            this.modalRef = this.modalService.show(template, {class: 'modal-sm'});
            this.venta.tipo = 'Cambio de producto';
        }

        public onDevolucion() {

            this.loading = true;
            this.apiService.store('devolucion-venta', this.venta).subscribe(venta => {
                this.loading = false;
                if(venta.tipo_documento == 'Factura' || venta.tipo_documento == 'Credito Fiscal' || venta.tipo_documento == 'Ticket'){
                    this.imprimirDocDevolucion(venta);
                }
                this.router.navigate(['/devoluciones/ventas']);
                this.alertService.success("Guardado");
            },error => {this.alertService.error(error); this.loading = false; });
        }


    public imprimirDocDevolucion(venta:any){
        setTimeout(()=>{
            window.open(this.apiService.baseUrl + '/api/reporte/devolucion/' + venta.id + '?token=' + this.apiService.auth_token(), 'hola', 'width=400');
        }, 1000);
    }


}
