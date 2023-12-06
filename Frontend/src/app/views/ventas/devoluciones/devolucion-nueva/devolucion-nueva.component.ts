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
    public loading:boolean = false;
    public saving:boolean = false;
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

        const id = +this.route.snapshot.queryParamMap.get('id_venta')!;
        console.log(id);
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
                this.venta.id_venta = id;
                this.venta.tipo = 'Interna';

                this.venta.percepcion = this.venta.iva_percibido > 0 ? true : false; 
                this.venta.retencion = this.venta.iva_retenido > 0 ? true : false;

                let corte = JSON.parse(sessionStorage.getItem('SP_corte')!);
                if (corte) {
                    this.venta.id_caja = JSON.parse(sessionStorage.getItem('SP_corte')!).id_caja;
                    this.venta.id_corte = JSON.parse(sessionStorage.getItem('SP_corte')!).id;
                }
                this.venta.id_usuario = this.apiService.auth_user().id;
                this.venta.id_sucursal = this.apiService.auth_user().id_sucursal;
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
        this.venta.fecha = this.apiService.date();
        this.venta.tipo = 'Interna';
        this.venta.cliente = {};
        this.venta.detalles = [];
        this.venta.canal = 'Tienda';
        this.venta.descuento = 0;
        this.detalle = {};

        let corte = JSON.parse(sessionStorage.getItem('worder_corte')!);
        if (corte) {
            this.venta.fecha = JSON.parse(sessionStorage.getItem('worder_corte')!).fecha;
            this.venta.caja_id = JSON.parse(sessionStorage.getItem('worder_corte')!).id_caja;
            this.venta.corte_id = JSON.parse(sessionStorage.getItem('worder_corte')!).id;
        }

        this.venta.id_usuario = this.apiService.auth_user().id;
        this.venta.id_sucursal = this.apiService.auth_user().id_sucursal;
        // this.sumTotal();
        this.imprimir = true;
    }

    public sumTotal() {
        this.venta.sub_total = (parseFloat(this.sumPipe.transform(this.venta.detalles, 'total'))).toFixed(2);
        this.venta.iva_percibido = this.venta.percepcion ? this.venta.sub_total * 0.01 : 0; 
        this.venta.iva_retenido = this.venta.retencion ? this.venta.sub_total * 0.01 : 0; 

        this.venta.impuestos.forEach((impuesto:any) => {
            if(this.venta.cobrar_impuestos){
                impuesto.monto = this.venta.sub_total * (impuesto.porcentaje / 100);
            }else{
                impuesto.monto = 0;
            }
        });

        this.venta.iva = (parseFloat(this.sumPipe.transform(this.venta.impuestos, 'monto'))).toFixed(2);
        this.venta.descuento = (parseFloat(this.sumPipe.transform(this.venta.detalles, 'descuento'))).toFixed(2);
        // this.venta.total_costo = (parseFloat(this.sumPipe.transform(this.venta.detalles, 'total_costo'))).toFixed(2);
        this.venta.total = (parseFloat(this.venta.sub_total) + parseFloat(this.venta.iva) + parseFloat(this.venta.iva_percibido) - parseFloat(this.venta.iva_retenido)).toFixed(2);
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

            this.saving = true;
            this.apiService.store('devolucion-venta', this.venta).subscribe(venta => {
                this.saving = false;
                if(venta.tipo_documento == 'Factura' || venta.tipo_documento == 'Credito Fiscal' || venta.tipo_documento == 'Ticket'){
                    this.imprimirDocDevolucion(venta);
                }
                this.router.navigate(['/devoluciones/ventas']);
                this.alertService.success('Devolucion de venta creada', 'La devolución de venta fue guardado exitosamente.');
            },error => {this.alertService.error(error); this.saving = false; });
        }


    public imprimirDocDevolucion(venta:any){
        setTimeout(()=>{
            window.open(this.apiService.baseUrl + '/api/reporte/devolucion/' + venta.id + '?token=' + this.apiService.auth_token(), 'hola', 'width=400');
        }, 1000);
    }


}
