import { Component, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { SumPipe }     from '@pipes/sum.pipe';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-devolucion-compra-nueva',
  templateUrl: './devolucion-compra-nueva.component.html',
  providers: [ SumPipe ]
})

export class DevolucionCompraNuevaComponent implements OnInit {

    public compra: any= {};
    public devolucion: any= {};
    public detalle: any = {};
    public documentos:any = [];
    public supervisor:any = {};
    public loading = false;
    public saving = false;
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

        const id = +this.route.snapshot.queryParamMap.get('id_compra')!;
        console.log(id);
        if(id == 0){
            this.cargarDatosIniciales();
        }
        else{
            this.loading = true;
            this.compra.cliente = {};
            this.apiService.read('compra/', id).subscribe(compra => {
                this.compra = compra;
                this.devolucion.detalles = compra.detalles;
                this.devolucion.id_proveedor = compra.id_proveedor;
                this.devolucion.fecha = this.apiService.date();
                this.devolucion.id_compra = id;
                this.devolucion.tipo = 'Interna';
                this.devolucion.observaciones = '';

                this.devolucion.cobrar_impuestos = this.compra.iva > 0 ? true : false; 
                this.devolucion.cobrar_percepcion = this.compra.percepcion > 0 ? true : false; 
                this.devolucion.retencion = this.compra.iva_retenido > 0 ? true : false;

                let corte = JSON.parse(sessionStorage.getItem('SP_corte')!);
                if (corte) {
                    this.devolucion.id_caja = JSON.parse(sessionStorage.getItem('SP_corte')!).id_caja;
                    this.devolucion.id_corte = JSON.parse(sessionStorage.getItem('SP_corte')!).id;
                }
                this.devolucion.id_usuario = this.apiService.auth_user().id;
                this.devolucion.id_sucursal = this.apiService.auth_user().id_sucursal;
                this.devolucion.id_bodega = this.apiService.auth_user().id_bodega;
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
            this.documentos = this.documentos.filter((x:any) => x.id_sucursal == this.compra.id_sucursal);

            if (this.route.snapshot.queryParamMap.get('tipo_documento')! == 'nota_debito') {
                let documento = this.documentos.find((x:any) => x.nombre == 'Nota de débito');
                console.log(documento);
                if(documento){
                    this.devolucion.tipo_documento = documento.nombre;
                }
            }
            if (this.route.snapshot.queryParamMap.get('tipo_documento')! == 'nota_credito') {

                console.log(this.documentos);
                let documento = this.documentos.find((x:any) => x.nombre == 'Nota de crédito');
                console.log(documento);
                if(documento){
                    this.devolucion.tipo_documento = documento.nombre;
                }
            }
            console.log(this.devolucion);
        }, error => {this.alertService.error(error);});
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

        let corte = JSON.parse(sessionStorage.getItem('worder_corte')!);
        if (corte) {
            this.devolucion.fecha = JSON.parse(sessionStorage.getItem('worder_corte')!).fecha;
            this.devolucion.caja_id = JSON.parse(sessionStorage.getItem('worder_corte')!).id_caja;
            this.devolucion.corte_id = JSON.parse(sessionStorage.getItem('worder_corte')!).id;
        }

        this.devolucion.id_usuario = this.apiService.auth_user().id;
        this.devolucion.id_sucursal = this.apiService.auth_user().id_sucursal;
        this.devolucion.id_bodega = this.apiService.auth_user().id_bodega;
        // this.sumTotal();
        this.imprimir = true;
    }

    public sumTotal() {
        this.devolucion.sub_total = (parseFloat(this.sumPipe.transform(this.devolucion.detalles, 'total'))).toFixed(2);
        this.devolucion.iva_percibido = this.devolucion.cobrar_percepcion ? this.devolucion.sub_total * 0.01 : 0; 
        this.devolucion.iva_retenido = this.devolucion.retencion ? this.devolucion.sub_total * 0.01 : 0;
        this.devolucion.renta_retenida = this.devolucion.renta ? this.devolucion.sub_total * 0.10 : 0; 

        if(this.devolucion.cobrar_impuestos){
            this.devolucion.iva = ( this.devolucion.sub_total * 0.13 ).toFixed(2);
        }else{
            this.devolucion.iva = 0;
        }

        this.devolucion.descuento = (parseFloat(this.sumPipe.transform(this.devolucion.detalles, 'descuento'))).toFixed(2);
        this.devolucion.total_costo = (parseFloat(this.sumPipe.transform(this.devolucion.detalles, 'total_costo'))).toFixed(2);
        this.devolucion.total = (parseFloat(this.devolucion.sub_total) + parseFloat(this.devolucion.iva) + parseFloat(this.devolucion.iva_percibido) - parseFloat(this.devolucion.iva_retenido) - parseFloat(this.devolucion.renta_retenida)).toFixed(2);
    }


    updateCompra(devolucion:any) {
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
            this.apiService.store('devolucion-compra', this.devolucion).subscribe(devolucion => {
                this.saving = false;
                if(devolucion.tipo_documento == 'Factura' || devolucion.tipo_documento == 'Credito Fiscal' || devolucion.tipo_documento == 'Ticket'){
                    this.imprimirDocDevolucion(devolucion);
                }
                this.router.navigate(['/devoluciones/compras']);
                this.alertService.success('Devolucion de compra creada', 'La devolución de compra fue guardado exitosamente.');
            },error => {this.alertService.error(error); this.saving = false; });
        }


    public imprimirDocDevolucion(devolucion:any){
        setTimeout(()=>{
            window.open(this.apiService.baseUrl + '/api/reporte/devolucion/' + devolucion.id + '?token=' + this.apiService.auth_token(), 'hola', 'width=400');
        }, 1000);
    }


}
