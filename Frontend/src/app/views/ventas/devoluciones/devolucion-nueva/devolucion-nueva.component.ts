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
    public devolucion: any= {};
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
            this.apiService.read('venta/', id).subscribe(venta => {
                this.venta = venta;
                this.devolucion.detalles = venta.detalles;
                this.devolucion.id_cliente = venta.id_cliente;
                this.devolucion.impuestos = venta.impuestos;
                this.devolucion.fecha = this.apiService.date();
                this.devolucion.id_venta = id;
                this.devolucion.tipo = 'Interna';
                this.devolucion.cuenta_a_terceros = 0;

                this.devolucion.percepcion = parseFloat(this.venta.iva_percibido) > 0 ? true : false; 
                this.devolucion.retencion = parseFloat(this.venta.iva_retenido) > 0 ? true : false;
                this.devolucion.cobrar_impuestos = parseFloat(this.venta.iva) > 0 ? true : false;

                let corte = JSON.parse(sessionStorage.getItem('SP_corte')!);
                if (corte) {
                    this.devolucion.id_caja = JSON.parse(sessionStorage.getItem('SP_corte')!).id_caja;
                    this.devolucion.id_corte = JSON.parse(sessionStorage.getItem('SP_corte')!).id;
                }
                this.devolucion.id_usuario = this.apiService.auth_user().id;
                this.devolucion.id_sucursal = this.apiService.auth_user().id_sucursal;
                this.devolucion.id_empresa = this.apiService.auth_user().id_empresa;
                this.sumTotal();
                this.cargarDocumentos();
                this.loading = false;
            }, error => {this.alertService.error(error);this.loading = false;});
        }

    }

    cargarDocumentos(){
        this.apiService.getAll('documentos/list').subscribe(documentos => {
            this.documentos = documentos;
            this.documentos = this.documentos.filter((x:any) => x.id_sucursal == this.devolucion.id_sucursal);

            this.documentos = this.documentos.filter((x:any) => x.nombre == 'Nota de crédito');
            let documento = this.documentos.find((x:any) => x.nombre == 'Nota de crédito');
            if(documento){
                this.devolucion.id_documento = documento.id;
                this.devolucion.correlativo = documento.correlativo;
            }

        }, error => {this.alertService.error(error);});
    }

    public setDocumento(id_documento:any){
        let documento = this.documentos.find((x:any) => x.id == id_documento);
        this.devolucion.id_documento = documento.id;
        this.devolucion.correlativo = documento.correlativo;
    }

    cargarDatosIniciales(){
        this.cargarDocumentos();
        this.devolucion = {};
        this.devolucion.fecha = this.apiService.date();
        this.devolucion.tipo = 'Interna';
        this.devolucion.cliente = {};
        this.devolucion.detalles = [];
        this.devolucion.canal = 'Tienda';
        this.devolucion.descuento = 0;
        this.detalle = {};

        let corte = JSON.parse(sessionStorage.getItem('SP_corte')!);
        if (corte) {
            this.devolucion.fecha = JSON.parse(sessionStorage.getItem('SP_corte')!).fecha;
            this.devolucion.caja_id = JSON.parse(sessionStorage.getItem('SP_corte')!).id_caja;
            this.devolucion.corte_id = JSON.parse(sessionStorage.getItem('SP_corte')!).id;
        }

        this.devolucion.id_usuario = this.apiService.auth_user().id;
        this.devolucion.id_sucursal = this.apiService.auth_user().id_sucursal;
        this.devolucion.id_empresa = this.apiService.auth_user().id_empresa;
        // this.sumTotal();
        this.imprimir = true;
    }

    public sumTotal() {
        this.devolucion.sub_total = (parseFloat(this.sumPipe.transform(this.devolucion.detalles, 'total'))).toFixed(2);
        
        this.devolucion.exenta = (parseFloat(this.sumPipe.transform(this.devolucion.detalles, 'exenta'))).toFixed(4);
        this.devolucion.no_sujeta = (parseFloat(this.sumPipe.transform(this.devolucion.detalles, 'no_sujeta'))).toFixed(4);
        
        this.devolucion.iva_percibido = this.devolucion.percepcion ? this.devolucion.sub_total * 0.01 : 0; 
        this.devolucion.iva_retenido = this.devolucion.retencion ? this.devolucion.sub_total * 0.01 : 0; 

        this.devolucion.impuestos.forEach((impuesto:any) => {
            if(this.devolucion.cobrar_impuestos){
                impuesto.monto = this.devolucion.sub_total * (impuesto.porcentaje / 100);
            }else{
                impuesto.monto = 0;
            }
        });

        this.devolucion.iva = (parseFloat(this.sumPipe.transform(this.devolucion.impuestos, 'monto'))).toFixed(2);
        this.devolucion.descuento = (parseFloat(this.sumPipe.transform(this.devolucion.detalles, 'descuento'))).toFixed(2);
        this.devolucion.total_costo = (parseFloat(this.sumPipe.transform(this.devolucion.detalles, 'total_costo'))).toFixed(2);
        this.devolucion.total = (parseFloat(this.devolucion.sub_total) + parseFloat(this.devolucion.iva) + parseFloat(this.devolucion.cuenta_a_terceros) + parseFloat(this.devolucion.exenta) + parseFloat(this.devolucion.no_sujeta) + parseFloat(this.devolucion.iva_percibido) - parseFloat(this.devolucion.iva_retenido)).toFixed(2);
        console.log(this.devolucion);
    }


    updateDevolucion(devolucion:any) {
        this.devolucion = devolucion;
        this.sumTotal();
    }

    // Devolución
        openModalDevolucion(template: TemplateRef<any>) {
            this.modalRef = this.modalService.show(template, {class: 'modal-sm'});
            this.devolucion.tipo = 'Cambio de producto';
        }

        public onDevolucion() {

            this.saving = true;
            this.apiService.store('devolucion/venta', this.devolucion).subscribe(devolucion => {
                this.saving = false;
                if(devolucion.tipo_documento == 'Factura' || devolucion.tipo_documento == 'Credito Fiscal' || devolucion.tipo_documento == 'Ticket'){
                    this.imprimirDocDevolucion(devolucion);
                }
                this.router.navigate(['/devoluciones/ventas']);
                this.alertService.success('Devolucion de venta creada', 'La devolución de venta fue guardado exitosamente.');
            },error => {this.alertService.error(error); this.saving = false; });
        }


    public imprimirDocDevolucion(devolucion:any){
        setTimeout(()=>{
            window.open(this.apiService.baseUrl + '/api/reporte/devolucion/' + devolucion.id + '?token=' + this.apiService.auth_token(), 'hola', 'width=400');
        }, 1000);
    }


}
