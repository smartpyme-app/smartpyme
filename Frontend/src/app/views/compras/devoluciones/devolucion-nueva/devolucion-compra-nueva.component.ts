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
            this.cargarDocumentos();
            this.apiService.read('compra/', id).subscribe(compra => {
                this.compra = compra;
                this.compra.id = null;
                this.compra.fecha = this.apiService.date();
                this.compra.id_compra = id;
                this.compra.tipo = 'Interna';

                this.compra.percepcion = this.compra.iva_percibido > 0 ? true : false; 
                this.compra.retencion = this.compra.iva_retenido > 0 ? true : false;

                let corte = JSON.parse(sessionStorage.getItem('SP_corte')!);
                if (corte) {
                    this.compra.id_caja = JSON.parse(sessionStorage.getItem('SP_corte')!).id_caja;
                    this.compra.id_corte = JSON.parse(sessionStorage.getItem('SP_corte')!).id;
                }
                this.compra.id_usuario = this.apiService.auth_user().id;
                this.compra.id_sucursal = this.apiService.auth_user().id_sucursal;
                // this.sumTotal();
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
        this.compra.fecha = this.apiService.date();
        this.compra.tipo = 'Interna';
        this.compra.cliente = {};
        this.compra.detalles = [];
        this.compra.canal = 'Tienda';
        this.compra.descuento = 0;
        this.detalle = {};

        let corte = JSON.parse(sessionStorage.getItem('worder_corte')!);
        if (corte) {
            this.compra.fecha = JSON.parse(sessionStorage.getItem('worder_corte')!).fecha;
            this.compra.caja_id = JSON.parse(sessionStorage.getItem('worder_corte')!).id_caja;
            this.compra.corte_id = JSON.parse(sessionStorage.getItem('worder_corte')!).id;
        }

        this.compra.id_usuario = this.apiService.auth_user().id;
        this.compra.id_sucursal = this.apiService.auth_user().id_sucursal;
        // this.sumTotal();
        this.imprimir = true;
    }

    public sumTotal() {
        this.compra.sub_total = (parseFloat(this.sumPipe.transform(this.compra.detalles, 'total'))).toFixed(2);
        this.compra.iva_percibido = this.compra.percepcion ? this.compra.sub_total * 0.01 : 0; 
        this.compra.iva_retenido = this.compra.retencion ? this.compra.sub_total * 0.01 : 0; 

        this.compra.iva = (parseFloat(this.sumPipe.transform(this.compra.impuestos, 'monto'))).toFixed(2);
        this.compra.descuento = (parseFloat(this.sumPipe.transform(this.compra.detalles, 'descuento'))).toFixed(2);
        // this.compra.total_costo = (parseFloat(this.sumPipe.transform(this.compra.detalles, 'total_costo'))).toFixed(2);
        this.compra.total = (parseFloat(this.compra.sub_total) + parseFloat(this.compra.iva) + parseFloat(this.compra.iva_percibido) - parseFloat(this.compra.iva_retenido)).toFixed(2);
    }


    updateCompra(compra:any) {
        this.compra = compra;
        this.sumTotal();
    }

    // Devolución
        openModalDevolucion(template: TemplateRef<any>) {
            this.modalRef = this.modalService.show(template, {class: 'modal-sm'});
            this.compra.tipo = 'Cambio de producto';
        }

        public onDevolucion() {

            this.saving = true;
            this.apiService.store('devolucion-compra', this.compra).subscribe(compra => {
                this.saving = false;
                if(compra.tipo_documento == 'Factura' || compra.tipo_documento == 'Credito Fiscal' || compra.tipo_documento == 'Ticket'){
                    this.imprimirDocDevolucion(compra);
                }
                this.router.navigate(['/devoluciones/compras']);
                this.alertService.success('Devolucion de compra creada', 'La devolución de compra fue guardado exitosamente.');
            },error => {this.alertService.error(error); this.saving = false; });
        }


    public imprimirDocDevolucion(compra:any){
        setTimeout(()=>{
            window.open(this.apiService.baseUrl + '/api/reporte/devolucion/' + compra.id + '?token=' + this.apiService.auth_token(), 'hola', 'width=400');
        }, 1000);
    }


}
